<?php

namespace Modules\Approval\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Approval\Models\ApprovalRequest;

/**
 * @property-read ApprovalRequest|null $approvalRequest
 */
trait HasApproval
{
    /**
     * Get the maker-checker approval request assigned to this model.
     */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id')
            ->withTrashed();
    }
}
