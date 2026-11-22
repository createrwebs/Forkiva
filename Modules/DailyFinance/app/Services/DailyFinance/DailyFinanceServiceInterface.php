<?php

namespace Modules\DailyFinance\Services\DailyFinance;

use Illuminate\Pagination\LengthAwarePaginator;
use JsonException;

interface DailyFinanceServiceInterface
{
    /**
     * Read one branch's daily activity, retaining each source's permission and date basis.
     *
     * @param  array{branch_id?: int|null, date?: string|null}  $filters
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function summary(array $filters = []): array;

    /**
     * Read source records for the selected day, enforcing the source permission and branch scope.
     *
     * @param  array{branch_id: int, date: string, kind: string, page?: int}  $filters
     */
    public function entries(array $filters): LengthAwarePaginator;
}
