<?php

namespace Modules\Core\Contracts;

use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Printer\Contracts\PrintContentFactoryInterface;
use Modules\User\Models\User;

interface DeliveryFeatureInterface
{
    /**
     * Determine whether the delivery feature is active.
     */
    public function enabled(): bool;

    /**
     * Get feature metadata for POS bootstrap.
     *
     * @return array<string, mixed>
     */
    public function posMeta(): array;

    /**
     * Extend the authenticated user payload with delivery-specific data.
     *
     * @return array<string, mixed>
     */
    public function authPayload(?User $user): array;

    /**
     * Remove unsupported order types for the branch.
     *
     * @param  array<int, string>  $orderTypes
     * @return array<int, string>
     */
    public function filterSupportedOrderTypes(Branch $branch, array $orderTypes): array;

    /**
     * Set validated delivery selections on the request-local order calculation.
     */
    public function updatePricingContext(array $data): void;

    public function syncPricingCharges(bool $force = false): void;

    /**
     * Validate delivery eligibility before order submission.
     *
     * @return array<string, string>
     */
    public function validatePricingForSubmit(): array;

    /**
     * Synchronize delivery data for the order when supported.
     */
    public function syncOrderFromPricing(Order $order): ?object;

    /**
     * Extend order payload relations for edit initialization.
     *
     * @return array<int|string, mixed>
     */
    public function editOrderRelations(): array;

    /**
     * Extend order payload relations for show endpoints.
     *
     * @return array<int|string, mixed>
     */
    public function showOrderRelations(): array;

    /**
     * Extend the order show payload.
     *
     * @return array<string, mixed>
     */
    public function extendOrderPayload(Order $order): array;

    /**
     * Extend available print contents for the order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extendPrintContents(Order $order): array;

    /**
     * Resolve the delivery print content factory when available.
     */
    public function deliveryPrintFactory(): ?PrintContentFactoryInterface;

    /**
     * Resolve the order delivery model class when available.
     *
     * @return class-string|null
     */
    public function orderDeliveryModelClass(): ?string;

    /**
     * Determine whether delivery state allows editing the order.
     */
    public function allowsOrderEdit(Order $order): bool;

    /**
     * Override the next order status when delivery is involved.
     */
    public function resolveNextStatus(Order $order, ?OrderStatus $defaultStatus): ?OrderStatus;

    /**
     * Extract delivery context from the order for cart editing.
     *
     * @return array<string, mixed>|null
     */
    public function orderDeliveryContext(Order $order): ?array;
}
