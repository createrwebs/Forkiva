<?php

namespace Modules\GiftCard\Services\GiftCardAnalytics;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JsonException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Currency\Currency;
use Modules\Currency\Models\CurrencyRate;
use Modules\GiftCard\Enums\GiftCardAnalyticsWidget;
use Modules\GiftCard\Enums\GiftCardFundingType;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardBatch;
use Modules\GiftCard\Models\GiftCardTransaction;
use Modules\GiftCard\Support\GiftCardCurrencyRate;
use Modules\Support\DateRange;
use Modules\Support\Enums\Day;
use Modules\Support\GlobalStructureFilters;
use Modules\Support\Money;

class GiftCardAnalyticsService implements GiftCardAnalyticsServiceInterface
{
    private const CACHE_TTL_SECONDS = 120;

    /** @var array<string, mixed> */
    private array $requestMemo = [];

    public function __construct(protected BranchAccessServiceInterface $branchAccessService) {}

    /** {@inheritDoc} */
    public function section(string $section, array $filters = []): array
    {
        $this->requestMemo = [];
        $filters = $this->applyDefaultFilters($filters);

        if (! in_array($section, $this->sectionNames(), true)) {
            return $this->analytics($filters);
        }

        return $this->cachedSection($section, $filters);
    }

    /**
     * Return the supported analytics section names.
     *
     * @return array<int, string>
     */
    protected function sectionNames(): array
    {
        return [
            'overview',
            'cards',
            'transactions',
            'batches',
            'branches',
            'expiry',
            'sales_over_time',
            'redemption_over_time',
            'sales_by_branch',
            'liability_by_currency',
            'usage_by_day_of_week',
        ];
    }

    /**
     * Calculate and cache one analytics section.
     *
     * @param  array<string,mixed>  $filters
     */
    protected function cachedSection(string $section, array $filters): array
    {
        $includePeriod = ! in_array($section, ['cards', 'expiry', 'liability_by_currency'], true);

        return $this->remember($section, $filters, fn (): array => match ($section) {
            'overview' => $this->overview($filters),
            'cards' => $this->cards($filters),
            'transactions' => $this->transactions($filters),
            'batches' => $this->batches($filters),
            'branches' => $this->branches($filters),
            'expiry' => $this->expiry($filters),
            'sales_over_time' => $this->salesOverTime($filters),
            'redemption_over_time' => $this->redemptionOverTime($filters),
            'sales_by_branch' => $this->salesByBranch($filters),
            'liability_by_currency' => $this->liabilityByCurrency($filters),
            'usage_by_day_of_week' => $this->usageByDayOfWeek($filters),
            default => [],
        }, $includePeriod);
    }

    /**
     * Normalize filters before building analytics queries.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function applyDefaultFilters(array $filters): array
    {
        $branchId = $this->branchAccessService->resolve(
            ! empty($filters['branch_id']) ? (int) $filters['branch_id'] : null
        );
        $currency = $this->resolveSelectedCurrency($branchId, $filters);
        $today = CarbonImmutable::today();
        $from = CarbonImmutable::parse(
            $filters['from'] ?? $today->startOfYear()->toDateString()
        )->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? $today->toDateString())->endOfDay();

        return [
            ...$filters,
            'branch_id' => $branchId,
            'currency' => $currency,
            'currency_precision' => Currency::subunit($currency),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'refresh' => (bool) ($filters['refresh'] ?? false),
        ];
    }

    /**
     * Resolve the analytics currency from branch or explicit filter.
     *
     * @param  array<string,mixed>  $filters
     */
    protected function resolveSelectedCurrency(?int $branchId, array $filters): string
    {
        if ($branchId) {
            return (string) (Branch::query()
                ->withoutGlobalActive()
                ->whereKey($branchId)
                ->value('currency') ?: setting('default_currency'));
        }

        return (string) (($filters['currency'] ?? null) ?: setting('default_currency', 'JOD'));
    }

