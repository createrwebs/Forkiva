<?php

namespace Modules\Core\Services\Delivery;

use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Printer\Contracts\PrintContentFactoryInterface;
use Modules\User\Models\User;

class NullDeliveryFeatureService implements DeliveryFeatureInterface
{
    /** {@inheritDoc} */
    public function enabled(): bool
    {
        return false;
    }

    /** {@inheritDoc} */
    public function posMeta(): array
    {
        return [
            'delivery_methods' => [],
            'payment_collection_channels' => [],
        ];
    }

    /** {@inheritDoc} */
    public function authPayload(?User $user): array
    {
        return [
            'delivery_driver' => null,
        ];
    }

    /** {@inheritDoc} */
    public function filterSupportedOrderTypes(Branch $branch, array $orderTypes): array
    {
        return array_values(array_filter($orderTypes, fn (string $type) => $type !== 'delivery'));
    }

    /** {@inheritDoc} */
    public function updatePricingContext(array $data): void {}

    public function syncPricingCharges(bool $force = false): void {}

    /** {@inheritDoc} */
    public function validatePricingForSubmit(): array
    {
        return [];
    }

    /** {@inheritDoc} */
    public function syncOrderFromPricing(Order $order): ?object
    {
        return null;
    }

    /** {@inheritDoc} */
    public function editOrderRelations(): array
    {
        return [];
    }

    /** {@inheritDoc} */
    public function showOrderRelations(): array
    {
        return [];
    }

    /** {@inheritDoc} */
    public function extendOrderPayload(Order $order): array
    {
        return [];
    }

    /** {@inheritDoc} */
    public function extendPrintContents(Order $order): array
    {
        return [];
    }

    /** {@inheritDoc} */
    public function deliveryPrintFactory(): ?PrintContentFactoryInterface
    {
        return null;
    }

    /** {@inheritDoc} */
    public function orderDeliveryModelClass(): ?string
    {
        return null;
    }

    /** {@inheritDoc} */
    public function allowsOrderEdit(Order $order): bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function resolveNextStatus(Order $order, ?OrderStatus $defaultStatus): ?OrderStatus
    {
        return $defaultStatus;
    }

    /** {@inheritDoc} */
    public function orderDeliveryContext(Order $order): ?array
    {
        return null;
    }
}
