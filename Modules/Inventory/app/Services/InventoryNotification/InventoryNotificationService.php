<?php

namespace Modules\Inventory\Services\InventoryNotification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Enums\StockTransferStatus;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\StockTransfer;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class InventoryNotificationService implements InventoryNotificationServiceInterface
{
    /**
     * Create a new InventoryNotificationService instance.
     */
    public function __construct(protected NotificationDispatcherServiceInterface $dispatcher) {}

    /** {@inheritDoc} */
    public function sendPurchaseUpdated(Purchase $purchase): void
    {
        $purchase = $this->loadPurchase($purchase);

        $recipients = $this->recipients($purchase, 'inventory_purchase_updated');
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send(
            'inventory_purchase_updated',
            $recipients,
            array_merge($this->basePurchaseVariables($purchase), [
                'purchase_number' => $purchase->reference_number ?: (string) $purchase->id,
                'branch_name' => $purchase->branch?->getTranslations('name') ?: '-',
                'purchase_status' => $purchase->status->allTrans(),
                'supplier_name' => $purchase->supplier?->name ?: '-',
                'total_amount' => $this->moneyAmount($purchase->total),
            ])
        );
    }

    /**
     * Load purchase relations required for notifications.
     */
    protected function loadPurchase(Purchase $purchase): Purchase
    {
        $purchase->loadMissing([
            'branch',
            'supplier',
            'items.receiptItems',
            'items.ingredient.unit',
        ]);

        return $purchase;
    }

    /**
     * Resolve candidate recipients for one inventory workflow.
     *
     * @return array<int, User>
     */
    protected function recipients(Purchase $purchase, string $templateKey): array
    {
        return $this->branchRecipients($purchase->branch_id, $templateKey)
            ->filter(fn ($user) => $user instanceof User && filled($user->id))
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * Resolve branch-scoped and global-admin recipients for an inventory template.
     *
     * @return Collection<int, User>
     */
    protected function branchRecipients(?int $branchId, string $templateKey): Collection
    {
        $roleKeys = $this->candidateRoleKeys($templateKey);
        if ($roleKeys === []) {
            return collect();
        }

        $globalRoles = array_values(array_intersect($roleKeys, [DefaultRole::Admin->value]));
        $branchRoles = array_values(array_diff($roleKeys, $globalRoles));

        $branchRecipients = ($branchId && $branchRoles !== [])
            ? User::query()
                ->withOutGlobalBranchPermission()
                ->where('branch_id', $branchId)
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $branchRoles))
                ->get()
            : collect();

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

    /** {@inheritDoc} */
    public function sendStockTransferStatusChanged(
        StockTransfer $transfer,
        StockTransferStatus $status,
        ?string $reason = null,
    ): void {
        $transfer->loadMissing(['sourceBranch', 'destinationBranch']);
        $templateKey = 'stock_transfer_status_changed';
        $recipients = $this->branchRecipients($transfer->source_branch_id, $templateKey)
            ->merge($this->branchRecipients($transfer->destination_branch_id, $templateKey))
            ->filter(fn ($user) => $user instanceof User && filled($user->id))
            ->unique('id')
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send($templateKey, $recipients, [
            'type' => 'stock_transfer',
            'type_id' => $transfer->id,
            'app_name' => NotificationVariableValue::appName(),
            'transfer_reference' => $transfer->reference_number ?: (string) $transfer->id,
            'source_branch' => $transfer->sourceBranch?->getTranslations('name') ?: '-',
            'destination_branch' => $transfer->destinationBranch?->getTranslations('name') ?: '-',
            'transfer_status' => $status->allTrans(),
            'reason' => $reason ?: '-',
        ]);
    }

    /**
     * Resolve all allowed role keys for a template across supported channels.
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
     * Build the shared purchase template metadata.
     *
     * @return array<string, mixed>
     */
    protected function basePurchaseVariables(Purchase $purchase): array
    {
        return [
            'type' => 'purchase',
            'type_id' => $purchase->id,
            'currency' => $purchase->currency,
            'app_name' => NotificationVariableValue::appName(),
        ];
    }

    /**
     * Normalize a money amount for template variables.
     */
    protected function moneyAmount($money): string
    {
        return $money->round()->format();
    }

    /** {@inheritDoc} */
    public function sendStockMovementNeedsAttention(Purchase $purchase): void
    {
        $purchase = $this->loadPurchase($purchase);

        if ($purchase->status !== PurchaseStatus::PartiallyReceived) {
            return;
        }

        $recipients = $this->recipients($purchase, 'stock_movement_needs_attention');
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send(
            'stock_movement_needs_attention',
            $recipients,
            array_merge(
                $this->basePurchaseVariables($purchase),
                $this->stockMovementAttentionVariables($purchase),
            )
        );
    }

    /**
     * Build stock-movement variables from the first partially received item.
     *
     * @return array<string, mixed>
     */
    protected function stockMovementAttentionVariables(Purchase $purchase): array
    {
        $item = $purchase->items->first(function ($item) {
            $receivedQuantity = (float) $item->receiptItems->sum('received_quantity');

            return round((float) $item->quantity - $receivedQuantity, 3) > 0;
        });

        $ingredient = $item?->ingredient;
        $missingQuantity = $item
            ? max(0, round((float) $item->quantity - (float) $item->receiptItems->sum('received_quantity'), 3))
            : 0;
        $unitSymbol = $ingredient?->unit?->symbol;

        return [
            'product_name' => $ingredient?->getTranslations('name') ?: ($ingredient?->name ?: '-'),
            'sku' => $ingredient ? sprintf('ING-%s', $ingredient->id) : '-',
            'branch_name' => $purchase->branch?->getTranslations('name') ?: '-',
            'movement_type' => collect(supportedLocaleKeys())
                ->mapWithKeys(fn (string $locale) => [
                    $locale => trans('inventory::messages.notification_partial_receipt', locale: $locale),
                ])
                ->all(),
            'quantity' => trim($this->quantity($missingQuantity).' '.($unitSymbol ?: '')),
            'reason' => collect(supportedLocaleKeys())
                ->mapWithKeys(fn (string $locale) => [
                    $locale => trans('inventory::messages.notification_received_quantity_lower_than_ordered', locale: $locale),
                ])
                ->all(),
        ];
    }

    /**
     * Normalize a quantity for template variables.
     */
    protected function quantity(float|int $value): string
    {
        $normalized = round((float) $value, 3);

        return rtrim(rtrim(number_format($normalized, 3, '.', ''), '0'), '.');
    }
}