    /**
     * Build overview KPI data.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function overview(array $filters): array
    {
        $periodCards = (clone $this->cardQuery($filters))->count();
        $today = Carbon::today();
        $currentCardRows = (clone $this->currentCardQuery($filters))
            ->select('currency')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_cards', [GiftCardStatus::Active->value])
            ->selectRaw('SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as assigned_cards')
            ->selectRaw('SUM(current_balance) as total_amount_raw')
            ->selectRaw(
                'SUM(CASE WHEN expiry_date >= ? AND expiry_date < ? THEN 1 ELSE 0 END) as expiring_soon_cards',
                [DateRange::start($today), DateRange::endExclusive($today->copy()->addDays(30))],
            )
            ->groupBy('currency')
            ->get();

        $reversedExpression = 'CASE WHEN reversed_at IS NULL THEN 0 ELSE 1 END';
        $transactionRows = (clone $this->transactionQuery($filters))
            ->select(['type', 'funding_type', 'currency', 'currency_rate'])
            ->selectRaw("$reversedExpression as is_reversed")
            ->selectRaw('COUNT(*) as transactions_count')
            ->selectRaw('SUM(amount) as total_amount_raw')
            ->groupBy('type', 'funding_type', 'currency', 'currency_rate')
            ->groupByRaw($reversedExpression)
            ->get();
        $totalTransactions = $transactionRows->sum(fn ($row): int => (int) $row->transactions_count);
        $unreversedTransactionRows = $transactionRows->filter(
            fn ($row): bool => (int) $row->is_reversed === 0,
        );
        $soldValue = $this->sumTransactionRows(
            $unreversedTransactionRows->filter(fn ($row) => in_array($row->type, [
                GiftCardTransactionType::Purchase,
                GiftCardTransactionType::IssueVoid,
            ], true) && $row->funding_type === GiftCardFundingType::Paid->value),
            $filters,
            fn ($row): int => $row->type === GiftCardTransactionType::IssueVoid ? -1 : 1,
        );
        $redeemedValue = $this->sumTransactionRows(
            $unreversedTransactionRows->where('type', GiftCardTransactionType::Redeem),
            $filters,
        );
        $refundedValue = $this->sumTransactionRows(
            $unreversedTransactionRows->where('type', GiftCardTransactionType::Refund),
            $filters,
        );

        $batchRows = (clone $this->batchQuery($filters))
            ->select('currency')
            ->selectRaw('COUNT(*) as total_batches')
            ->selectRaw('SUM(quantity) as total_batch_quantity')
            ->selectRaw('SUM(quantity * value) as total_amount_raw')
            ->groupBy('currency')
            ->get();

        return [
            'currency' => $filters['currency'],
            'currency_precision' => $filters['currency_precision'],
            'total_cards' => $periodCards,
            'active_cards' => $currentCardRows->sum(fn ($row): int => (int) $row->active_cards),
            'assigned_cards' => $currentCardRows->sum(fn ($row): int => (int) $row->assigned_cards),
            'total_transactions' => $totalTransactions,
            'total_batches' => $batchRows->sum(fn ($row): int => (int) $row->total_batches),
            'total_batch_quantity' => $batchRows->sum(fn ($row): int => (int) $row->total_batch_quantity),
            'expiring_soon_cards' => $currentCardRows->sum(fn ($row): int => (int) $row->expiring_soon_cards),
            'outstanding_balance' => $this->money($this->sumCurrentValueRows($currentCardRows, $filters), $filters),
            'sold_value' => $this->money($soldValue, $filters),
            'redeemed_value' => $this->money($redeemedValue, $filters),
            'refunded_value' => $this->money($refundedValue, $filters),
            'batch_face_value' => $this->money($this->sumCurrentValueRows($batchRows, $filters), $filters),
        ];
    }

    /**
     * Base card query for analytics.
     *
     * @param  array<string,mixed>  $filters
     * @return Builder<GiftCard>
     */
    protected function cardQuery(array $filters): Builder
    {
        return $this->currentCardQuery($filters)
            ->when(! empty($filters['from']), fn (Builder $query) => $query->where('gift_cards.created_at', '>=', DateRange::start($filters['from'])))
            ->when(! empty($filters['to']), fn (Builder $query) => $query->where('gift_cards.created_at', '<', DateRange::endExclusive($filters['to'])));
    }

    /**
     * Build the current card population for point-in-time widgets.
     *
     * @param  array<string,mixed>  $filters
     * @return Builder<GiftCard>
     */
    protected function currentCardQuery(array $filters): Builder
    {
        return GiftCard::query()
            ->when(! empty($filters['branch_id']), fn (Builder $query) => $query->where('gift_cards.branch_id', (int) $filters['branch_id']));
    }

    /**
     * Base transaction query for analytics.
     *
     * @param  array<string,mixed>  $filters
     * @return Builder<GiftCardTransaction>
     */
    protected function transactionQuery(array $filters): Builder
    {
        return GiftCardTransaction::query()
            ->whereHas('giftCard')
            ->when(! empty($filters['branch_id']), fn (Builder $query) => $query->where('gift_card_transactions.branch_id', (int) $filters['branch_id']))
            ->when(! empty($filters['from']), fn (Builder $query) => $query->where('gift_card_transactions.transaction_at', '>=', DateRange::start($filters['from'])))
            ->when(! empty($filters['to']), fn (Builder $query) => $query->where('gift_card_transactions.transaction_at', '<', DateRange::endExclusive($filters['to'])));
    }

    /**
     * Sum frozen ledger amounts in the selected display currency.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<string,mixed>  $filters
     */
    protected function sumTransactionRows(
        Collection $rows,
        array $filters,
        ?callable $direction = null,
    ): float {
        $total = BigDecimal::zero();

        foreach ($rows as $row) {
            $amount = $this->convertToDisplayCurrency(
                (string) ($row->total_amount_raw ?? 0),
                (string) $row->currency,
                $filters,
                $row->currency_rate,
            );
            $total = $total->plus($amount->multipliedBy($direction ? $direction($row) : 1));
        }

        return (float) (string) $total->toScale(6, RoundingMode::HalfUp);
    }

