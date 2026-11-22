<?php

namespace Modules\DailyFinance\Services\DayClosing;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\DailyFinance\Enums\CashHandoverStatus;
use Modules\DailyFinance\Models\CashHandover;
use Modules\DailyFinance\Models\DayClosing;
use Modules\DailyFinance\Services\DailyFinance\DailyFinanceServiceInterface;
use Modules\Pos\Models\PosSession;

class DayClosingService implements DayClosingServiceInterface
{
    public function __construct(
        protected BranchAccessServiceInterface $branchAccess,
        protected DailyFinanceServiceInterface $finance,
    ) {}

    /** {@inheritDoc} */
    public function review(array $filters): array
    {
        return DB::transaction(function () use ($filters): array {
            $branch = $this->branch((int) $filters['branch_id']);

            $closing = DayClosing::query()
                ->where('branch_id', $branch->id)
                ->whereDate('date', $filters['date'])
                ->with('createdBy')
                ->first();

            $review = $closing
                ? $closing->snapshot
                : $this->snapshot($branch, $filters['date']);

            if (! auth()->user()->can('admin.accounting_reports.index')) {
                $review['summary']['expenses'] = null;
                $review['summary']['permissions']['expenses'] = false;
            }

            return [
                ...$review,
                'review_hash' => $closing?->review_hash ?? $this->hash($review),
                'can_close' => ! $closing
                    && $review['blockers'] === []
                    && auth()->user()->can('admin.daily_finance_closings.close'),
                'closing' => $closing,
            ];
        });
    }

    /**
     * Scope every review/history operation, including historical snapshots, to the authorized branch.
     */
    private function branch(int $id): Branch
    {
        $id = $this->branchAccess->resolve($id, true);

        return Branch::query()
            ->withoutGlobalActive()
            ->findOrFail($id);
    }

