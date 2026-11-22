<?php

namespace Modules\Branch\Services\BranchAccess;

interface BranchAccessServiceInterface
{
    /**
     * Resolve the effective branch for the authenticated user.
     *
     * Assigned users are restricted to their assigned branch. Unassigned
     * administrators may use the requested branch or omit it when an endpoint
     * supports an all-branch result.
     */
    public function resolve(?int $requestedBranchId = null, bool $required = false): ?int;

    /**
     * Get the branch assigned to the authenticated user.
     */
    public function assignedBranchId(): ?int;
}
