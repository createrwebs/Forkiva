<?php

namespace Modules\GiftCard\Services\GiftCardBreakage;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardBreakageBatch;
use Throwable;

interface GiftCardBreakageServiceInterface extends TableExportSourceInterface
{
    /**
     * Get paginated breakage batches filtered for the administrative listing.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array{key:string,order:string}>|null  $sorts
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Find one breakage batch and its selected card lines.
     */
    public function show(int $id): GiftCardBreakageBatch;

    /**
     * Get static form metadata or eligible cards for one selected branch.
     *
     * @return array{branches: mixed}|array{eligible_cards: Collection<int, GiftCard>}
     */
    public function getFormMeta(?int $branchId = null): array;

    /**
     * Create an idempotent draft batch with frozen selected card snapshots.
     *
     * @param  array{
     *     branch_id:int,
     *     gift_card_ids:array<int, int>,
     *     economic_date:string,
     *     idempotency_key:string,
     *     review_note:string
     * }  $data
     *
     * @throws Throwable
     */
    public function store(array $data): GiftCardBreakageBatch;

    /**
     * Revalidate and freeze a draft batch for posting.
     *
     * @param  array{economic_date:string, review_note:string}  $data
     *
     * @throws Throwable
     */
    public function review(int $id, array $data): GiftCardBreakageBatch;

    /**
     * Post a reviewed batch to operational and accounting ledgers.
     *
     * @throws Throwable
     */
    public function post(int $id): GiftCardBreakageBatch;

    /**
     * Create the one allowed append-only reversal of a posted batch.
     *
     * @param  array{economic_date:string, idempotency_key:string, reason:string}  $data
     *
     * @throws Throwable
     */
    public function reverse(int $id, array $data): GiftCardBreakageBatch;
}
