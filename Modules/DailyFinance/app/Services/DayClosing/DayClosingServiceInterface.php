<?php

namespace Modules\DailyFinance\Services\DayClosing;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\DailyFinance\Models\DayClosing;
use Throwable;

interface DayClosingServiceInterface
{
    /**
     * Review a branch calendar day with source permissions, readiness and stale-review protection.
     *
     * @param  array{branch_id: int, date: string}  $filters
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    public function review(array $filters): array;

    /**
     * Save one immutable snapshot per day; identical retries return the first accepted record.
     *
     * @param  array{branch_id: int, date: string, request_key: string, review_hash: string, notes?: string|null}  $data
     *
     * @throws Throwable
     */
    public function close(array $data): DayClosing;

    /**
     * Return branch-scoped closing history using the shared pagination policy.
     *
     * @param  array{branch_id: int, page?: int}  $filters
     */
    public function history(array $filters): LengthAwarePaginator;
}
