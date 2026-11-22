<?php

namespace Modules\Order\Services\Checkout;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Branch\Models\Branch;
use Modules\Category\Models\Category;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Discount\Services\DiscountApplyService\DiscountApplyServiceInterface;
use Modules\Loyalty\Enums\LoyaltyRewardType;
use Modules\Loyalty\Models\LoyaltyGift;
use Modules\Loyalty\Services\LoyaltyGift\LoyaltyGiftServiceInterface;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Facades\Pricing;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Pricing\Pricing as Calculation;
use Modules\Order\Support\CheckoutInput;
use Modules\Product\Models\Product;
use Modules\Product\Support\ProductOptionValidation;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

final class CheckoutPricing
{
    public function __construct(private readonly DiscountApplyServiceInterface $discounts) {}

    /**
     * Rebuild authoritative pricing from validated selections and the current catalog.
     *
     * Existing order lines retain their fulfillment restrictions. No client prices or
     * persistent cart state participate in this calculation.
     *
     * @throws ValidationException
     */
    public function calculate(array $data, ?Order $order = null, bool $public = false): Calculation
    {
        $branch = Branch::query()->findOrFail($data['branch_id']);
        $this->initialize($data, $branch, $order);
        $products = Product::with(['options.values', 'files', 'taxes', 'categories', 'menu'])
            ->whereHas('menu', fn ($q) => $q->where('branch_id', $branch->id)/** ->where('is_active', true) TODO: Remove comment */)
            ->whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
        $original = $order?->products->keyBy('id') ?? collect();
        $seen = [];
        $activeCategories = [];
        foreach ($data['items'] as $index => $line) {
            $path = "items.$index";
            /** @var Product $product */
            $product = $products->get($line['product_id']);
            if (! $product || ($public && $product->menu_id !== (int) $data['menu_id'])) {
                $this->invalid("$path.product_id");
            }
            $active = $activeCategories[$product->menu_id] ??= Category::getFullyActiveCategoryIds($product->menu_id);
            if ($product->categories->isNotEmpty() && $product->categories->pluck('id')->intersect($active)->isEmpty()) {
                $this->invalid("$path.product_id");
            }
            $options = ProductOptionValidation::normalize($line['options']);
            if (array_diff(array_map('strval', array_keys($line['options'])), $product->options->pluck('id')->map('strval')->all())) {
                $this->invalid("$path.options");
            }
            Validator::make(
                ['items' => [$index => ['options' => $options]]],
                ProductOptionValidation::rules($product->options, "$path.options"),
                ProductOptionValidation::messages("$path.options"),
            )->validate();
            /** @var OrderProduct|null $saved */
            $saved = isset($line['order_product_id']) ? $original->get($line['order_product_id']) : null;
            if (isset($line['order_product_id']) && (! $saved || isset($seen[$saved->id]) || (int) $saved->product_id !== (int) $product->id)) {
                $this->invalid("$path.order_product_id");
            }
            $actions = $line['actions'] ?? [];
            if ((! $saved && ! empty($actions)) || ($line['qty'] < 1 && empty($actions))) {
                $this->invalid("$path.qty");
            }
            if ($saved) {
                if ($saved->gift && $actions) {
                    $this->invalid("$path.actions");
                }
                if ($saved->gift && $saved->gift->customer?->customer_id !== Pricing::customer()?->id()) {
                    $this->invalid("$path.order_product_id");
                }
                $seen[$saved->id] = true;
                if (in_array($saved->status, [OrderProductStatus::Cancelled, OrderProductStatus::Refunded], true)) {
                    $this->invalid("$path.order_product_id");
                }
                if ($saved->status !== OrderProductStatus::Pending || $saved->gift) {
                    $this->validateCommittedLine($saved, $product, $options, $line, $path);
                }
            }
            $item = Pricing::store(
                productId: $product->id,
                qty: $line['qty'] + array_sum($actions),
                options: $options,
                orderProduct: $saved,
                gift: $saved?->gift,
                lineKey: $line['line_id'],
                resolvedProduct: $product,
            );
            foreach ($actions as $action => $qty) {
                $allowed = $action === 'cancel' ? [OrderProductStatus::Preparing] : [OrderProductStatus::Ready, OrderProductStatus::Served];
                if (! $saved || ! in_array($saved->status, $allowed, true) || ! auth()->user()?->can('admin.orders.'.$action)) {
                    $this->invalid("$path.actions");
                }
                Pricing::storeAction($item->id, $action, $qty);
            }
        }
        foreach ($original as $saved) {
            if (! isset($seen[$saved->id]) && ! in_array($saved->status, [OrderProductStatus::Pending, OrderProductStatus::Cancelled, OrderProductStatus::Refunded], true)) {
                $this->invalid('items');
            }
        }
        $this->applyAdjustments($data, $branch);

        return Pricing::instance();
    }

