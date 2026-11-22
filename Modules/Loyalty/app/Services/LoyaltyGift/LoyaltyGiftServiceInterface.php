<?php

namespace Modules\Loyalty\Services\LoyaltyGift;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Loyalty\Models\LoyaltyCustomer;
use Modules\Loyalty\Models\LoyaltyGift;
use Modules\Order\Models\Order;
use Throwable;

interface LoyaltyGiftServiceInterface extends TableExportSourceInterface
{
    /**
     * Redeem points immediately to create a persistent gift.
     * No order is required.
     *
     * @throws Throwable
     */
    public function redeem(int $rewardId, int $customerId, array $ctx = []): LoyaltyGift;

    /**
     * Get active gifts for a customer.
     */
    public function availableGifts(int $customerId, ?int $programId = null): Collection;

    /**
     * Use a gift on an order.
     *
     * @param  LoyaltyCustomer|null  $lc
     *
     * @throws Throwable
     */
    public function useGift(int $loyaltyGiftId, ?Order $order = null): void;

    /**
     * Expire old gifts.
     *
     * @throws Throwable
     */
    public function expireGifts(int $batch = 100): int;

    /**
     * Fetch all rewards currently available for redemption by the given customer.
     */
    public function getRewards(int $customerId, ?int $programId = null, ?int $branchId = null): array;

    /**
     * Get  gift for a customer by id.
     */
    public function availableGift(int $customerId, int $giftId, ?int $branchId = null): ?LoyaltyGift;

    /**
     * Rollback Gift on an order.
     *
     * @throws Throwable
     */
    public function rollbackGift(int $loyaltyGiftId, Order $order): void;

    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], array $sorts = []): LengthAwarePaginator;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(?int $programId = null): array;

    /**
     * Model for the resource.
     */
    public function model(): string;

    /**
     * Get a new instance of the model.
     */
    public function getModel(): LoyaltyGift;

    /**
     * Label for the resource.
     */
    public function label(): string;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
