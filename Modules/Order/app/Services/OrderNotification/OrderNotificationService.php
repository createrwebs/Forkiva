<?php

namespace Modules\Order\Services\OrderNotification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Events\OrderUpdateStatus;
use Modules\Order\Events\OrderVoided;
use Modules\Order\Models\Order;
use Modules\Order\Models\Reason;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\SeatingPlan\Models\TableMerge;
use Modules\Support\Money;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class OrderNotificationService implements OrderNotificationServiceInterface
{
    /**
     * Create a new OrderNotificationService instance.
     */
    public function __construct(protected NotificationDispatcherServiceInterface $dispatcher) {}

    /** {@inheritDoc} */
    public function sendCreated(Order $order): void
    {
        $order = $this->loadOrder($order);

        $this->dispatch(
            templateKey: 'order_created',
            order: $order,
            variables: array_merge($this->baseVariables($order), [
                'order_number' => $this->orderNumber($order),
                'branch_name' => $this->branchName($order),
                'order_total' => $this->moneyAmount($order->total),
                'currency' => $order->currency,
                'order_status' => $order->status->allTrans(),
            ])
        );
    }

    /**
     * Load the order graph required for notifications.
     *
     * @param  array<int, string>  $extra
     */
    protected function loadOrder(Order $order, array $extra = []): Order
    {
        $order->loadMissing([
            'branch',
            'customer',
            'waiter',
            'cashier',
            'createdBy',
            'modifiedBy',
            ...$extra,
        ]);

        return $order;
    }

    /**
     * Dispatch one notification template to all matching recipients.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function dispatch(string $templateKey, Order $order, array $variables, ?int $excludeUserId = null): void
    {
        $recipients = $this->recipients($order, $templateKey, $excludeUserId);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send($templateKey, $recipients, $variables);
    }

    /**
     * Get candidate recipients for an order workflow.
     *
     * @return array<int, User>
     */
    protected function recipients(Order $order, string $templateKey, ?int $excludeUserId = null): array
    {
        $branchRecipients = $this->branchRecipients($order, $templateKey);

        return collect([
            $order->customer,
            $order->waiter,
            $order->cashier,
            $order->createdBy,
            $order->modifiedBy,
        ])
            ->merge($branchRecipients)
            ->filter(fn ($user) => $user instanceof User && filled($user->id) && $user->id !== $excludeUserId)
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * Resolve branch-scoped and global-admin recipients for a template.
     *
     * @return Collection<int, User>
     */
    protected function branchRecipients(Order $order, string $templateKey): Collection
    {
        $roleKeys = $this->candidateRoleKeys($templateKey);
        if ($roleKeys === []) {
            return collect();
        }

        $globalRoles = array_values(array_intersect($roleKeys, [DefaultRole::Admin->value]));
        $branchRoles = array_values(array_diff($roleKeys, $globalRoles, [DefaultRole::Customer->value]));

        $branchRecipients = $branchRoles === []
            ? collect()
            : User::query()
                ->withOutGlobalBranchPermission()
                ->where('branch_id', $order->branch_id)
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $branchRoles))
                ->get();

        $globalRecipients = $globalRoles === []
            ? collect()
            : User::query()
                ->withOutGlobalBranchPermission()
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $globalRoles))
                ->get();

        return $branchRecipients
            ->merge($globalRecipients)
            ->unique('id')
            ->values();
    }

    /**
     * Get all roles that could receive a template across supported channels.
     *
     * @return array<int, string>
     */
    protected function candidateRoleKeys(string $templateKey): array
    {
        return collect(['email', 'in_app', 'push_notification', 'sms', 'whatsapp'])
            ->flatMap(fn (string $channel) => NotificationWorkflowRecipientCatalog::allowedRolesFor($templateKey, $channel))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Build the shared template metadata for order notifications.
     *
     * @return array<string, mixed>
     */
    protected function baseVariables(Order $order): array
    {
        return [
            'type' => 'order',
            'type_id' => $order->id,
            'app_name' => NotificationVariableValue::appName(),
        ];
    }

    /**
     * Get the public order number.
     */
    protected function orderNumber(Order $order): string
    {
        return $order->order_number ?: ($order->reference_no ?: (string) $order->id);
    }

    /**
     * Get the branch display name.
     */
    protected function branchName(Order $order): array|string
    {
        return $order->branch?->getTranslations('name') ?: '-';
    }

    /**
     * Normalize a money amount for template variables.
     */
    protected function moneyAmount(Money $money): string
    {
        return $money->round()->format();
    }

    /** {@inheritDoc} */
    public function sendUpdated(Order $order): void
    {
        $order = $this->loadOrder($order);

        $this->dispatch(
            templateKey: 'order_updated',
            order: $order,
            variables: array_merge($this->baseVariables($order), [
                'order_number' => $this->orderNumber($order),
                'branch_name' => $this->branchName($order),
                'order_status' => $order->status->allTrans(),
            ])
        );
    }

    /** {@inheritDoc} */
    public function sendKitchenItemReady(OrderUpdateStatus $event): void
    {
        if ($event->status !== OrderStatus::Ready) {
            return;
        }

        $order = $this->loadOrder($event->order, ['products']);

        $this->dispatch(
            templateKey: 'kitchen_item_ready',
            order: $order,
            variables: array_merge($this->baseVariables($order), [
                'order_number' => $this->orderNumber($order),
                'branch_name' => $this->branchName($order),
                'item_name' => $this->kitchenItemName($order),
                'kitchen_station' => $this->kitchenStation($event->changedById),
            ]),
            excludeUserId: $event->changedById
        );
    }

    /**
     * Resolve the order item name used in kitchen-ready templates.
     *
     * @return array<string, string>|string
     */
    protected function kitchenItemName(Order $order): array|string
    {
        $product = $order->products->first()?->product;
        if ($product && method_exists($product, 'getTranslations')) {
            $translations = $product->getTranslations('name');
            if ($translations !== []) {
                return $translations;
            }
        }

        if (filled($product?->name)) {
            return $product->name;
        }

        return collect(supportedLocaleKeys())
            ->mapWithKeys(fn (string $locale) => [
                $locale => trans('order::messages.notification_order_item', locale: $locale),
            ])
            ->all();
    }

    /**
     * Resolve the kitchen station label for kitchen-ready templates.
     *
     * @return array<string, string>|string
     */
    protected function kitchenStation(?int $changedById): array|string
    {
        if ($changedById) {
            $name = User::query()
                ->withOutGlobalBranchPermission()
                ->withoutGlobalActive()
                ->withTrashed()
                ->whereKey($changedById)
                ->toBase()
                ->value('name');

            if (filled($name)) {
                return (string) $name;
            }
        }

        return collect(supportedLocaleKeys())
            ->mapWithKeys(fn (string $locale) => [
                $locale => trans('order::messages.notification_kitchen_station', locale: $locale),
            ])
            ->all();
    }

    /** {@inheritDoc} */
    public function sendStatusChanged(OrderUpdateStatus $event): void
    {
        if (in_array($event->status, [OrderStatus::Ready, OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            return;
        }

        $order = $this->loadOrder($event->order);

        $this->dispatch(
            templateKey: 'order_status_changed',
            order: $order,
            variables: array_merge($this->baseVariables($order), [
                'order_number' => $this->orderNumber($order),
                'branch_name' => $this->branchName($order),
                'order_status' => $event->status->allTrans(),
            ]),
            excludeUserId: $event->changedById
        );
    }

    /** {@inheritDoc} */
    public function sendVoided(OrderVoided $event): void
    {
        $order = $this->loadOrder($event->order);
        $templateKey = match ($event->status) {
            OrderStatus::Cancelled => 'order_cancelled',
            OrderStatus::Refunded => 'order_refunded',
            default => 'order_voided',
        };

        $this->dispatch(
            templateKey: $templateKey,
            order: $order,
            variables: array_merge(
                $this->baseVariables($order),
                $this->voidedVariables($order, $event, $templateKey),
            ),
            excludeUserId: $event->changedById
        );
    }

    /**
     * Build template variables for cancelled, refunded, and voided workflows.
     *
     * @return array<string, mixed>
     */
    protected function voidedVariables(Order $order, OrderVoided $event, string $templateKey): array
    {
        return match ($templateKey) {
            'order_cancelled' => [
                'order_number' => $this->orderNumber($order),
                'branch_name' => $this->branchName($order),
                'cancellation_reason' => $this->voidReason($event->reasonId, $event->note),
            ],
            'order_refunded' => [
                'order_number' => $this->orderNumber($order),
                'refund_amount' => $this->moneyAmount($order->total),
                'currency' => $order->currency,
                'refund_status' => $event->status->allTrans(),
            ],
            default => [
                'order_number' => $this->orderNumber($order),
                'branch_name' => $this->branchName($order),
                'void_reason' => $this->voidReason($event->reasonId, $event->note),
            ],
        };
    }

    /**
     * Resolve the translated void reason.
     *
     * @return array<string, string>|string
     */
    protected function voidReason(?int $reasonId, ?string $note): array|string
    {
        $reason = $reasonId ? Reason::query()->find($reasonId) : null;
        if ($reason) {
            return $reason->getTranslations('name');
        }

        if (filled($note)) {
            return $note;
        }

        return [
            'en' => 'Not provided',
            'ar' => 'غير محدد',
        ];
    }

    /** {@inheritDoc} */
    public function sendMergedPaymentCompleted(TableMerge $tableMerge): void
    {
        Order::query()
            ->where('table_merge_id', $tableMerge->id)
            ->get()
            ->each(fn (Order $order) => $this->sendPaymentCompleted($order));
    }

    /** {@inheritDoc} */
    public function sendPaymentCompleted(Order $order): void
    {
        $order = $this->loadOrder($order, ['payments']);

        $this->dispatch(
            templateKey: 'order_payment_completed',
            order: $order,
            variables: array_merge($this->baseVariables($order), [
                'order_number' => $this->orderNumber($order),
                'paid_amount' => $this->moneyAmount($order->total),
                'currency' => $order->currency,
                'payment_method' => $this->paymentMethod($order),
            ])
        );
    }

    /**
     * Resolve the translated payment method label.
     *
     * @return array<string, string>|string
     */
    protected function paymentMethod(Order $order): array|string
    {
        $methods = $order->payments
            ->whereNull('voided_at')
            ->where('type', PaymentType::Payment)
            ->pluck('method')
            ->filter()
            ->unique()
            ->values();

        if ($methods->count() === 1) {
            $method = $methods->first();

            return ($method instanceof PaymentMethod ? $method : PaymentMethod::from((string) $method))->allTrans();
        }

        if ($methods->count() > 1) {
            return collect(supportedLocaleKeys())
                ->mapWithKeys(fn (string $locale) => [
                    $locale => trans('order::messages.notification_multiple_payment_methods', locale: $locale),
                ])
                ->all();
        }

        return '-';
    }
}