    /**
     * Initialize branch, order type and customer before any item is priced.
     */
    private function initialize(array $data, Branch $branch, ?Order $order): void
    {
        Pricing::clear();
        if ($order) {
            abort_unless($order->branch_id === $branch->id && $order->editIsAllowed(), 409, __('order::messages.edit_not_allowed'));
            $order->setRelation('branch', $branch);
            $order->loadMissing(['products.options.values', 'products.options.option', 'products.gift.customer']);
            // Submitted selections rebuild lines, discounts and delivery below; only retain order identity here.
            Pricing::addOrder($order);
        } else {
            Pricing::addBranch($branch);
        }
        Pricing::addOrderType(OrderType::from($data['type']));
        if (isset($data['customer_id'])) {
            $customer = User::role(DefaultRole::Customer)
                ->where(fn ($query) => $query->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->findOrFail($data['customer_id']);
            Pricing::addCustomer($customer);
        } else {
            Pricing::removeCustomer();
        }
        Pricing::removeDiscount();
    }

    /**
     * Preserve the input path so the browser can highlight the affected line or option.
     */
    private function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => __('order::checkout.invalid_input')]);
    }

    /**
     * Keep fulfilled and gifted lines immutable except for authorized quantity actions.
     */
    private function validateCommittedLine(OrderProduct $saved, Product $product, array $options, array $line, string $path): void
    {
        $expectedOptions = [];
        foreach ($saved->options as $option) {
            $expectedOptions[$option->option_id] = $option->option->type->isFieldType()
                ? $option->value
                : $option->values->pluck('id')->sort()->values()->all();
        }
        $normalized = [];
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = collect($value)->map(fn ($value) => (int) $value)->sort()->values()->all();
            } elseif ($product->options->firstWhere('id', $key)?->type->isFieldType()) {
                $normalized[$key] = $value;
            } else {
                $normalized[$key] = [(int) $value];
            }
        }
        // Form submissions and stored options may represent the same numeric selection as strings or integers.
        if (CheckoutInput::canonical($normalized) != CheckoutInput::canonical($expectedOptions)) {
            $this->invalid("$path.options");
        }
        if ($saved->quantity !== $line['qty'] + array_sum($line['actions'] ?? [])) {
            $this->invalid("$path.qty");
        }
    }

    /**
     * Resolve discounts, gifts and delivery charges through their owning modules.
     */
    private function applyAdjustments(array $data, Branch $branch): void
    {
        if (! empty($data['discount_id'])) {
            $this->discounts->applyDiscount(Pricing::class, $data['discount_id']);
        }
        if (! empty($data['voucher_code'])) {
            $this->discounts->applyVoucher(Pricing::class, $data['voucher_code']);
        }
        if (! empty($data['gift_id'])) {
            $this->applyGift($data['gift_id'], $branch->id);
        }
        if ($data['type'] === OrderType::Delivery->value && isset($data['delivery'])) {
            app(DeliveryFeatureInterface::class)->updatePricingContext($data['delivery']);
        }
        app(DeliveryFeatureInterface::class)->syncPricingCharges(true);
        $errors = app(DeliveryFeatureInterface::class)->validatePricingForSubmit();
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Resolve the selected reward for the current customer and branch.
     */
    private function applyGift(int $id, int $branchId): void
    {
        $gift = Pricing::hasCustomer() ? app(LoyaltyGiftServiceInterface::class)->availableGift(Pricing::customer()->id(), $id, $branchId) : null;
        if (! $gift) {
            $this->invalid('gift_id');
        }
        match ($gift->type) {
            LoyaltyRewardType::Discount => $this->discounts->applyDiscount(Pricing::class, $gift->artifact_id, $gift),
            LoyaltyRewardType::VoucherCode => $this->discounts->applyVoucher(Pricing::class, $gift->artifact->code, $gift),
            LoyaltyRewardType::FreeItem => $this->addGiftProduct($gift, $branchId),
            default => $this->invalid('gift_id'),
        };
    }

    /**
     * Derive the free product and quantity from the server-owned reward.
     */
    private function addGiftProduct(LoyaltyGift $gift, int $branchId): void
    {
        $product = Product::where('sku', $gift->meta['product_sku'] ?? null)
            ->whereHas('menu', fn ($q) => $q->where('branch_id', $branchId) /** ->where('is_active', true) TODO: Remove comment on add is default to menu */)->first();
        if (! $product) {
            $this->invalid('gift_id');
        }
        Pricing::store($product->id, (int) ($gift->meta['quantity'] ?? 1), gift: $gift, lineKey: 'gift:'.$gift->id);
    }
}
