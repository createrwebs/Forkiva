<?php

namespace Modules\Approval\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ApprovalRequestStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Invalidated = 'invalidated';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'approval::enums.approval_request_statuses';
    }
}
