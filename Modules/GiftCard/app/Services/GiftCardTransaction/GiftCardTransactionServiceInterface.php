<?php

namespace Modules\GiftCard\Services\GiftCardTransaction;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use JsonException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardTransaction;
use Throwable;

interface GiftCardTransactionServiceInterface extends TableExportSourceInterface
{
    /**
     * Get the human-readable module label.
     */
    public function label(): string;

    /**
     * Get paginated gift card transactions using filters and sorting options.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>|null  $sorts
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Show a single gift card transaction by database identifier or UUID.
     */
    public function show(int|string $id): GiftCardTransaction;

    /**
     * Resolve a gift card transaction or fail by database identifier or UUID.
     */
    public function findOrFail(int|string $id): GiftCardTransaction;

    /**
     * Get structure filters for transaction listing screens.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStructureFilters(): array;

    /**
     * Record a balance-affecting transaction for the given gift card.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function record(GiftCard $giftCard, array $data): GiftCardTransaction;

    /**
     * Record an approved operational funding, payout, or adjustment action.
     *
     * @throws Throwable
     */
    public function recordFinancialAction(int|string $giftCardId, array $data): GiftCardTransaction;

    /**
     * Reverse an immutable gift-card transaction exactly once.
     *
     * @throws Throwable
     * @throws JsonException
     */
    public function reverse(int|string $id, array $data): GiftCardTransaction;

    /**
     * Reverse a redemption or refund only from its owning Payment workflow.
     *
     * @throws Throwable
     * @throws JsonException
     */
    public function reversePaymentTransaction(int $paymentId, array $data): ?GiftCardTransaction;

    /**
     * Reverse a breakage transaction only from its owning batch workflow.
     *
     * @throws Throwable
     * @throws JsonException
     */
    public function reverseBreakageTransaction(int|string $id, array $data): GiftCardTransaction;

    /**
     * Expire all eligible gift cards and return the affected count.
     */
    public function expireEligibleCards(): int;

    /**
     * Shared table query used by pagination and export.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>|null  $sorts
     * @param  array<string, mixed>  $context
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;

    /**
     * Get the fully qualified gift card transaction model class name.
     *
     * @return class-string<GiftCardTransaction>
     */
    public function model(): string;

    /**
     * Create a new gift card transaction model instance.
     */
    public function getModel(): GiftCardTransaction;
}
