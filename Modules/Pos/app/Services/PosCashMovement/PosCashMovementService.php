<?php

namespace Modules\Pos\Services\PosCashMovement;

use App\Forkiva;
use Brick\Math\BigDecimal;
use DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Core\Events\GratuityCollected;
use Modules\Currency\Models\CurrencyRate;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Support\GlobalStructureFilters;
use Throwable;

class PosCashMovementService implements PosCashMovementServiceInterface
{
    public function __construct(protected FinancialPostingBridgeInterface $postingBridge) {}

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
        if (! empty($context['session_id'])) {
            $filters['pos_session_id'] = $context['session_id'];
        }

        return $this->getModel()
            ->query()
            ->with(['branch:id,name', 'posRegister:id,name', 'posSession:id,pos_register_id'])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function getModel(): PosCashMovement
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return PosCashMovement::class;
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'pos_cash_movements';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.pos_cash_movements.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return PosCashMovement::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): PosCashMovement
    {
        return $this->getModel()
            ->query()
            ->with(['branch:id,name', 'posRegister:id,name', 'createdBy:id,name'])
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('pos::pos_cash_movements.pos_cash_movement');
    }

    /** {@inheritDoc} */
    public function sale(
        PosSession $session,
        float $amount,
        ?int $orderId = null,
        ?int $paymentId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::Sale,
            amount: $amount,
            direction: PosCashDirection::In,
            orderId: $orderId,
            paymentId: $paymentId,
            reference: $reference,
            notes: $notes,
        );
    }

    /**
     * Generic entry point (useful for admin tools or bulk import).
     * It records the movement and applies Rollups if needed.
     *
     * @throws Throwable
     */
    public function addMovement(
        PosSession $session,
        PosCashReason $reason,
        float|string $amount,
        PosCashDirection $direction,
        ?int $orderId = null,
        ?int $paymentId = null,
        ?string $reference = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): PosCashMovement {
        return DB::transaction(function () use ($session, $reason, $amount, $direction, $orderId, $paymentId, $reference, $notes, $idempotencyKey): PosCashMovement {
            // All drawer writers share the closing mutex, including an empty drawer's first movement.
            $session = PosSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($idempotencyKey) {
                $existing = PosCashMovement::query()
                    ->withOutGlobalBranchPermission()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    abort_unless(
                        (int) $existing->pos_session_id === $session->id
                        && $existing->reason === $reason
                        && $existing->direction === $direction
                        && BigDecimal::of((string) $existing->getRawOriginal('amount'))->isEqualTo(BigDecimal::of((string) $amount))
                        && (int) ($existing->order_id ?: 0) === ($orderId ?: 0)
                        && (int) ($existing->payment_id ?: 0) === ($paymentId ?: 0)
                        && $existing->reference === $reference
                        && $existing->notes === $notes,
                        422,
                        __('pos::messages.cash_movement_idempotency_conflict'),
                    );

                    return $existing;
                }
            }

            // A matching retry may replay after closing, but must never append a new movement.
            abort_if($session->isClosed(), 409, __('pos::messages.session_closed'));

            $movement = $this->storeMovement(
                session: $session,
                reason: $reason,
                direction: $direction,
                amount: $amount,
                orderId: $orderId,
                paymentId: $paymentId,
                reference: $reference,
                notes: $notes,
                idempotencyKey: $idempotencyKey,
            );

            if ($reason === PosCashReason::TipIn) {
                DB::afterCommit(fn () => $this->recordTipCollection($movement));
            }

            return $movement;
        }, 3);
    }

    private function storeMovement(
        PosSession $session,
        PosCashReason $reason,
        PosCashDirection $direction,
        float|string $amount,
        ?int $orderId,
        ?int $paymentId,
        ?string $reference,
        ?string $notes,
        ?string $idempotencyKey,
    ): PosCashMovement {
        $lastMovement = PosCashMovement::query()
            ->withOutGlobalBranchPermission()
            ->where('pos_session_id', $session->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $balanceBefore = BigDecimal::of((string) ($lastMovement?->getRawOriginal('balance_after') ?? $session->getRawOriginal('opening_float')));
        $amount = BigDecimal::of((string) $amount);

        if ($direction === PosCashDirection::In) {
            $balanceAfter = $balanceBefore->plus($amount);
        } elseif ($direction === PosCashDirection::Out) {
            $balanceAfter = $balanceBefore->minus($amount);
        } else {
            $balanceAfter = $balanceBefore;
        }

        return PosCashMovement::query()
            ->create([
                'branch_id' => $session->branch_id,
                'pos_register_id' => $session->pos_register_id,
                'pos_session_id' => $session->id,
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'direction' => $direction,
                'reason' => $reason,
                'amount' => (string) $amount,
                'currency' => $session->branch->currency,
                'currency_rate' => CurrencyRate::for($session->branch->currency),
                'balance_before' => (string) $balanceBefore,
                'balance_after' => (string) $balanceAfter,
                'reference' => $reference,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);
    }

    /**
     * Record tip collection.
     */
    private function recordTipCollection(PosCashMovement $movement): void
    {
        $snapshot = [
            'branch_id' => $movement->branch_id,
            'source_type' => 'pos_cash_movement',
            'source_id' => $movement->id,
            'source_version' => 1,
            'collection_type' => 'tip',
            'transaction_type' => 'collection',
            'amount' => $movement->getRawOriginal('amount'),
            'currency' => $movement->currency,
            'currency_rate' => $movement->getRawOriginal('currency_rate'),
            'economic_date' => $movement->occurred_at?->toDateString() ?: now()->toDateString(),
            'idempotency_key' => $movement->idempotency_key ?: "pos_tip:{$movement->id}",
            'source_snapshot' => [
                'pos_register_id' => $movement->pos_register_id,
                'pos_session_id' => $movement->pos_session_id,
                'reference' => $movement->reference,
            ],
        ];

        event(new GratuityCollected($snapshot));

        try {
            $this->postingBridge->recordEvent('gratuity_batch', $movement->id, 'posted', $snapshot);
        } catch (Throwable $exception) {
            try {
                $this->postingBridge->recordFailure(
                    'gratuity_batch',
                    $movement->id,
                    'posted',
                    class_basename($exception),
                    $exception->getMessage(),
                    $snapshot,
                );
            } catch (Throwable $diagnosticException) {
                Log::error('Unable to record POS tip collection Accounting failure.', [
                    'movement_id' => $movement->id,
                    'idempotency_key' => $snapshot['idempotency_key'],
                    'posting_error' => $exception->getMessage(),
                    'diagnostic_error' => $diagnosticException->getMessage(),
                ]);
            }
        }
    }

    /** {@inheritDoc} */
    public function refundCash(
        PosSession $session,
        float $amount,
        ?int $orderId = null,
        ?int $paymentId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::Refund,
            amount: $amount,
            direction: PosCashDirection::Out,
            orderId: $orderId,
            paymentId: $paymentId,
            reference: $reference,
            notes: $notes
        );
    }

    /** {@inheritDoc} */
    public function payIn(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::PayIn,
            amount: $amount,
            direction: PosCashDirection::In,
            reference: $reference,
            notes: $notes,
        );
    }

    /** {@inheritDoc} */
    public function payOut(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::PayOut,
            amount: $amount,
            direction: PosCashDirection::Out,
            reference: $reference,
            notes: $notes,
        );
    }

    /** {@inheritDoc} */
    public function cashDrop(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::CashDrop,
            amount: $amount,
            direction: PosCashDirection::Out,
            reference: $reference,
            notes: $notes,
        );
    }

    /** {@inheritDoc} */
    public function closingAdjust(
        PosSession $session,
        float|string $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::ClosingAdjust,
            amount: $amount,
            direction: PosCashDirection::Adjust,
            reference: $reference,
            notes: $notes,
        );
    }

    /** {@inheritDoc} */
    public function tipIn(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,

    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::TipIn,
            amount: $amount,
            direction: PosCashDirection::In,
            reference: $reference,
            notes: $notes
        );
    }

    /** {@inheritDoc} */
    public function tipOut(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::TipOut,
            amount: $amount,
            direction: PosCashDirection::Out,
            reference: $reference,
            notes: $notes,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** {@inheritDoc} */
    public function reverseTipOut(
        PosSession $session,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::Correction,
            amount: $amount,
            direction: PosCashDirection::In,
            reference: $reference,
            notes: $notes,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** {@inheritDoc} */
    public function correction(
        PosSession $session,
        float $amount,
        PosCashDirection $direction,
        ?string $reference = null,
        ?string $notes = null,
    ): PosCashMovement {
        return $this->addMovement(
            session: $session,
            reason: PosCashReason::Correction,
            amount: $amount,
            direction: $direction,
            reference: $reference,
            notes: $notes,
        );
    }

    /** {@inheritDoc} */
    public function getStructureFilters(?int $branchId = null, ?int $sessionId = null): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($sessionId) ? [
                ...(is_null($branchFilter) ? [] : [$branchFilter]),
                [
                    'key' => 'pos_register_id',
                    'label' => __('pos::pos_cash_movements.filters.pos_register'),
                    'type' => 'select',
                    'options' => ! is_null($branchId) ? PosRegister::list($branchId) : [],
                    'depends' => 'branch_id',
                ],
            ] : []),
            [
                'key' => 'direction',
                'label' => __('pos::pos_cash_movements.filters.direction'),
                'type' => 'select',
                'options' => PosCashDirection::toArrayTrans(),
            ],
            [
                'key' => 'reason',
                'label' => __('pos::pos_cash_movements.filters.reason'),
                'type' => 'select',
                'options' => PosCashReason::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function store(array $data): PosCashMovement
    {
        return $this->addMovement(
            session: $this->getPosActiveSession(
                $data['pos_register_id'],
                __('pos::pos_cash_movements.pos_cash_movement')
            ),
            reason: PosCashReason::from($data['reason']),
            amount: $data['amount'],
            direction: PosCashDirection::from($data['direction']),
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            idempotencyKey: $data['idempotency_key'] ?? null,
        );
    }

    /** {@inheritDoc} */
    public function getPosActiveSession(int $posRegisterId, ?string $action = null): ?PosSession
    {
        return PosRegister::activeSession($posRegisterId, $action);
    }
}
