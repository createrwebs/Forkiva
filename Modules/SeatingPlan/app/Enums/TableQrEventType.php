<?php

namespace Modules\SeatingPlan\Enums;

use Modules\Support\Traits\EnumArrayable;

enum TableQrEventType: string
{
    use EnumArrayable;

    case TokenRegenerated = 'token_regenerated';
    case ContextOpened = 'context_opened';
    case SubmitAttempt = 'submit_attempt';
    case SubmitSuccess = 'submit_success';
    case SubmitFailed = 'submit_failed';
}
