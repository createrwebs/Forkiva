<?php

namespace Modules\Order\Services\Checkout;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Discount\Models\Discount;
use Modules\GiftCard\Enums\GiftCardScope;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Services\GiftCard\GiftCardServiceInterface;
use Modules\Loyalty\Models\LoyaltyGift;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Services\Order\OrderServiceInterface;
use Modules\Order\Services\SaveOrder\SaveOrderServiceInterface;
use Modules\Order\Support\OrderRevision;
use Modules\Payment\Enums\RefundPaymentMethod;
use Modules\Pos\Enums\PosSubmitAction;
use Modules\Pos\Models\PosSession;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Table;
use Modules\Voucher\Models\Voucher;
use Throwable;

final readonly class PosCheckout
{
    public function __construct(
        private CheckoutPricing $pricing,
        private CheckoutConfirmation $confirmation,
        private SaveOrderServiceInterface $orders,
    ) {}

    /**
     * Validate the editable order revision and issue an authoritative price preview.
     *
     * @throws JsonException
     * @throws BindingResolutionException|CircularDependencyException
     */
    public function quote(array $data, ?int $id = null): array
    {
        $this->authorize($data);
        $order = $id ? Order::query()->findOrFail($id) : null;
        $this->checkRevision($order, $data);
        $this->validateRefundGiftCard($data);
        $pricing = $this->pricing->calculate($data, $order)->toArray();

        return $this->confirmation->quote($data, $this->serialize($pricing), $this->scope($id));
    }

    /**
     * Resolve branch access before reading or replaying checkout data.
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     */
    private function authorize(array $data): void
    {
        app(BranchAccessServiceInterface::class)->resolve((int) $data['branch_id'], true);
    }

    /**
     * Reject a draft whose saved order changed after it was loaded.
     *
     * @throws JsonException
     */
    private function checkRevision(?Order $order, array $data): void
    {
        if ($order) {
            abort_unless(hash_equals(OrderRevision::for($order), $data['edit_version'] ?? ''), 409, __('order::checkout.order_changed'));
        }
    }

    /**
     * Validate the refund destination again under a row lock before committing stored value.
     */
    private function validateRefundGiftCard(array $data, bool $lock = false): void
    {
        if (($data['refund_payment_method'] ?? null) !== RefundPaymentMethod::GiftCard->value) {
            return;
        }

        try {
            $giftCard = app(GiftCardServiceInterface::class)->findOrFail($data['gift_card_code'] ?? '');
            if ($lock) {
                $giftCard = GiftCard::query()->whereKey($giftCard->id)->lockForUpdate()->firstOrFail();
            }
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['gift_card_code' => __('giftcard::messages.invalid_card')]);
        }

        $message = match (true) {
            $giftCard->isExpired() => 'card_expired',
            $giftCard->isDisabled() => 'card_disabled',
            ! $giftCard->isUsable() => 'invalid_card',
            $giftCard->scope === GiftCardScope::Branch && (int) $giftCard->branch_id !== (int) $data['branch_id'] => 'invalid_card',
            $giftCard->customer_id && (int) $giftCard->customer_id !== (int) ($data['customer_id'] ?? 0) => 'card_customer_mismatch',
            default => null,
        };
        if ($message) {
            throw ValidationException::withMessages(['gift_card_code' => __("giftcard::messages.$message")]);
        }
    }

    /**
     * Convert Money and other serializable values to the public quote representation.
     *
     * @throws JsonException
     */
    private function serialize(array $data): array
    {
        return json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Bind receipts and quotes to the authenticated cashier and operation.
     */
    private function scope(?int $id): string
    {
        return 'pos:'.auth()->id().':'.($id ? 'edit:'.$id : 'create');
    }

    /**
     * Serialize competing table, order and session writes before recalculation and saving.
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws JsonException
     * @throws Throwable
     */
    public function submit(array $data, ?int $id = null): array
    {
        $this->authorize($data);

        $existing = $id ? Order::query()->findOrFail($id) : null;
        $tableIds = array_filter([$data['table_id'] ?? null, $existing?->table_id]);

        return $this->confirmation->once($this->scope($id), $data, function () use ($data, $id, $existing, $tableIds) {
            // Same order of locks for create, append, edit and payment paths.
            if ($tableIds) {
                Table::query()->whereIn('id', $tableIds)->orderBy('id')->lockForUpdate()->get();
            }
            if (! empty($data['table_id'])) {
                $table = Table::query()->whereKey($data['table_id'])->lockForUpdate()->firstOrFail();
                $sameTable = (int) $existing?->table_id === (int) $table->id;
                abort_unless(
                    (int) $table->branch_id === (int) $data['branch_id']
                        && $table->is_active
                        && ($sameTable || $table->status === TableStatus::Available),
                    409,
                    __('order::checkout.order_changed'),
                );
            }
            $order = $id ? Order::query()->whereKey($id)->lockForUpdate()->firstOrFail() : null;
            abort_unless(! $order || $order->table_id === $existing?->table_id, 409, __('order::checkout.order_changed'));
            $session = PosSession::query()->whereKey($data['session_id'])->lockForUpdate()->firstOrFail();
            abort_unless(
                $session->status->value === 'open'
                    && (int) $session->branch_id === (int) $data['branch_id']
                    && (int) $session->pos_register_id === (int) $data['register_id'],
                409,
                __('order::checkout.session_changed'),
            );
            $this->lockBenefits($data);
            $this->checkRevision($order, $data);
            $this->validateRefundGiftCard($data, true);
            $calculation = $this->pricing->calculate($data, $order);
            $pricing = $this->serialize($calculation->toArray());
            if (! empty($data['displayed_pricing']) && empty($data['quote_token'])) {
                $this->confirmation->confirmDisplayed($data, $pricing, $this->scope($id));
            } else {
                $this->confirmation->confirm($data, $pricing, $this->scope($id));
            }
            $saved = $id ? $this->orders->update($id, $data) : $this->orders->create($data);

            // A held order must enter the kitchen through the existing status/event workflow.
            // Keep this inside the submission transaction so retries cannot fire it twice.
            if ($id && $saved->status === OrderStatus::Pending
                && ($data['submit_action'] ?? null) === PosSubmitAction::SendToKitchen->value) {
                app(OrderServiceInterface::class)->moveToNextStatus($saved->id);
            }

            return ['id' => $saved->id, 'order_id' => $saved->reference_no];
        });
    }

    /**
     * Serialize consumption of limited discounts, vouchers and customer rewards.
     */
    private function lockBenefits(array $data): void
    {
        if (! empty($data['discount_id'])) {
            $discount = Discount::query()->whereKey($data['discount_id'])->lockForUpdate()->first();
            $discount?->gift()->lockForUpdate()->first();
        }
        if (! empty($data['voucher_code'])) {
            $voucher = Voucher::query()->where('code', $data['voucher_code'])->lockForUpdate()->first();
            $voucher?->gift()->lockForUpdate()->first();
        }
        if (! empty($data['gift_id'])) {
            LoyaltyGift::query()->whereKey($data['gift_id'])->lockForUpdate()->first();
        }
    }
}
