<?php

namespace Modules\Pos\Services\PosSession;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use DB;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Currency\Currency;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Events\OpenPosSession;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Support\GlobalStructureFilters;

class PosSessionService implements PosSessionServiceInterface
{
    public function __construct(protected BranchAccessServiceInterface $branchAccess) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('pos::pos_sessions.pos_session');
    }

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return $this->getModel()
            ->query()
            ->with([
                'branch:id,name,currency',
                'openedBy',
                'closedBy',
                'posRegister',
            ])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function getModel(): PosSession
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return PosSession::class;
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'pos_sessions';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.pos_sessions.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return PosSession::class;
    }

    /** {@inheritDoc} */
    public function open(array $data): PosSession
    {
        return DB::transaction(function () use ($data) {
            $register = PosRegister::query()
                ->whereBranch($data['branch_id'])
                ->findOrFail($data['pos_register_id']);

            $existing = PosSession::query()
                ->whereBranch($data['branch_id'])
                ->where('pos_register_id', $register->id)
                ->where('status', PosSessionStatus::Open)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'pos_register_id' => __('pos::messages.session_already_opened'),
                ]);
            }

            $session = PosSession::query()
                ->create([
                    'branch_id' => $data['branch_id'],
                    'pos_register_id' => $register->id,
                    'opened_by' => auth()->id(),
                    'status' => PosSessionStatus::Open,
                    'opened_at' => now(),
                    'opening_float' => $data['opening_float'] ?? 0,
                    'notes' => $data['notes'] ?? null,
                ]);

            event(new OpenPosSession($session));

            return $session;
        });
    }

    /** {@inheritDoc} */
    public function findOrFail(int $id): Builder|array|EloquentCollection|PosSession
    {
        return $this->getModel()
            ->query()
            ->with(['openedBy', 'closedBy', 'branch'])
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function closingPreview(int $id, ?string $declaredCash = null): array
    {
        return DB::transaction(function () use ($id, $declaredCash) {
            $session = $this->show($id)->load('branch');
            abort_unless($session->canBeClosedBy(auth()->user()), 403, __('pos::messages.session_close_not_responsible'));
            abort_if($session->isClosed(), 400, __('pos::messages.session_closed'));
            $reconciliation = $session->calculateReconciliation();
            $review = $this->closingReview($session, $reconciliation, $declaredCash);
            $session->forceFill([
                ...$reconciliation,
                'declared_cash' => $review['declared_cash_decimal'],
                'cash_over_short' => $review['cash_difference'],
            ]);

            return ['session' => $session, 'review' => $review];
        });
    }

    /** {@inheritDoc} */
    public function show(int $id): PosSession
    {
        return $this->getModel()
            ->query()
            ->with([
                'branch:id,name,currency',
                'openedBy',
                'closedBy',
                'posRegister',
            ])
            ->findOrFail($id);
    }

    /**
     * Bind the reviewed financial totals, cash count, branch policy and actor to confirmation.
     * The closing lock prevents attributed payment and drawer writers from changing these figures during confirmation.
     */
    private function closingReview(PosSession $session, array $reconciliation, ?string $declaredCash): array
    {
        $scale = Currency::subunit($session->currency);
        $threshold = BigDecimal::of((string) $session->branch->getRawOriginal('cash_difference_threshold'));
        $declared = null;
        if ($declaredCash !== null) {
            try {
                $declared = BigDecimal::of($declaredCash)->toScale($scale, RoundingMode::UNNECESSARY);
            } catch (RoundingNecessaryException) {
                throw ValidationException::withMessages(['declared_cash' => __('pos::messages.counted_cash_precision', ['precision' => $scale])]);
            }
        }
        $difference = $declared?->minus(BigDecimal::of($reconciliation['meta']['cash_reconciliation']['expected_cash']));
        $canClose = $difference !== null && ! $difference->abs()->isGreaterThan($threshold);
        $fingerprint = [
            'session_id' => $session->id,
            'branch_id' => $session->branch_id,
            'register_id' => $session->pos_register_id,
            'actor_id' => auth()->id(),
            'responsible_cashier_id' => $session->opened_by,
            'currency' => $session->currency,
            'opening_float' => (string) $session->getRawOriginal('opening_float'),
            'threshold' => (string) $threshold,
            'declared_cash' => $declared === null ? null : (string) $declared,
            'totals' => array_diff_key($reconciliation, ['meta' => true]),
            'cash' => $reconciliation['meta']['cash_reconciliation'],
            'payments' => $reconciliation['meta']['payment_reconciliation'],
        ];

        return [
            'generated_at' => dateTimeFormat(now()),
            'declared_cash_decimal' => $declared === null ? null : (string) $declared,
            'cash_difference' => $difference === null ? null : (string) $difference,
            'allowed_difference' => (string) $threshold,
            'can_close' => $canClose,
            'actor' => ['id' => auth()->id(), 'name' => auth()->user()?->name],
            'requires_closing_reason' => (int) $session->opened_by !== (int) auth()->id(),
            'requires_variance_reason' => $difference !== null && ! $difference->isZero(),
            'token' => $canClose ? hash_hmac('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR), config('app.key')) : null,
        ];
    }

    /** {@inheritDoc} */
    public function close(int $id, array $data): PosSession
    {
        return DB::transaction(function () use ($id, $data) {
            $session = PosSession::query()->with(['openedBy', 'closedBy', 'branch', 'posRegister'])->lockForUpdate()->findOrFail($id);
            abort_if($session->status !== PosSessionStatus::Open, 400, __('pos::messages.session_closed'));
            abort_unless($session->canBeClosedBy(auth()->user()), 403, __('pos::messages.session_close_not_responsible'));

            $session->declared_cash = $data['declared_cash'];
            try {
                $reconciliation = $session->calculateReconciliation();
            } catch (Exception $exception) {
                throw ValidationException::withMessages(['declared_cash' => $exception->getMessage()]);
            }
            if (isset($data['review_token'])) {
                $review = $this->closingReview($session, $reconciliation, (string) $data['declared_cash']);
                abort_unless($review['token'] && hash_equals($review['token'], $data['review_token']), 409, __('pos::messages.closing_review_changed'));
                $reconciliation['meta']['closing_review'] = [
                    'version' => 1,
                    'verified_by' => auth()->id(),
                    'verified_at' => now()->toIso8601String(),
                ];
            }

            $reviewed = isset($data['review_token']);
            $onBehalf = (int) $session->opened_by !== (int) auth()->id();
            $difference = BigDecimal::of((string) $data['declared_cash'])
                ->toScale(Currency::subunit($session->currency), RoundingMode::HALF_UP)
                ->minus($reconciliation['meta']['cash_reconciliation']['expected_cash']);
            $closingReason = $this->closingReason($data, 'closing_reason', $onBehalf);
            $varianceReason = $this->closingReason($data, 'variance_reason', $reviewed && ! $difference->isZero());
            $actor = ['id' => auth()->id(), 'name' => auth()->user()?->name];
            $reconciliation['meta']['closing_custody'] = [
                'version' => 1,
                'responsible_cashier' => ['id' => $session->opened_by, 'name' => $session->openedBy?->name],
                // Legacy callers did not attest to a personal count; do not invent historical counting evidence.
                'counted_by' => $reviewed ? $actor : null,
                'approved_by' => $actor,
                'approved_at' => now()->toIso8601String(),
                'on_behalf' => $onBehalf,
                'closing_reason' => $closingReason,
                'variance_reason' => $varianceReason,
            ];

            try {
                $summary = $session->calculateClosingSummary($reconciliation);
            } catch (Exception $exception) {
                throw ValidationException::withMessages([
                    'declared_cash' => $exception->getMessage(),
                ]);
            }

            $session->update([
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'status' => PosSessionStatus::Closed,
                'declared_cash' => $data['declared_cash'],
                ...$summary,
            ]);

            event(new ClosePosSession($session));

            return $session;
        }, 3);
    }

    /**
     * Require explanatory evidence before recording a delegated close or an attested cash variance.
     */
    private function closingReason(array $data, string $field, bool $required): ?string
    {
        $reason = trim((string) ($data[$field] ?? ''));
        if (($required || $reason !== '') && (Str::length($reason) < 3 || Str::length($reason) > 1000)) {
            throw ValidationException::withMessages([$field => __('pos::messages.'.$field.'_required')]);
        }

        return $reason === '' ? null : $reason;
    }

    /** {@inheritDoc} */
    public function getStructureFilters(?int $branchId = null): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'pos_register_id',
                'label' => __('pos::pos_sessions.filters.pos_register'),
                'type' => 'select',
                'options' => ! is_null($branchId) ? PosRegister::list($branchId) : [],
                'depends' => 'branch_id',
            ],
            [
                'key' => 'status',
                'label' => __('pos::pos_sessions.filters.status'),
                'type' => 'select',
                'options' => PosSessionStatus::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null): array
    {
        $resolvedBranchId = $this->branchAccess->resolve($branchId);

        if (is_null($branchId)) {
            return [
                'branches' => Branch::list(true),
            ];
        }

        return [
            'pos_registers' => PosRegister::list($resolvedBranchId),
        ];
    }
}
