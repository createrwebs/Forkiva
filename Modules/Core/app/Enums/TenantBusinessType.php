<?php

namespace Modules\Core\Enums;

enum TenantBusinessType: string
{
    case Fnb = 'fnb';
    case Retail = 'retail';
    case Hybrid = 'hybrid';
}