    /**
     * Convert a native amount to the dashboard display currency.
     *
     * Ledger rows use their immutable source-to-base rate. Snapshot values,
     * which have no frozen rate, use the current configured currency rate.
     *
     * @param  array<string,mixed>  $filters
     */
    protected function convertToDisplayCurrency(
        string $amount,
        string $sourceCurrency,
        array $filters,
        mixed $frozenSourceToBaseRate = null,
    ): BigDecimal {
        $value = BigDecimal::of($amount);
        $sourceCurrency = strtoupper($sourceCurrency);
        $displayCurrency = strtoupper((string) $filters['currency']);
        if ($sourceCurrency === $displayCurrency) {
            return $value;
        }

        $defaultCurrency = strtoupper((string) setting('default_currency', 'JOD'));
        $sourceToBaseRate = $sourceCurrency === $defaultCurrency
            ? BigDecimal::one()
            : BigDecimal::of((string) ($frozenSourceToBaseRate ?: GiftCardCurrencyRate::toDefault($sourceCurrency)));
        $baseValue = $value->multipliedBy($sourceToBaseRate);

        if ($displayCurrency === $defaultCurrency) {
            return $baseValue;
        }

        return $baseValue->multipliedBy((string) CurrencyRate::for($displayCurrency));
    }

    /**
     * Base batch query for analytics.
     *
     * @param  array<string,mixed>  $filters
     * @return Builder<GiftCardBatch>
     */
    protected function batchQuery(array $filters): Builder
    {
        return GiftCardBatch::query()
            ->when(! empty($filters['branch_id']), fn (Builder $query) => $query->where('gift_card_batches.branch_id', (int) $filters['branch_id']))
            ->when(! empty($filters['from']), fn (Builder $query) => $query->where('gift_card_batches.created_at', '>=', DateRange::start($filters['from'])))
            ->when(! empty($filters['to']), fn (Builder $query) => $query->where('gift_card_batches.created_at', '<', DateRange::endExclusive($filters['to'])));
    }

    /**
     * Create a rounded money value in the selected display currency.
     *
     * @param  array<string,mixed>  $filters
     */
    protected function money(float $amount, array $filters): Money
    {
        return (new Money($amount, $filters['currency']))->round();
    }

    /**
     * Sum current card or batch values after converting each native currency.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<string,mixed>  $filters
     */
    protected function sumCurrentValueRows(Collection $rows, array $filters): float
    {
        $total = BigDecimal::zero();

        foreach ($rows as $row) {
            $total = $total->plus($this->convertToDisplayCurrency(
                (string) ($row->total_amount_raw ?? 0),
                (string) $row->currency,
                $filters,
            ));
        }

        return (float) (string) $total->toScale(6, RoundingMode::HalfUp);
    }

    /**
     * Rank native-currency values in SQL using their comparable display value.
     *
     * @param  Collection<int, string>  $currencies
     * @param  array<string,mixed>  $filters
     */
    protected function orderByConvertedValue(
        Builder $query,
        string $amountExpression,
        string $currencyColumn,
        Collection $currencies,
        array $filters,
    ): void {
        $currencies = $currencies
            ->map(fn (string $currency): string => strtoupper($currency))
            ->unique()
            ->values();

        if ($currencies->isEmpty()) {
            $query->orderByDesc(DB::raw($amountExpression));

            return;
        }

        $cases = [];
        $bindings = [];
        foreach ($currencies as $currency) {
            $cases[] = 'WHEN ? THEN CAST(? AS DECIMAL(30, 12))';
            $bindings[] = $currency;
            $bindings[] = (string) $this->convertToDisplayCurrency('1', $currency, $filters);
        }

        $query->orderByRaw(
            "($amountExpression) * (CASE $currencyColumn ".implode(' ', $cases).' ELSE 0 END) DESC',
            $bindings,
        );
    }

