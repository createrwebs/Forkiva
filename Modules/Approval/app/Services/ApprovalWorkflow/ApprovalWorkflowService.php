<?php

namespace Modules\Approval\Services\ApprovalWorkflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Approval\Enums\ApprovalRequestStatus;
use Modules\Approval\Enums\ApprovalStepStatus;
use Modules\Approval\Exceptions\ApprovalException;
use Modules\Approval\Models\ApprovalRequest;
use Modules\Approval\Models\ApprovalStep;
use Modules\Core\Contracts\ImmutableAuditLoggerInterface;
use Modules\Core\Support\DeterministicPayload;
use Modules\User\Models\User;

readonly class ApprovalWorkflowService implements ApprovalWorkflowServiceInterface
{
    public function __construct(private ImmutableAuditLoggerInterface $audit) {}

    /** {@inheritDoc} */
    public function request(
        string $workflow,
        int $makerId,
        array $payload,
        array $levels,
        ?Model $subject = null,
        ?int $branchId = null,
        int|float|string|null $amount = null,
        ?string $currency = null,
        array $metadata = [],
    ): ApprovalRequest {
        $workflow = trim($workflow);
        $this->validateRequestDefinition($workflow, $amount, $levels);

        return DB::transaction(function () use ($workflow, $makerId, $payload, $levels, $subject, $branchId, $amount, $currency, $metadata): ApprovalRequest {
            $previousVersion = ApprovalRequest::query()
                ->when($subject, fn ($query) => $query
                    ->where('subject_type', $subject->getMorphClass())
                    ->where('subject_id', $subject->getKey()))
                ->where('workflow', $workflow)
                ->max('version');

            $request = ApprovalRequest::query()
                ->create([
                    'subject_type' => $subject?->getMorphClass(),
                    'subject_id' => $subject?->getKey(),
                    'branch_id' => $branchId,
                    'maker_id' => $makerId,
                    'workflow' => $workflow,
                    'status' => ApprovalRequestStatus::Pending,
                    'amount' => $amount,
                    'currency' => $currency ? strtoupper(trim($currency)) : null,
                    'version' => ((int) $previousVersion) + 1,
                    'payload_fingerprint' => DeterministicPayload::fingerprint($payload),
                    'payload' => DeterministicPayload::normalize($payload),
                    'metadata' => $metadata,
                ]);

            foreach (array_values($levels) as $index => $level) {
                $minimum = $level['minimum_amount'] ?? null;
                $maximum = $level['maximum_amount'] ?? null;

                $request->steps()->create([
                    'level' => $index + 1,
                    'name' => trim($level['name']),
                    'minimum_amount' => $minimum,
                    'maximum_amount' => $maximum,
                    'permission' => $level['permission'] ?? null,
                    'status' => $this->isApplicable($amount, $minimum, $maximum)
                        ? ApprovalStepStatus::Pending
                        : ApprovalStepStatus::Skipped,
                ]);
            }

            if (! $request->steps()->where('status', ApprovalStepStatus::Pending->value)->exists()) {
                throw new ApprovalException(__('approval::exceptions.no_applicable_level'));
            }

            $this->audit->record(
                action: 'approval.requested',
                reason: __('approval::messages.approval_requested'),
                actorId: $makerId,
                subject: $subject,
                branchId: $branchId,
                after: $payload,
                metadata: [
                    'approval_request_id' => $request->id,
                    'workflow' => $workflow,
                    'version' => $request->version,
                ],
            );

            return $request->load('steps');
        });
    }

    /**
     * Validate workflow, amount, and threshold definitions before persistence.
     *
     * @param  list<array{name: string, minimum_amount?: numeric|null, maximum_amount?: numeric|null, permission?: string|null}>  $levels
     */
    private function validateRequestDefinition(string $workflow, int|float|string|null $amount, array $levels): void
    {
        if ($workflow === '' || $levels === []) {
            throw new ApprovalException(__('approval::exceptions.workflow_and_level_required'));
        }
        if ($amount !== null && ! is_numeric($amount)) {
            throw new ApprovalException(__('approval::exceptions.amount_must_be_numeric'));
        }

        foreach ($levels as $level) {
            $name = trim((string) ($level['name'] ?? ''));
            $minimum = $level['minimum_amount'] ?? null;
            $maximum = $level['maximum_amount'] ?? null;
            if ($name === '') {
                throw new ApprovalException(__('approval::exceptions.level_name_required'));
            }
            if (($minimum !== null && ! is_numeric($minimum)) || ($maximum !== null && ! is_numeric($maximum))) {
                throw new ApprovalException(__('approval::exceptions.thresholds_must_be_numeric'));
            }
            if ($minimum !== null && $maximum !== null && bccomp((string) $minimum, (string) $maximum, 8) === 1) {
                throw new ApprovalException(__('approval::exceptions.minimum_exceeds_maximum'));
            }
        }
    }

    /**
     * Determine whether a level applies without floating-point money comparisons.
     */
    private function isApplicable(
        int|float|string|null $amount,
        int|float|string|null $minimum,
        int|float|string|null $maximum,
    ): bool {
        return $amount === null
            || (($minimum === null || bccomp((string) $amount, (string) $minimum, 8) >= 0)
                && ($maximum === null || bccomp((string) $amount, (string) $maximum, 8) <= 0));
    }

    /** {@inheritDoc} */
    public function approve(ApprovalRequest $request, int $actorId, string $reason): ApprovalRequest
    {
        return $this->decide($request, $actorId, $reason, true);
    }

    /**
     * Apply one reviewer decision inside a row-locked transaction.
     */
    private function decide(ApprovalRequest $request, int $actorId, string $reason, bool $approve): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actorId, $reason, $approve): ApprovalRequest {
            $locked = ApprovalRequest::query()->with('steps')->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== ApprovalRequestStatus::Pending) {
                throw new ApprovalException(__('approval::exceptions.only_pending_can_be_decided'));
            }

            if ($locked->maker_id === $actorId) {
                throw new ApprovalException(__('approval::exceptions.maker_checker_violation'));
            }

            /** @var ApprovalStep|null $step */
            $step = $locked->steps->firstWhere('status', ApprovalStepStatus::Pending);
            if (! $step) {
                throw new ApprovalException(__('approval::exceptions.no_pending_level'));
            }

            $this->assertReviewerPermission($step, $actorId);
            $reason = $this->requiredReason($reason);
            $step->update([
                'status' => $approve ? ApprovalStepStatus::Approved : ApprovalStepStatus::Rejected,
                'decided_by' => $actorId,
                'decided_at' => now(),
                'reason' => $reason,
            ]);

            $requestStatus = ApprovalRequestStatus::Pending;
            $timestamp = [];
            if (! $approve) {
                $requestStatus = ApprovalRequestStatus::Rejected;
                $timestamp['rejected_at'] = now();
            } elseif (! $locked->steps()->where('status', ApprovalStepStatus::Pending->value)->exists()) {
                $requestStatus = ApprovalRequestStatus::Approved;
                $timestamp['approved_at'] = now();
            }
            $locked->update(['status' => $requestStatus, ...$timestamp]);

            $this->audit->record(
                action: $approve ? 'approval.approved' : 'approval.rejected',
                reason: $reason,
                actorId: $actorId,
                subject: $locked->subject,
                branchId: $locked->branch_id,
                metadata: ['approval_request_id' => $locked->id, 'level' => $step->level],
            );

            return $locked->fresh('steps');
        });
    }

    /**
     * Ensure a configured level can be decided by the selected reviewer.
     */
    private function assertReviewerPermission(ApprovalStep $step, int $actorId): void
    {
        if (! $step->permission) {
            return;
        }

        $actor = User::query()->withoutGlobalScopes()->find($actorId);
        $hasPermission = $actor && $actor->can($step->permission);

        if (! $hasPermission) {
            throw new ApprovalException(__('approval::exceptions.reviewer_missing_permission', [
                'permission' => $step->permission,
            ]));
        }
    }

    /**
     * Normalize and enforce the mandatory decision reason.
     */
    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new ApprovalException(__('approval::exceptions.decision_reason_required'));
        }

        return $reason;
    }

    /** {@inheritDoc} */
    public function reject(ApprovalRequest $request, int $actorId, string $reason): ApprovalRequest
    {
        return $this->decide($request, $actorId, $reason, false);
    }

    /** {@inheritDoc} */
    public function invalidateIfChanged(
        ApprovalRequest $request,
        array $currentPayload,
        int $actorId,
        string $reason,
    ): ApprovalRequest {
        if (hash_equals($request->payload_fingerprint, DeterministicPayload::fingerprint($currentPayload))) {
            return $request->fresh('steps');
        }

        return DB::transaction(function () use ($request, $currentPayload, $actorId, $reason): ApprovalRequest {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status === ApprovalRequestStatus::Invalidated) {
                return $locked->load('steps');
            }

            $reason = $this->requiredReason($reason);
            $locked->steps()->whereIn('status', [
                ApprovalStepStatus::Pending->value,
                ApprovalStepStatus::Approved->value,
            ])->update(['status' => ApprovalStepStatus::Invalidated->value]);
            $locked->update([
                'status' => ApprovalRequestStatus::Invalidated,
                'invalidated_at' => now(),
                'invalidated_by' => $actorId,
                'invalidation_reason' => $reason,
            ]);

            $this->audit->record(
                action: 'approval.invalidated',
                reason: $reason,
                actorId: $actorId,
                subject: $locked->subject,
                branchId: $locked->branch_id,
                before: $locked->payload,
                after: $currentPayload,
                metadata: ['approval_request_id' => $locked->id],
            );

            return $locked->load('steps');
        });
    }

    /** {@inheritDoc} */
    public function assertApproved(ApprovalRequest $request): void
    {
        if ($request->status !== ApprovalRequestStatus::Approved) {
            throw new ApprovalException(__('approval::exceptions.requires_current_approval'));
        }
    }
}
