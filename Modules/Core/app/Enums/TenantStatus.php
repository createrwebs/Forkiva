<?php

namespace Modules\Core\Enums;

enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function isAccessible(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }
}
