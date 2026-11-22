<?php

namespace Modules\Branch\Services\BranchAccess;

use Modules\Branch\Models\Branch;

class BranchAccessService implements BranchAccessServiceInterface
{
    /** {@inheritDoc} */
    public function resolve(?int $requestedBranchId = null, bool $required = false): ?int
    {
        $assignedBranchId = $this->assignedBranchId();

        if ($assignedBranchId !== null) {
            if ($requestedBranchId !== null && $requestedBranchId !== $assignedBranchId) {
                abort(404);
            }

            return $assignedBranchId;
        }

        if ($requestedBranchId === null) {
            if ($required) {
                abort(404);
            }

            return null;
        }

        Branch::query()
            ->withTrashed()
            ->withoutGlobalActive()
            ->findOrFail($requestedBranchId);

        return $requestedBranchId;
    }

    /** {@inheritDoc} */
    public function assignedBranchId(): ?int
    {
        $user = auth()->user();

        return $user?->assignedToBranch() ? (int) $user->branch_id : null;
    }
}
