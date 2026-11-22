<?php

namespace Modules\DailyFinance\Services\CashHandover;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Currency\Currency;
use Modules\DailyFinance\Enums\CashHandoverStatus;
use Modules\DailyFinance\Models\CashHandover;
use Modules\DailyFinance\Transformers\Api\V1\CashHandoverResource;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Models\PosSession;
use Modules\Support\Money;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class CashHandoverService implements CashHandoverServiceInterface
{
    private const RESOURCE_RELATIONS = ['createdBy:id,name', 'cashier:id,name', 'receiver:id,name', 'branch:id,timezone'];

    public function __construct(protected BranchAccessServiceInterface $branchAccess) {}

    /** {@inheritDoc} */
    public function summary(int $sessionId, int $page = 1): array
    {
        $session = $this->session($sessionId);
        $currency = $this->currency($session);
        $balances = $this->balances($session, currency: $currency);
        $records = CashHandover::query()
            ->with(self::RESOURCE_RELATIONS)
            ->where('pos_session_id', $session->id)
            ->latest('id')
            ->paginate(Forkiva::paginate(), ['*'], 'page', $page);

        $canCreate = $currency === $session->currency && $session->isClosed() && $balances['available']?->isPositive()
            && auth()->user()->can('admin.daily_finance_handovers.create');

        return [
            'currency' => $currency,
            'precision' => Currency::subunit($currency),
            'counted' => $this->money($balances['counted'], $currency),
            'handed_over' => $this->money($balances['reserved'], $currency),
            'received' => $this->money($balances['received'], $currency),
            'pending' => $this->money($balances['pending'], $currency),
            'variance' => $this->money($balances['variance'], $currency),
            'available' => $this->money($balances['available'], $currency),
            'available_decimal' => $balances['available'] === null ? null : (string) $balances['available']->toScale(Currency::subunit($currency)),
            'can_create' => (bool) $canCreate,
            'receivers' => $canCreate ? $this->receivers($session)->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values()->all() : [],
            'data' => CashHandoverResource::collection($records)->resolve(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
        ];
    }

    /** {@inheritDoc} */
    public function followUp(int $branchId): array
    {
        $summary = [
            'branch_id' => $branchId,
            'unhanded_sessions_count' => 0,
            'pending_sessions_count' => 0,
            'pending_handovers_count' => 0,
        ];
        $balances = [];
        foreach ($this->followUpRows($branchId) as $row) {
            $available = $row['available'];
            $pending = $row['pending'];
            $summary['unhanded_sessions_count'] += $available->isPositive() ? 1 : 0;
            $summary['pending_sessions_count'] += $row['pending_count'] > 0 ? 1 : 0;
            $summary['pending_handovers_count'] += $row['pending_count'];
            $currency = $row['currency'];
            $balances[$currency] ??= ['currency' => $currency, 'unhanded' => '0', 'pending' => '0'];
            $balances[$currency]['unhanded'] = (string) BigDecimal::of($balances[$currency]['unhanded'])->plus($available);
            $balances[$currency]['pending'] = (string) BigDecimal::of($balances[$currency]['pending'])->plus($pending);
        }
        ksort($balances);

        return [...$summary, 'balances' => array_values($balances)];
    }

    /** {@inheritDoc} */
    public function followUpSessions(int $branchId, string $kind, int $page = 1): array
    {
        $perPage = Forkiva::paginate();
        $offset = ($page - 1) * $perPage;
        $data = [];
        $total = 0;
        foreach ($this->followUpRows($branchId, true) as $row) {
            if ($kind === 'unhanded' ? ! $row['available']->isPositive() : $row['pending_count'] === 0) {
                continue;
            }
            if ($total >= $offset && count($data) < $perPage) {
                $data[] = $row;
            }
            $total++;
        }

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'total' => $total,
            ],
        ];
    }

    /**
     * Read actionable closed sessions in bounded batches, preserving the same custody reservations
     * and historical currency used by the session drawer. These live balances are independent of day closing.
     *
     * @return Generator<int, array{session: PosSession, currency: string, available: BigDecimal, pending: BigDecimal, pending_count: int}>
     */
    protected function followUpRows(int $branchId, bool $withActors = false): Generator
    {
        $branch = Branch::query()
            ->withoutGlobalActive()
            ->findOrFail($this->branchAccess->resolve($branchId, true));
        $lastId = 0;
        do {
            $query = PosSession::query()
                ->where('branch_id', $branch->id)
                ->where('status', PosSessionStatus::Closed);
            if ($withActors) {
                $query->with(['posRegister', 'openedBy:id,name']);
            }
            $sessions = $query->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(200)
                ->get();
            $handovers = CashHandover::query()
                ->whereIn('pos_session_id', $sessions->modelKeys())
                ->orderBy('id')
                ->get(['pos_session_id', 'currency', 'counted_cash', 'amount', 'received_amount', 'status'])
                ->groupBy('pos_session_id');
            foreach ($sessions as $session) {
                $session->setRelation('branch', $branch);
                $records = $handovers->get($session->id, collect());
                $currency = $session->meta['payment_reconciliation']['currency'] ?? $records->first()?->currency ?? $session->currency;
                $active = $records->filter(fn (CashHandover $row) => $row->status !== CashHandoverStatus::Cancelled);
                $balances = $this->balances($session, currency: $currency, records: $active);
                $available = $balances['available'];
                $pendingCount = $active->where('status', CashHandoverStatus::Pending)->count();
                if ($available === null || (! $available->isPositive() && $pendingCount === 0)) {
                    continue;
                }

                yield [
                    'session' => $session,
                    'currency' => $currency,
                    'available' => BigDecimal::max($available, 0),
                    'pending' => $balances['pending'],
                    'pending_count' => $pendingCount,
                ];
            }
            $lastId = $sessions->last()?->id ?? $lastId;
        } while ($sessions->count() === 200);
    }

    /**
     * Resolve the source session within the actor's authorized branch.
     */
    protected function session(int $id, bool $lock = false): PosSession
    {
        $query = PosSession::query()->with('branch');
        $session = ($lock ? $query->lockForUpdate() : $query)->findOrFail($id);
        $this->branchAccess->resolve($session->branch_id, true);

        return $session;
    }

    /**
     * Preserve the POS closing currency, rather than relabeling historical cash after a branch change.
     */
    protected function currency(PosSession $session): string
    {
        return $session->meta['payment_reconciliation']['currency']
            ?? CashHandover::query()->where('pos_session_id', $session->id)->oldest('id')->value('currency')
            ?? $session->currency;
    }

    /**
     * Exact sums use current locking reads when reserving cash, avoiding stale repeatable-read snapshots.
     * Branch follow-up may reuse prefetched evidence; mutation callers always perform locking reads.
     */
    protected function balances(PosSession $session, bool $lock = false, ?string $currency = null, ?Collection $records = null): array
    {
        $currency ??= $this->currency($session);
        $scale = Currency::subunit($currency);
        $raw = $session->getRawOriginal('declared_cash');
        $counted = $session->isClosed() && $raw !== null ? BigDecimal::of((string) $raw)->toScale($scale, RoundingMode::HALF_UP) : null;
        $reserved = $received = $pending = $variance = BigDecimal::zero()->toScale($scale);
        if ($lock || $records === null) {
            $query = CashHandover::query()->where('pos_session_id', $session->id)->where('status', '!=', CashHandoverStatus::Cancelled->value)->orderBy('id');
            $records = ($lock ? $query->lockForUpdate() : $query)->get(['currency', 'counted_cash', 'amount', 'received_amount', 'status']);
        }
        foreach ($records as $row) {
            abort_unless($row->currency === $currency
                && $counted?->isEqualTo(BigDecimal::of($row->counted_cash)), 409, __('dailyfinance::cash_handovers.errors.snapshot'));
            $amount = BigDecimal::of($row->amount);
            $actual = $row->received_amount === null ? null : BigDecimal::of($row->received_amount);
            $reserved = $reserved->plus($actual?->isGreaterThan($amount) ? $actual : $amount);
            if ($row->status === CashHandoverStatus::Pending) {
                $pending = $pending->plus($amount);
            } else {
                $received = $received->plus($actual);
                $variance = $variance->plus($actual->minus($amount));
            }
        }

        return [
            'counted' => $counted,
            'reserved' => $reserved,
            'received' => $received,
            'pending' => $pending,
            'variance' => $variance,
            'available' => $counted?->isNegative() ? null : $counted?->minus($reserved),
        ];
    }

    protected function money(?BigDecimal $amount, string $currency): ?Money
    {
        return $amount === null ? null : new Money($amount->toFloat(), $currency);
    }

    /**
     * Only active branch-compatible users who can open and receive this workflow are eligible.
     */
    protected function receivers(PosSession $session): Collection
    {
        return User::query()->withOutGlobalBranchPermission()
            ->where(fn (Builder $query) => $query->where('branch_id', $session->branch_id)
                ->orWhereNull('branch_id'))
            ->whereNotIn('id', [auth()->id(), $session->opened_by])
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereHas('permissions', fn (Builder $permissions) => $permissions->where('name', 'admin.daily_finance_handovers.receive'))
                ->orWhereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('name', 'admin.daily_finance_handovers.receive'))
                ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', DefaultRole::SuperAdmin->value)))
            ->with(['permissions', 'roles.permissions'])
            ->orderBy('name')->get()
            ->filter(fn (User $user) => $user->can('admin.daily_finance.index')
                && $user->can('admin.pos_sessions.show')
                && $user->can('admin.daily_finance_handovers.index')
                && $user->can('admin.daily_finance_handovers.receive'));
    }

    /** {@inheritDoc} */
    public function create(int $sessionId, array $data): CashHandover
    {
        return DB::transaction(function () use ($sessionId, $data): CashHandover {
            // Every custody writer locks the closed session first, then its handovers, in the same order.
            $session = $this->session($sessionId, true);
            $currency = $this->currency($session);
            $requestKey = strtolower($data['request_key']);
            $amount = $this->decimal($data['amount'], $currency, 'amount');
            $notes = $this->text($data['notes'] ?? null);
            $existing = CashHandover::query()->where('pos_session_id', $session->id)
                ->where('request_key', $requestKey)->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->created_by === auth()->id()
                    && $existing->receiver_id === (int) $data['receiver_id']
                    && BigDecimal::of($existing->amount)->isEqualTo($amount)
                    && $existing->notes === $notes, 409, __('dailyfinance::cash_handovers.errors.replay'));

                return $existing->loadMissing(self::RESOURCE_RELATIONS);
            }

            abort_unless($currency === $session->currency, 422, __('dailyfinance::cash_handovers.errors.snapshot'));
            $balances = $this->balances($session, true, $currency);
            abort_unless($session->isClosed() && $balances['available'] !== null, 422, __('dailyfinance::cash_handovers.errors.closed_counted'));
            if (! $amount->isPositive() || $amount->isGreaterThan($balances['available'])) {
                throw ValidationException::withMessages(['amount' => __('dailyfinance::cash_handovers.errors.available')]);
            }
            $receiver = $this->receivers($session)->firstWhere('id', (int) $data['receiver_id']);
            if (! $receiver) {
                throw ValidationException::withMessages(['receiver_id' => __('dailyfinance::cash_handovers.errors.receiver')]);
            }

            return CashHandover::create([
                'pos_session_id' => $session->id,
                'branch_id' => $session->branch_id,
                'cashier_id' => $session->opened_by,
                'receiver_id' => $receiver->id,
                'request_key' => $requestKey,
                'currency' => $currency,
                'counted_cash' => (string) $balances['counted'],
                'amount' => (string) $amount,
                'status' => CashHandoverStatus::Pending,
                'notes' => $notes,
            ])->loadMissing(self::RESOURCE_RELATIONS);
        }, 3);
    }

    protected function decimal(string $value, string $currency, string $field): BigDecimal
    {
        try {
            return BigDecimal::of($value)->toScale(Currency::subunit($currency), RoundingMode::UNNECESSARY);
        } catch (RoundingNecessaryException) {
            throw ValidationException::withMessages([$field => __('dailyfinance::cash_handovers.errors.precision')]);
        }
    }

    protected function text(?string $value): ?string
    {
        $value = trim($value ?? '');

        return $value === '' ? null : $value;
    }

    /** {@inheritDoc} */
    public function receive(int $sessionId, int $id, array $data): CashHandover
    {
        return DB::transaction(function () use ($sessionId, $id, $data): CashHandover {
            $session = $this->session($sessionId, true);
            $handover = $this->handover($session, $id);
            abort_unless(auth()->id() === $handover->receiver_id, 403);
            $received = $this->decimal($data['received_amount'], $handover->currency, 'received_amount');
            $reason = $this->text($data['variance_reason'] ?? null);
            if ($handover->status === CashHandoverStatus::Received) {
                abort_unless(BigDecimal::of($handover->received_amount)->isEqualTo($received)
                    && $handover->variance_reason === $reason, 409, __('dailyfinance::cash_handovers.errors.replay'));

                return $handover->loadMissing(self::RESOURCE_RELATIONS);
            }
            abort_unless($handover->status === CashHandoverStatus::Pending, 409, __('dailyfinance::cash_handovers.errors.transition'));
            $amount = BigDecimal::of($handover->amount);
            $balances = $this->balances($session, true);
            $limit = $amount->plus($balances['available'] ?? BigDecimal::zero());
            if ($received->isNegative() || $received->isGreaterThan($limit)) {
                throw ValidationException::withMessages(['received_amount' => __('dailyfinance::cash_handovers.errors.received_amount')]);
            }
            if (! $received->isEqualTo($amount) && mb_strlen($reason ?? '') < 3) {
                throw ValidationException::withMessages(['variance_reason' => __('dailyfinance::cash_handovers.errors.reason')]);
            }
            // A shortage belongs to custody evidence; it must never become available for a second handover.
            $handover->update([
                'received_amount' => (string) $received,
                'variance_reason' => $reason,
                'received_at' => now(),
                'status' => CashHandoverStatus::Received,
            ]);

            return $handover->loadMissing(self::RESOURCE_RELATIONS);
        }, 3);
    }

    protected function handover(PosSession $session, int $id): CashHandover
    {
        return CashHandover::query()->where('pos_session_id', $session->id)->lockForUpdate()->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function cancel(int $sessionId, int $id, array $data): CashHandover
    {
        return DB::transaction(function () use ($sessionId, $id, $data): CashHandover {
            $session = $this->session($sessionId, true);
            $handover = $this->handover($session, $id);
            abort_unless(auth()->id() === $handover->created_by, 403);
            $reason = $this->text($data['cancellation_reason']);
            if (mb_strlen($reason ?? '') < 3) {
                throw ValidationException::withMessages(['cancellation_reason' => __('dailyfinance::cash_handovers.errors.reason')]);
            }
            if ($handover->status === CashHandoverStatus::Cancelled) {
                abort_unless($handover->cancellation_reason === $reason, 409, __('dailyfinance::cash_handovers.errors.replay'));

                return $handover->loadMissing(self::RESOURCE_RELATIONS);
            }
            abort_unless($handover->status === CashHandoverStatus::Pending, 409, __('dailyfinance::cash_handovers.errors.transition'));
            $handover->update([
                'status' => CashHandoverStatus::Cancelled,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $handover->loadMissing(self::RESOURCE_RELATIONS);
        }, 3);
    }
}
