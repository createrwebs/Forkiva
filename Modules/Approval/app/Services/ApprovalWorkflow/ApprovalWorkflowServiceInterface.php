<?php

namespace Modules\Approval\Services\ApprovalWorkflow;

use Illuminate\Database\Eloquent\Model;
use Modules\Approval\Exceptions\ApprovalException;
use Modules\Approval\Models\ApprovalRequest;

interface ApprovalWorkflowServiceInterface
{
    /**
     * Create a versioned approval request and its applicable decision levels.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<array{name: string, minimum_amount?: numeric|null, maximum_amount?: numeric|null, permission?: string|null}>  $levels
     * @param  array<string, mixed>  $metadata
     *
     * @throws ApprovalException
     */
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
    ): ApprovalRequest;

    /**
     * Approve the next pending approval level.
     *
     * @throws ApprovalException
     */
    public function approve(ApprovalRequest $request, int $actorId, string $reason): ApprovalRequest;

    /**
     * Reject the next pending approval level.
     *
     * @throws ApprovalException
     */
    public function reject(ApprovalRequest $request, int $actorId, string $reason): ApprovalRequest;

    /**
     * Invalidate approval evidence when the protected payload changes.
     *
     * @param  array<string, mixed>  $currentPayload
     *
     * @throws ApprovalException
     */
    public function invalidateIfChanged(
        ApprovalRequest $request,
        array $currentPayload,
        int $actorId,
        string $reason,
    ): ApprovalRequest;

    /**
     * Verify the approval request is currently approved.
     *
     * @throws ApprovalException
     */
    public function assertApproved(ApprovalRequest $request): void;
}
