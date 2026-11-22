<?php

namespace Modules\Core\Services\Governance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ImmutableAuditLoggerInterface;
use Modules\Core\Models\ImmutableAuditEvent;
use Modules\Core\Support\DeterministicPayload;
use Throwable;

class ImmutableAuditLogger implements ImmutableAuditLoggerInterface
{
    /** {@inheritDoc}
     * @throws Throwable
     */
    public function record(
        string $action,
        string $reason,
        ?int $actorId = null,
        ?Model $subject = null,
        ?int $branchId = null,
        ?array $before = null,
        ?array $after = null,
        array $metadata = [],
    ): ImmutableAuditEvent {
        $action = trim($action);
        $reason = trim($reason);

        if ($action === '' || $reason === '') {
            throw new InvalidArgumentException(__('core::exceptions.audit_action_reason_required'));
        }

        return DB::transaction(function () use ($action, $reason, $actorId, $subject, $branchId, $before, $after, $metadata): ImmutableAuditEvent {
            $previous = ImmutableAuditEvent::query()->lockForUpdate()->latest('id')->first();
            $occurredAt = now();
            $evidence = $this->buildEvidence(
                action: $action,
                reason: $reason,
                actorId: $actorId,
                subject: $subject,
                branchId: $branchId,
                before: $before,
                after: $after,
                metadata: $metadata,
                previousHash: $previous?->event_hash,
                occurredAt: $occurredAt->toISOString(),
            );

            return ImmutableAuditEvent::query()->create([
                ...$evidence,
                'event_hash' => DeterministicPayload::fingerprint($evidence),
                'occurred_at' => $occurredAt,
            ]);
        });
    }

    /**
     * Build the canonical payload used for the append-only audit hash.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function buildEvidence(
        string $action,
        string $reason,
        ?int $actorId,
        ?Model $subject,
        ?int $branchId,
        ?array $before,
        ?array $after,
        array $metadata,
        ?string $previousHash,
        string $occurredAt,
    ): array {
        return [
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'branch_id' => $branchId,
            'actor_id' => $actorId,
            'action' => $action,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata,
            'previous_hash' => $previousHash,
            'occurred_at' => $occurredAt,
        ];
    }
}
