<?php

namespace Modules\DailyFinance\Services\DailyFinance;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Core\Contracts\FinancialExpenseSummaryReaderInterface;
use Modules\Currency\Currency;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Models\Payment;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosSession;

class DailyFinanceService implements DailyFinanceServiceInterface
{
    public function __construct(
        protected BranchAccessServiceInterface $branchAccess,
        protected FinancialExpenseSummaryReaderInterface $expenses,
    ) {}

    /** {@inheritDoc} */
    public function summary(array $filters = []): array
    {
        $branchId = $this->branchAccess->resolve(isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $branches = Branch::query()
            ->withoutGlobalActive()
            ->when($this->branchAccess->assignedBranchId(), fn (Builder $query, int $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get(['id', 'name', 'currency', 'timezone']);

        $branch = $branchId ? $branches->firstWhere('id', $branchId) : $branches->first();

        abort_if($branchId && ! $branch, 404);

        $timezone = $branch?->timezone ?: config('app.timezone');

        $date = $filters['date'] ?? CarbonImmutable::now($timezone)->toDateString();

        // Operational timestamps use the application's storage timezone, while the selected day belongs to the branch.
        $start = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $from = $start->setTimezone(config('app.timezone'));
        $until = $start->addDay()->setTimezone(config('app.timezone'));

        $permissions = [
            'sales' => auth()->user()->can('admin.orders.index'),
            'payments' => auth()->user()->can('admin.payments.index'),
            'movements' => auth()->user()->can('admin.pos_cash_movements.index'),
            'sessions' => auth()->user()->can('admin.pos_sessions.index') && auth()->user()->can('admin.pos_sessions.show'),
            'expenses' => auth()->user()->can('admin.accounting_reports.index'),
        ];

        return [
            'meta' => [
                'branch_id' => $branch?->id,
                'date' => $date,
                'today' => CarbonImmutable::now($timezone)->toDateString(),
                'timezone' => $timezone,
                'currency' => $branch?->currency,
                'generated_at' => CarbonImmutable::now()->toIso8601String(),
                'branch_locked' => $this->branchAccess->assignedBranchId() !== null,
            ],
            'branches' => $branches->map(fn (Branch $item) => ['id' => $item->id, 'name' => $item->name])->all(),
            'permissions' => $permissions,
            'sales' => $branch && $permissions['sales'] ? $this->sales($branch, $from, $until) : null,
            'payments' => $branch && $permissions['payments'] ? $this->payments($branch, $from, $until) : null,
            'movements' => $branch && $permissions['movements'] ? $this->movements($branch, $from, $until) : null,
            'sessions' => $branch && $permissions['sessions'] ? $this->sessions($branch, $from, $until) : null,
            'expenses' => $branch && $permissions['expenses'] ? $this->expenses->forDate($branch->id, $date) : null,
        ];
    }

    /** {@inheritDoc} */
    public function entries(array $filters): LengthAwarePaginator
    {
        $permission = match ($filters['kind']) {
            'sales', 'dues' => 'admin.orders.index',
            'collections', 'refunds' => 'admin.payments.index',
            'movements' => 'admin.pos_cash_movements.index',
        };
        abort_unless(auth()->user()->can($permission), 403);
        $branchId = $this->branchAccess->resolve((int) $filters['branch_id'], true);
        $branch = Branch::query()->withoutGlobalActive()->findOrFail($branchId);
        $start = CarbonImmutable::parse($filters['date'], $branch->timezone ?: config('app.timezone'))->startOfDay();
        $from = $start->setTimezone(config('app.timezone'));
        $until = $start->addDay()->setTimezone(config('app.timezone'));
        $query = match ($filters['kind']) {
            'sales', 'dues' => $this->orderQuery($branch, $from, $until)
                ->with(['branch', 'allTaxes', 'discount', 'charges'])
                ->when($filters['kind'] === 'dues', fn (Builder $query) => $query->where('due_amount', '>', 0)),
            'collections', 'refunds' => $this->paymentQuery($branch, $from, $until)
                ->with(['branch', 'cashier:id,name'])
                ->where('type', $filters['kind'] === 'refunds' ? PaymentType::Refund : PaymentType::Payment),
            'movements' => $this->movementQuery($branch, $from, $until)
                ->with(['branch', 'posRegister:id,name'])
                ->whereIn('direction', [PosCashDirection::In, PosCashDirection::Out]),
        };
        $dateColumn = match ($filters['kind']) {
            'collections', 'refunds' => 'COALESCE(received_at, created_at)',
            'movements' => 'occurred_at',
            default => 'created_at',
        };

        return $query->where('currency', $branch->currency)
            ->orderByRaw($dateColumn.' DESC')->orderByDesc('id')
            ->paginate(15, ['*'], 'page', $filters['page'] ?? 1);
    }

    /**
     * Share source selection with the drill-down so rows explain the overview totals.
     */
    private function orderQuery(Branch $branch, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return $this->during(Order::query()->where('branch_id', $branch->id)->withoutCanceledOrders(), 'created_at', $from, $until);
    }

    private function paymentQuery(Branch $branch, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return Payment::query()->where('branch_id', $branch->id)->active()
            ->where(function (Builder $query) use ($from, $until) {
                $this->during($query, 'received_at', $from, $until)
                    ->orWhere(fn (Builder $legacy) => $this->during($legacy->whereNull('received_at'), 'created_at', $from, $until));
            });
    }

    private function movementQuery(Branch $branch, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return $this->during(PosCashMovement::query()->where('branch_id', $branch->id)
            ->where('reason', '!=', PosCashReason::ClosingAdjust->value), 'occurred_at', $from, $until);
    }

    /**
     * Report orders placed during the branch day and their current unpaid balance, not historical receivables.
     */
    private function sales(Branch $branch, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $totals = ['total' => '0', 'dues' => '0'];
        $count = 0;
        $excluded = 0;
        $orders = $this->orderQuery($branch, $from, $until);

        /** @var Order $order */
        foreach ($orders->select(['id', 'currency', 'total', 'due_amount'])->cursor() as $order) {
            if ($order->currency !== $branch->currency) {
                $excluded++;

                continue;
            }
            $count++;
            foreach (['total' => 'total', 'dues' => 'due_amount'] as $key => $column) {
                $totals[$key] = $this->add($totals[$key], $order->getRawOriginal($column));
            }
        }

        return [...$totals, 'orders_count' => $count, 'excluded_currency_count' => $excluded];
    }

    /**
     * Use half-open date bounds so midnight belongs to exactly one branch day.
     */
    private function during(Builder $query, string $column, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return $query->where($column, '>=', $from)->where($column, '<', $until);
    }

    /**
     * Preserve persisted decimal precision until the aggregate is displayed.
     */
    private function add(string $total, mixed $amount): string
    {
        return (string) BigDecimal::of($total)->plus((string) ($amount ?? '0'));
    }

    /**
     * Attribute receipts and refunds to their own receipt date, including payments for older orders.
     */
    private function payments(Branch $branch, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $methods = [];
        foreach (PaymentMethod::cases() as $method) {
            $methods[$method->value] = ['method' => $method->toTrans(), 'collections' => '0', 'refunds' => '0'];
        }
        $excluded = 0;
        $query = $this->paymentQuery($branch, $from, $until);
        foreach ($query->select(['id', 'currency', 'method', 'type', 'amount'])->cursor() as $payment) {
            if ($payment->currency !== $branch->currency) {
                $excluded++;

                continue;
            }
            $key = $payment->type === PaymentType::Refund ? 'refunds' : 'collections';
            $method = $payment->method->value;
            $methods[$method][$key] = $this->add($methods[$method][$key], $payment->getRawOriginal('amount'));
        }
        $collections = '0';
        $refunds = '0';
        foreach ($methods as &$method) {
            $method['collections'] = $this->rounded($method['collections'], $branch->currency);
            $method['refunds'] = $this->rounded($method['refunds'], $branch->currency);
            $method['net'] = (string) BigDecimal::of($method['collections'])->minus($method['refunds']);
            $collections = $this->add($collections, $method['collections']);
            $refunds = $this->add($refunds, $method['refunds']);
        }
        unset($method);

        return [
            'collections' => $collections,
            'refunds' => $refunds,
            'net' => (string) BigDecimal::of($collections)->minus($refunds),
            'methods' => array_values($methods),
            'excluded_currency_count' => $excluded,
        ];
    }

    private function rounded(string $amount, string $currency): string
    {
        return (string) BigDecimal::of($amount)->toScale(Currency::subunit($currency), RoundingMode::HalfUp);
    }

    /**
     * Keep drawer payouts distinct from posted expenses and avoid adding receipts a second time.
     */
    private function movements(Branch $branch, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $totals = ['cash_in' => '0', 'cash_out' => '0', 'pay_ins' => '0', 'payouts' => '0', 'cash_drops' => '0'];
        $excluded = 0;
        $query = $this->movementQuery($branch, $from, $until);

        /** @var PosCashMovement $movement */
        foreach ($query->select(['id', 'currency', 'direction', 'reason', 'amount'])->cursor() as $movement) {
            if ($movement->currency !== $branch->currency) {
                $excluded++;

                continue;
            }
            $key = match ($movement->direction) {
                PosCashDirection::In => 'cash_in',
                PosCashDirection::Out => 'cash_out',
                default => null,
            };
            if ($key === null) {
                continue;
            }
            $amount = $movement->getRawOriginal('amount');
            $totals[$key] = $this->add($totals[$key], $amount);
            $reasonKey = match (true) {
                $movement->direction === PosCashDirection::In && $movement->reason === PosCashReason::PayIn => 'pay_ins',
                $movement->direction === PosCashDirection::Out && $movement->reason === PosCashReason::PayOut => 'payouts',
                $movement->direction === PosCashDirection::Out && $movement->reason === PosCashReason::CashDrop => 'cash_drops',
                default => null,
            };
            if ($reasonKey !== null) {
                $totals[$reasonKey] = $this->add($totals[$reasonKey], $amount);
            }
        }

        return [...$totals, 'excluded_currency_count' => $excluded];
    }

    /**
     * Show sessions overlapping the day. Drawer totals use only the latest session per register,
     * preventing the same retained float from being counted again for successive shifts.
     */
    private function sessions(Branch $branch, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $sessions = PosSession::query()->where('branch_id', $branch->id)
            ->where('opened_at', '<', $until)
            ->where(fn (Builder $query) => $query->whereNull('closed_at')->orWhere('closed_at', '>=', $from))
            ->with(['posRegister', 'openedBy'])->orderByDesc('opened_at')->orderByDesc('id')->get();
        $liveIds = $sessions->filter(fn (PosSession $session) => ! $session->closed_at || $session->closed_at->gte($until))->modelKeys();
        $movementTotals = [];
        $invalidCurrencies = [];
        if ($liveIds !== []) {
            $movements = PosCashMovement::query()->where('branch_id', $branch->id)->whereIn('pos_session_id', $liveIds)
                ->where('occurred_at', '<', $until)
                ->where('reason', '!=', PosCashReason::ClosingAdjust->value);

            /** @var PosCashMovement $movement */
            foreach ($movements->select(['id', 'pos_session_id', 'currency', 'amount', 'direction'])->cursor() as $movement) {
                $id = $movement->pos_session_id;
                if ($movement->currency !== $branch->currency) {
                    $invalidCurrencies[$id] = true;

                    continue;
                }
                $amount = BigDecimal::of((string) $movement->getRawOriginal('amount'));
                $change = match ($movement->direction) {
                    PosCashDirection::In => $amount,
                    PosCashDirection::Out => $amount->negated(),
                    default => BigDecimal::zero(),
                };
                $movementTotals[$id] = $this->add($movementTotals[$id] ?? '0', (string) $change);
            }
        }
        $registers = [];
        $rows = [];
        $expectedTotal = '0';
        $countedTotal = '0';
        $allExpected = true;
        $allCounted = true;
        $openCount = 0;

        /** @var PosSession $session */
        foreach ($sessions as $session) {
            $closed = $session->closed_at && $session->closed_at->lt($until);
            $snapshot = $session->meta['cash_reconciliation'] ?? null;
            $expected = $closed
                ? ($snapshot['expected_cash'] ?? null)
                : (isset($invalidCurrencies[$session->id]) ? null : $this->add((string) $session->getRawOriginal('opening_float'), $movementTotals[$session->id] ?? '0'));
            $snapshotCurrency = $session->meta['payment_reconciliation']['currency'] ?? $branch->currency;
            if ($snapshotCurrency !== $branch->currency) {
                $expected = null;
            }
            $counted = $closed && $expected !== null ? $session->getRawOriginal('declared_cash') : null;
            $latest = ! isset($registers[$session->pos_register_id]);
            $registers[$session->pos_register_id] = true;
            $expected = $expected === null ? null : $this->rounded((string) $expected, $branch->currency);
            $counted = $counted === null ? null : $this->rounded((string) $counted, $branch->currency);
            $openCount += $closed ? 0 : 1;
            if ($latest) {
                $allExpected = $allExpected && $expected !== null;
                $allCounted = $allCounted && $counted !== null;
                $expectedTotal = $this->add($expectedTotal, $expected ?? '0');
                $countedTotal = $this->add($countedTotal, $counted ?? '0');
            }
            $rows[] = [
                'id' => $session->id,
                'register' => $session->posRegister?->name,
                'cashier' => $session->openedBy?->name,
                'status' => $closed ? 'closed' : 'open',
                'opened_at' => $session->opened_at?->toIso8601String(),
                'closed_at' => $closed ? $session->closed_at->toIso8601String() : null,
                'latest_for_register' => $latest,
                'expected' => $expected,
                'counted' => $counted,
                'variance' => $counted === null ? null : (string) BigDecimal::of($counted)->minus($expected),
            ];
        }

        return [
            'rows' => $rows,
            'open_count' => $openCount,
            'closed_count' => count($rows) - $openCount,
            'registers_count' => count($registers),
            'expected' => $rows && $allExpected ? $expectedTotal : null,
            'counted' => $rows && $allCounted ? $countedTotal : null,
            'variance' => $rows && $allExpected && $allCounted ? (string) BigDecimal::of($countedTotal)->minus($expectedTotal) : null,
        ];
    }
}