    /**
     * Reuse the live overview's exact source/date semantics without duplicating financial transactions.
     *
     * @throws JsonException
     */
    private function snapshot(Branch $branch, string $date): array
    {
        $summary = $this->finance->summary([
            'branch_id' => $branch->id,
            'date' => $date,
        ]);

        $summary['branches'] = array_values(
            array_filter(
                $summary['branches'],
                fn (array $row) => $row['id'] === $branch->id
            )
        );

        $rows = $summary['sessions']['rows'];

        $handovers = CashHandover::query()
            ->where('branch_id', $branch->id)
            ->whereIn('pos_session_id', array_column($rows, 'id'))
            ->orderBy('id')
            ->get([
                'id',
                'pos_session_id',
                'status',
                'amount',
                'received_amount',
            ]);

        $pending = $handovers
            ->where('status', CashHandoverStatus::Pending)
            ->count();

        $unknown = count(
            array_filter(
                $rows,
                fn (array $row) => $row['status'] === 'closed'
                    && ($row['expected'] === null || $row['counted'] === null)
            )
        );

        $checks = [
            'open_sessions' => $summary['sessions']['open_count'],
            'closed_sessions' => $summary['sessions']['closed_count'],
            'unknown_counts' => $unknown,
            'pending_handovers' => $pending,
            'session_variances' => count(
                array_filter(
                    $rows,
                    fn (array $row) => $row['variance'] !== null
                        && ! BigDecimal::of($row['variance'])->isZero()
                )
            ),
            'receipt_variances' => $handovers
                ->filter(
                    fn (CashHandover $row) => $row->received_amount !== null
                        && ! BigDecimal::of($row->received_amount)->isEqualTo($row->amount)
                )
                ->count(),
            'unpaid_orders' => ! BigDecimal::of($summary['sales']['dues'])->isZero() ? 1 : 0,
            'excluded_currencies' => array_sum(
                array_map(
                    fn (string $key) => $summary[$key]['excluded_currency_count'],
                    ['sales', 'payments', 'movements']
                )
            ),
            'future_day' => $date > $summary['meta']['today'] ? 1 : 0,
        ];

        $blockers = array_keys(
            array_filter(
                array_intersect_key(
                    $checks,
                    array_flip([
                        'open_sessions',
                        'unknown_counts',
                        'pending_handovers',
                        'future_day',
                    ])
                )
            )
        );

        $warnings = array_keys(
            array_filter(
                array_intersect_key(
                    $checks,
                    array_flip([
                        'session_variances',
                        'receipt_variances',
                        'unpaid_orders',
                        'excluded_currencies',
                    ])
                )
            )
        );

        // Money objects from the optional expense reader are normalized; source totals remain decimal strings.
        $summary = json_decode(
            json_encode($summary, JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $handoverRows = $handovers->map(fn (CashHandover $row) => [
            'id' => $row->id,
            'session_id' => $row->pos_session_id,
            'status' => $row->status->value,
            'amount' => $row->amount,
            'received_amount' => $row->received_amount,
        ])->all();

        return [
            ...compact('summary', 'checks', 'blockers', 'warnings'),
            'handovers' => $handoverRows,
        ];
    }

    /**
     * Generated timestamps and translated display names must not invalidate an otherwise identical review.
     *
     * @throws JsonException
     */
    private function hash(array $review): string
    {
        unset(
            $review['summary']['meta']['generated_at'],
            $review['summary']['branches']
        );

        foreach ($review['summary']['payments']['methods'] as &$method) {
            $method['method'] = [
                'id' => $method['method']['id'],
            ];
        }

        unset($method);

        return hash(
            'sha256',
            json_encode($review, JSON_THROW_ON_ERROR)
        );
    }

    /** {@inheritDoc} */
    public function history(array $filters): LengthAwarePaginator
    {
        $branch = $this->branch((int) $filters['branch_id']);

        return DayClosing::query()
            ->where('branch_id', $branch->id)
            ->with('createdBy')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(Forkiva::paginate());
    }

    /** {@inheritDoc} */
    public function close(array $data): DayClosing
    {
        $branch = $this->branch((int) $data['branch_id']);

        $notes = isset($data['notes'])
            ? trim($data['notes'])
            : null;

        $notes = $notes === '' ? null : $notes;

        $key = Str::lower($data['request_key']);

        return DB::transaction(function () use ($branch, $data, $notes, $key): DayClosing {
            // All closers lock the branch first; custody mutations already lock sessions before handovers.
            $branch = Branch::query()
                ->withoutGlobalActive()
                ->whereKey($branch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = DayClosing::query()
                ->where('branch_id', $branch->id)
                ->where(
                    fn (Builder $query) => $query
                        ->whereDate('date', $data['date'])
                        ->orWhere('request_key', $key)
                )
                ->lockForUpdate()
                ->first();

            if ($existing) {
                abort_unless(
                    $existing->date->toDateString() === $data['date']
                    && $existing->request_key === $key
                    && $existing->created_by === auth()->id()
                    && $existing->review_hash === $data['review_hash']
                    && $existing->notes === $notes,
                    409,
                    __('dailyfinance::day_closings.errors.already_closed')
                );

                return $existing->load('createdBy');
            }

            $start = CarbonImmutable::parse(
                $data['date'],
                $branch->timezone ?: config('app.timezone')
            )->startOfDay();

            $sessions = PosSession::query()
                ->where('branch_id', $branch->id)
                ->where(
                    'opened_at',
                    '<',
                    $start->addDay()->setTimezone(config('app.timezone'))
                )
                ->where(
                    fn (Builder $query) => $query
                        ->whereNull('closed_at')
                        ->orWhere(
                            'closed_at',
                            '>=',
                            $start->setTimezone(config('app.timezone'))
                        )
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            CashHandover::query()
                ->where('branch_id', $branch->id)
                ->whereIn('pos_session_id', $sessions->modelKeys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $review = $this->snapshot($branch, $data['date']);

            abort_unless(
                hash_equals($this->hash($review), $data['review_hash']),
                409,
                __('dailyfinance::day_closings.errors.changed')
            );

            if ($review['blockers'] !== []) {
                throw ValidationException::withMessages([
                    'date' => __('dailyfinance::day_closings.errors.blocked'),
                ]);
            }

            if ($review['warnings'] !== [] && mb_strlen($notes ?? '') < 3) {
                throw ValidationException::withMessages([
                    'notes' => __('dailyfinance::day_closings.errors.notes'),
                ]);
            }

            return DayClosing::create([
                'branch_id' => $branch->id,
                'date' => $data['date'],
                'timezone' => $review['summary']['meta']['timezone'],
                'currency' => $review['summary']['meta']['currency'],
                'request_key' => $key,
                'review_hash' => $data['review_hash'],
                'notes' => $notes,
                'snapshot' => $review,
            ])->load('createdBy');
        }, 3);
    }
}