    /**
     * Build card-centric analytics.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function cards(array $filters): array
    {
        $baseQuery = $this->currentCardQuery($filters);

        $mixRows = (clone $baseQuery)
            ->select(['status', 'scope', 'currency'])
            ->selectRaw('COUNT(*) as cards_count')
            ->groupBy('status', 'scope', 'currency')
            ->get();
        $statusMix = $mixRows
            ->groupBy(fn ($row): string => $row->status->value)
            ->map(function (Collection $rows): array {
                $status = $rows->first()->status;

                return [
                    'key' => $status,
                    'label' => $status->trans(),
                    'count' => $rows->sum(fn ($row): int => (int) $row->cards_count),
                ];
            })
            ->values();
        $scopeMix = $mixRows
            ->groupBy(fn ($row): string => $row->scope->value)
            ->map(function (Collection $rows): array {
                $scope = $rows->first()->scope;

                return [
                    'key' => $scope,
                    'label' => $scope->trans(),
                    'count' => $rows->sum(fn ($row): int => (int) $row->cards_count),
                ];
            })
            ->values();

        $topBalancesQuery = (clone $baseQuery)->with(['branch:id,name', 'customer:id,name']);
        $this->orderByConvertedValue(
            $topBalancesQuery,
            'gift_cards.current_balance',
            'gift_cards.currency',
            $mixRows->pluck('currency'),
            $filters,
        );
        $topBalances = $topBalancesQuery
            ->limit(6)
            ->get()
            ->map(fn (GiftCard $giftCard) => [
                'id' => $giftCard->id,
                'code' => $giftCard->code,
                'branch' => $giftCard->branch?->name,
                'customer' => $giftCard->customer?->name,
                'status' => $giftCard->status->value,
                'status_label' => $giftCard->status->toTrans(),
                'scope' => $giftCard->scope->value,
                'scope_label' => $giftCard->scope->toTrans(),
                'current_balance' => $giftCard->current_balance,
            ]);

        return [
            'status_mix' => $statusMix,
            'scope_mix' => $scopeMix,
            'top_balances' => $topBalances,
        ];
    }

    /**
     * Build transaction analytics.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function transactions(array $filters): array
    {
        $baseQuery = $this->transactionQuery($filters);

        $aggregateRows = (clone $baseQuery)
            ->selectRaw('DATE(transaction_at) as day')
            ->addSelect(['type', 'currency', 'currency_rate'])
            ->selectRaw('COUNT(*) as transactions_count')
            ->selectRaw('SUM(amount) as total_amount_raw')
            ->groupBy('day', 'type', 'currency', 'currency_rate')
            ->orderBy('day')
            ->get();
        $typeBreakdown = $aggregateRows
            ->groupBy(fn ($row): string => $row->type->value)
            ->map(function (Collection $rows) use ($filters): array {
                $type = $rows->first()->type;

                return [
                    'key' => $type,
                    'label' => $type->trans(),
                    'count' => $rows->sum(fn ($row): int => (int) $row->transactions_count),
                    'amount' => $this->money($this->sumTransactionRows($rows, $filters), $filters),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $dailyTrend = $aggregateRows
            ->groupBy('day')
            ->map(fn (Collection $rows, string $day): array => [
                'day' => $day,
                'count' => $rows->sum(fn ($row): int => (int) $row->transactions_count),
                'amount' => $this->money($this->sumTransactionRows($rows, $filters), $filters),
            ])
            ->values();

        $recentTransactions = (clone $baseQuery)
            ->with(['giftCard:id,code', 'branch:id,name'])
            ->latest('transaction_at')
            ->limit(8)
            ->get()
            ->map(fn (GiftCardTransaction $transaction) => [
                'id' => $transaction->id,
                'uuid' => $transaction->uuid,
                'code' => $transaction->giftCard?->code,
                'type' => $transaction->type->value,
                'type_label' => $transaction->type->toTrans(),
                'amount' => $transaction->amount,
                'branch' => $transaction->branch?->name,
                'transaction_at' => $transaction->transaction_at?->format('Y-m-d H:i'),
            ]);

        return [
            'type_breakdown' => $typeBreakdown,
            'daily_trend' => $dailyTrend,
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Build batch analytics.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function batches(array $filters): array
    {
        $valueRows = (clone $this->batchQuery($filters))
            ->select('currency')
            ->selectRaw('COUNT(*) as total_batches')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(quantity * value) as total_amount_raw')
            ->groupBy('currency')
            ->get();

        $topBatchesQuery = $this->batchQuery($filters)
            ->with('branch:id,name')
            ->withCount('cards as cards_generated')
            ->withCount([
                'cards as cards_used' => fn (Builder $query) => $query->where('status', GiftCardStatus::Used->value),
            ]);
        $this->orderByConvertedValue(
            $topBatchesQuery,
            'gift_card_batches.quantity * gift_card_batches.value',
            'gift_card_batches.currency',
            $valueRows->pluck('currency'),
            $filters,
        );
        $topBatches = $topBatchesQuery
            ->limit(6)
            ->get()
            ->map(fn (GiftCardBatch $batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
                'branch' => $batch->branch?->name,
                'quantity' => $batch->quantity,
                'value' => $batch->value,
                'cards_generated' => (int) $batch->cards_generated,
                'cards_used' => (int) $batch->cards_used,
                'cards_remaining' => max((int) $batch->cards_generated - (int) $batch->cards_used, 0),
            ]);

        return [
            'summary' => [
                'total_batches' => $valueRows->sum(fn ($row): int => (int) $row->total_batches),
                'total_quantity' => $valueRows->sum(fn ($row): int => (int) $row->total_quantity),
                'total_value' => $this->money($this->sumCurrentValueRows($valueRows, $filters), $filters),
            ],
            'top_batches' => $topBatches,
        ];
    }

    /**
     * Build branch comparison analytics.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function branches(array $filters): array
    {
        $cardRows = (clone $this->currentCardQuery($filters))
            ->select(['gift_cards.branch_id', 'gift_cards.currency'])
            ->selectRaw('COUNT(gift_cards.id) as cards_count')
            ->selectRaw('SUM(gift_cards.current_balance) as outstanding_balance_raw')
            ->groupBy('gift_cards.branch_id', 'gift_cards.currency')
            ->get();
        $transactionRows = (clone $this->transactionQuery($filters))
            ->whereNull('reversed_at')
            ->whereIn('type', [
                GiftCardTransactionType::Purchase->value,
                GiftCardTransactionType::IssueVoid->value,
                GiftCardTransactionType::Redeem->value,
            ])
            ->select(['branch_id', 'type', 'funding_type', 'currency', 'currency_rate'])
            ->selectRaw('SUM(amount) as total_amount_raw')
            ->groupBy('branch_id', 'type', 'funding_type', 'currency', 'currency_rate')
            ->get();
        $branchNames = $this->localizedBranchNames($cardRows);

        return [
            'highlights' => $this->buildBranchHighlights($cardRows, $transactionRows, $branchNames, $filters),
        ];
    }

    /**
     * Resolve translated branch names without exposing their JSON storage representation.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, string>
     */
    protected function localizedBranchNames(Collection $rows): Collection
    {
        $branchIds = $rows
            ->pluck('branch_id')
            ->filter(fn ($branchId) => $branchId !== null)
            ->map(fn ($branchId) => (int) $branchId)
            ->unique()
            ->values();

        return Branch::query()
            ->withoutGlobalActive()
            ->whereKey($branchIds)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Branch $branch) => [$branch->id => (string) $branch->name]);
    }

    /**
     * Combine native-currency card snapshots with period ledger activity by branch.
     *
     * @param  Collection<int, object>  $cardRows
     * @param  Collection<int, object>  $transactionRows
     * @param  Collection<int, string>  $branchNames
     * @param  array<string,mixed>  $filters
     */
    protected function buildBranchHighlights(
        Collection $cardRows,
        Collection $transactionRows,
        Collection $branchNames,
        array $filters,
    ): Collection {
        $transactionsByBranch = $transactionRows->groupBy(
            fn ($row): string => is_null($row->branch_id) ? 'global' : (string) $row->branch_id,
        );

        return $cardRows
            ->groupBy(fn ($row): string => is_null($row->branch_id) ? 'global' : (string) $row->branch_id)
            ->map(function (Collection $rows, string|int $branchKey) use ($transactionsByBranch, $branchNames, $filters): array {
                $branchId = $rows->first()->branch_id;
                $branchTransactions = $transactionsByBranch->get($branchKey, collect());
                $salesRows = $branchTransactions->filter(fn ($row): bool => in_array($row->type, [
                    GiftCardTransactionType::Purchase,
                    GiftCardTransactionType::IssueVoid,
                ], true) && $row->funding_type === GiftCardFundingType::Paid->value);
                $outstandingRows = $rows->map(fn ($row): object => (object) [
                    'currency' => $row->currency,
                    'total_amount_raw' => $row->outstanding_balance_raw,
                ]);

                return [
                    'branch_id' => $branchId,
                    'branch_name' => $this->localizedBranchName($branchId, $branchNames),
                    'cards_count' => $rows->sum(fn ($row): int => (int) $row->cards_count),
                    'outstanding_balance' => $this->money(
                        $this->sumCurrentValueRows($outstandingRows, $filters),
                        $filters,
                    ),
                    'sold_value' => $this->money($this->sumTransactionRows(
                        $salesRows,
                        $filters,
                        fn ($row): int => $row->type === GiftCardTransactionType::IssueVoid ? -1 : 1,
                    ), $filters),
                    'redeemed_value' => $this->money($this->sumTransactionRows(
                        $branchTransactions->where('type', GiftCardTransactionType::Redeem),
                        $filters,
                    ), $filters),
                ];
            })
            ->sortByDesc(fn (array $row): float => (float) $row['outstanding_balance']->amount())
            ->take(8)
            ->values();
    }

    /**
     * Return the localized branch label or the global-pool fallback.
     *
     * @param  Collection<int, string>  $branchNames
     */
    protected function localizedBranchName(mixed $branchId, Collection $branchNames): string
    {
        if ($branchId === null) {
            return __('giftcard::gift_cards.analytics.global_pool');
        }

        return $branchNames->get((int) $branchId)
            ?: __('giftcard::gift_cards.analytics.global_pool');
    }

    /**
     * Build expiry monitoring analytics.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function expiry(array $filters): array
    {
        $today = Carbon::today();
        $activeStatuses = [GiftCardStatus::Active->value, GiftCardStatus::Used->value];
        $summary = (clone $this->currentCardQuery($filters))
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?) AND expiry_date >= ? AND expiry_date < ? THEN 1 ELSE 0 END) as expiring_in_7_days',
                [...$activeStatuses, DateRange::start($today), DateRange::endExclusive($today->copy()->addDays(7))],
            )
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?) AND expiry_date >= ? AND expiry_date < ? THEN 1 ELSE 0 END) as expiring_in_30_days',
                [...$activeStatuses, DateRange::start($today), DateRange::endExclusive($today->copy()->addDays(30))],
            )
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as expired_cards', [GiftCardStatus::Expired->value])
            ->first();

        $baseQuery = $this->currentCardQuery($filters)
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('expiry_date');

        $cards = (clone $baseQuery)
            ->with(['branch:id,name', 'customer:id,name'])
            ->where('expiry_date', '>=', DateRange::start(Carbon::today()))
            ->where('expiry_date', '<', DateRange::endExclusive(Carbon::today()->addDays(30)))
            ->orderBy('expiry_date')
            ->limit(8)
            ->get()
            ->map(fn (GiftCard $giftCard) => [
                'id' => $giftCard->id,
                'code' => $giftCard->code,
                'branch' => $giftCard->branch?->name,
                'customer' => $giftCard->customer?->name,
                'expiry_date' => $giftCard->expiry_date?->toDateString(),
                'status' => $giftCard->status->value,
                'status_label' => $giftCard->status->toTrans(),
                'current_balance' => $giftCard->current_balance,
            ]);

        return [
            'summary' => [
                'expiring_in_7_days' => (int) ($summary?->expiring_in_7_days ?? 0),
                'expiring_in_30_days' => (int) ($summary?->expiring_in_30_days ?? 0),
                'expired_cards' => (int) ($summary?->expired_cards ?? 0),
            ],
            'cards' => $cards,
        ];
    }

    /**
     * Build gift card sales over time line-chart data.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function salesOverTime(array $filters): array
    {
        return $this->buildTransactionTimeSeries($filters, GiftCardTransactionType::Purchase);
    }

    /**
     * Build transaction time-series chart data.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function buildTransactionTimeSeries(array $filters, GiftCardTransactionType $type): array
    {
        $query = $type === GiftCardTransactionType::Purchase
            ? $this->salesTransactionQuery($filters)
            : (clone $this->transactionQuery($filters))
                ->whereNull('reversed_at')
                ->where('type', $type->value);
        $rows = $query
            ->selectRaw('DATE(transaction_at) as day')
            ->addSelect(['type', 'currency', 'currency_rate'])
            ->selectRaw('SUM(amount) as total_amount_raw')
            ->groupBy('day', 'type', 'currency', 'currency_rate')
            ->orderBy('day')
            ->get()
            ->groupBy('day');

        $period = collect(Carbon::parse($filters['from'])->daysUntil(Carbon::parse($filters['to'])->addDay()))
            ->map(fn (Carbon $date) => $date->toDateString());

        return [
            'labels' => $period->map(fn (string $day) => Carbon::parse($day)->format('M d')),
            'series' => $period->map(function (string $day) use ($rows, $filters, $type): float {
                $dayRows = $rows->get($day, collect());
                $amount = $this->sumTransactionRows(
                    $dayRows,
                    $filters,
                    $type === GiftCardTransactionType::Purchase
                        ? fn ($row): int => $row->type === GiftCardTransactionType::IssueVoid ? -1 : 1
                        : null,
                );

                return round($amount, $filters['currency_precision']);
            }),
            'currency' => $filters['currency'],
            'precision' => $filters['currency_precision'],
        ];
    }

    /**
     * Net paid gift card issue activity for sales widgets.
     *
     * @param  array<string,mixed>  $filters
     * @return Builder<GiftCardTransaction>
     */
    protected function salesTransactionQuery(array $filters): Builder
    {
        return (clone $this->transactionQuery($filters))
            ->whereNull('reversed_at')
            ->where('funding_type', GiftCardFundingType::Paid->value)
            ->whereIn('type', [
                GiftCardTransactionType::Purchase->value,
                GiftCardTransactionType::IssueVoid->value,
            ]);
    }

    /**
     * Build gift card redemption over time line-chart data.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function redemptionOverTime(array $filters): array
    {
        return $this->buildTransactionTimeSeries($filters, GiftCardTransactionType::Redeem);
    }

    /**
     * Build gift card sales by branch bar-chart data.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function salesByBranch(array $filters): array
    {
        $rows = $this->salesTransactionQuery($filters)
            ->select(['branch_id', 'type', 'currency', 'currency_rate'])
            ->selectRaw('SUM(amount) as total_amount_raw')
            ->groupBy('branch_id', 'type', 'currency', 'currency_rate')
            ->get();
        $branchNames = $this->localizedBranchNames($rows);
        $branches = $rows
            ->groupBy(fn ($row): string => is_null($row->branch_id) ? 'global' : (string) $row->branch_id)
            ->map(fn (Collection $branchRows): array => [
                'branch_id' => $branchRows->first()->branch_id,
                'amount' => $this->sumTransactionRows(
                    $branchRows,
                    $filters,
                    fn ($row): int => $row->type === GiftCardTransactionType::IssueVoid ? -1 : 1,
                ),
            ])
            ->filter(fn (array $row): bool => (float) $row['amount'] !== 0.0)
            ->sortByDesc('amount')
            ->take(8)
            ->values();

        return [
            'labels' => $branches->map(fn (array $row) => $this->localizedBranchName($row['branch_id'], $branchNames)),
            'series' => $branches->map(fn (array $row) => round($row['amount'], $filters['currency_precision'])),
            'currency' => $filters['currency'],
            'precision' => $filters['currency_precision'],
        ];
    }

    /**
     * Build outstanding liability by currency donut-chart data.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function liabilityByCurrency(array $filters): array
    {
        $rows = (clone $this->currentCardQuery($filters))
            ->select('currency')
            ->selectRaw('SUM(current_balance) as total_amount_raw')
            ->groupBy('currency')
            ->get();
        $liabilities = $rows
            ->map(fn ($row): array => [
                'currency' => (string) $row->currency,
                'amount' => (float) (string) $this->convertToDisplayCurrency(
                    (string) $row->total_amount_raw,
                    (string) $row->currency,
                    $filters,
                ),
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'labels' => $liabilities->pluck('currency'),
            'series' => $liabilities->map(fn (array $row) => round(
                $row['amount'],
                $filters['currency_precision'],
            )),
            'currency' => $filters['currency'],
            'precision' => $filters['currency_precision'],
        ];
    }

    /**
     * Build day-of-week usage bar-chart data from redemption transactions.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    protected function usageByDayOfWeek(array $filters): array
    {
        $driver = DB::connection()->getDriverName();
        $weekDayExpression = match ($driver) {
            'pgsql' => 'EXTRACT(DOW FROM transaction_at) + 1',
            'sqlite' => "CAST(strftime('%w', transaction_at) AS INTEGER) + 1",
            default => 'DAYOFWEEK(transaction_at)',
        };

        $rows = (clone $this->transactionQuery($filters))
            ->whereNull('reversed_at')
            ->where('type', GiftCardTransactionType::Redeem->value)
            ->selectRaw("$weekDayExpression as week_day")
            ->selectRaw('COUNT(*) as usage_count')
            ->groupBy('week_day')
            ->get()
            ->keyBy('week_day');

        $weekDays = collect([
            1 => __('giftcard::gift_cards.analytics.days.sunday'),
            2 => __('giftcard::gift_cards.analytics.days.monday'),
            3 => __('giftcard::gift_cards.analytics.days.tuesday'),
            4 => __('giftcard::gift_cards.analytics.days.wednesday'),
            5 => __('giftcard::gift_cards.analytics.days.thursday'),
            6 => __('giftcard::gift_cards.analytics.days.friday'),
            7 => __('giftcard::gift_cards.analytics.days.saturday'),
        ]);

        $dayIndexes = [
            Day::Sunday->value => 1,
            Day::Monday->value => 2,
            Day::Tuesday->value => 3,
            Day::Wednesday->value => 4,
            Day::Thursday->value => 5,
            Day::Friday->value => 6,
            Day::Saturday->value => 7,
        ];

        $orderedDayKeys = collect(array_keys($dayIndexes));
        $startDay = (string) setting('start_of_week', Day::Sunday->value);
        $endDay = (string) setting('end_of_week', Day::Saturday->value);
        $startIndex = $orderedDayKeys->search($startDay);
        $endIndex = $orderedDayKeys->search($endDay);

        if ($startIndex === false) {
            $startIndex = 0;
        }

        if ($endIndex === false) {
            $endIndex = $orderedDayKeys->count() - 1;
        }

        $orderedDayKeys = $orderedDayKeys
            ->slice($startIndex)
            ->concat($orderedDayKeys->take($startIndex));

        if ($orderedDayKeys->contains($endDay)) {
            $endPosition = $orderedDayKeys->search($endDay);
            $orderedDayKeys = $orderedDayKeys
                ->take($endPosition + 1)
                ->concat($orderedDayKeys->slice($endPosition + 1));
        }

        return [
            'labels' => $orderedDayKeys->map(fn (string $day) => $weekDays->get($dayIndexes[$day]))->values()->all(),
            'series' => $orderedDayKeys->map(fn (string $day) => (int) ($rows->get($dayIndexes[$day])?->usage_count ?? 0))->values()->all(),
        ];
    }

    /**
     * Cache a normalized analytics section and prevent cache stampedes.
     *
     * @param  array<string,mixed>  $filters
     *
     * @throws JsonException
     */
    protected function remember(
        string $section,
        array $filters,
        callable $callback,
        bool $includePeriod = true,
    ): mixed {
        $cacheFilters = [
            'branch_id' => $filters['branch_id'],
            'currency' => $filters['currency'],
            'locale' => app()->getLocale(),
            'as_of' => CarbonImmutable::today()->toDateString(),
        ];

        if ($includePeriod) {
            $cacheFilters = [
                ...$cacheFilters,
                'from' => $filters['from'],
                'to' => $filters['to'],
            ];
        }

        ksort($cacheFilters);
        $key = 'gift-card-analytics:v2:'.$section.':'.hash(
            'sha256',
            json_encode($cacheFilters, JSON_THROW_ON_ERROR),
        );

        if (array_key_exists($key, $this->requestMemo)) {
            return $this->requestMemo[$key];
        }

        if (! $filters['refresh'] && Cache::has($key)) {
            return $this->requestMemo[$key] = Cache::get($key);
        }

        try {
            $value = Cache::lock($key.':lock', 15)->block(5, function () use ($callback, $filters, $key): mixed {
                if (! $filters['refresh'] && Cache::has($key)) {
                    return Cache::get($key);
                }

                $value = $callback();
                Cache::put($key, $value, self::CACHE_TTL_SECONDS);

                return $value;
            });
        } catch (LockTimeoutException) {
            $value = Cache::has($key) ? Cache::get($key) : $callback();
        }

        return $this->requestMemo[$key] = $value;
    }

    /** {@inheritDoc} */
    public function analytics(array $filters = []): array
    {
        $this->requestMemo = [];
        $normalized = $this->applyDefaultFilters($filters);
        $widgets = collect($filters['widgets'] ?? GiftCardAnalyticsWidget::defaultValues())
            ->map(fn (GiftCardAnalyticsWidget|string $widget) => $widget instanceof GiftCardAnalyticsWidget
                ? $widget
                : GiftCardAnalyticsWidget::from($widget));

        return [
            'meta' => [
                'branch_id' => $normalized['branch_id'],
                'branch_locked' => $this->branchAccessService->assignedBranchId() !== null,
                'from' => $normalized['from'],
                'to' => $normalized['to'],
                'display_currency' => $normalized['currency'],
                'timezone' => (string) config('app.timezone'),
                'generated_at' => dateTimeFormat(now()),
            ],
            'widgets' => $widgets
                ->mapWithKeys(fn (GiftCardAnalyticsWidget $widget) => [
                    $widget->value => $this->widget($widget, $normalized),
                ])
                ->all(),
        ];
    }

    /**
     * Wrap a requested widget in the shared dashboard authorization envelope.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function widget(GiftCardAnalyticsWidget $widget, array $filters): array
    {
        $authorized = auth()->user()?->can($widget->permission()) ?? false;

        return [
            'key' => $widget->value,
            'permission' => $widget->permission(),
            'authorized' => $authorized,
            'data' => $authorized ? $this->widgetData($widget, $filters) : null,
        ];
    }

    /**
     * Calculate one requested widget and reuse shared aggregates per request.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function widgetData(GiftCardAnalyticsWidget $widget, array $filters): array
    {
        if ($widget->isOverviewMetric()) {
            $overview = $this->cachedSection('overview', $filters);

            return ['value' => $overview[$widget->value]];
        }

        $section = match ($widget) {
            GiftCardAnalyticsWidget::CardsHealth => 'cards',
            GiftCardAnalyticsWidget::TransactionMix => 'transactions',
            GiftCardAnalyticsWidget::BatchActivity => 'batches',
            GiftCardAnalyticsWidget::BranchHighlights => 'branches',
            GiftCardAnalyticsWidget::ExpiryWatch => 'expiry',
            GiftCardAnalyticsWidget::SalesOverTime => 'sales_over_time',
            GiftCardAnalyticsWidget::RedemptionOverTime => 'redemption_over_time',
            GiftCardAnalyticsWidget::SalesByBranch => 'sales_by_branch',
            GiftCardAnalyticsWidget::LiabilityByCurrency => 'liability_by_currency',
            GiftCardAnalyticsWidget::UsageByDayOfWeek => 'usage_by_day_of_week',
            default => null,
        };

        return $section ? $this->cachedSection($section, $filters) : [];
    }

    /** {@inheritDoc} */
    public function filters(array $filters = []): array
    {
        $normalized = $this->applyDefaultFilters($filters);
        $branchFilter = GlobalStructureFilters::branch();
        $today = CarbonImmutable::today();
        $defaults = [
            'branch_id' => $normalized['branch_id'],
            'from' => $normalized['from'],
            'to' => $normalized['to'],
        ];

        return [
            'filters' => [
                ...(is_null($branchFilter) ? [] : [$branchFilter]),
                GlobalStructureFilters::from(__('giftcard::gift_cards.analytics.filters.from')),
                GlobalStructureFilters::to(__('giftcard::gift_cards.analytics.filters.to')),
            ],
            'presets' => [
                [
                    'id' => 'today',
                    'name' => __('giftcard::gift_cards.analytics.periods.today'),
                    'from' => $today->toDateString(),
                    'to' => $today->toDateString(),
                ],
                [
                    'id' => 'last_7_days',
                    'name' => __('giftcard::gift_cards.analytics.periods.last_7_days'),
                    'from' => $today->subDays(6)->toDateString(),
                    'to' => $today->toDateString(),
                ],
                [
                    'id' => 'this_month',
                    'name' => __('giftcard::gift_cards.analytics.periods.this_month'),
                    'from' => $today->startOfMonth()->toDateString(),
                    'to' => $today->toDateString(),
                ],
                [
                    'id' => 'this_year',
                    'name' => __('giftcard::gift_cards.analytics.periods.this_year'),
                    'from' => $today->startOfYear()->toDateString(),
                    'to' => $today->toDateString(),
                ],
            ],
            'defaults' => $defaults,
            // Keep the original key while older browser bundles roll forward.
            'default_filters' => $defaults,
            'meta' => [
                'branch_locked' => $this->branchAccessService->assignedBranchId() !== null,
                'max_range_days' => 367,
                'timezone' => (string) config('app.timezone'),
            ],
        ];
    }
}
