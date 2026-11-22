<?php

namespace Modules\Translation\Support;

class PublicTranslationExceptPatterns
{
    public static array $patterns = [
        'auth.*',
        'validation.*',
        'pagination.*',
        '1::*.*',
        'passwords.*',
        '*::exceptions.*',
        'core::permissions.*',
        'support::countries.*',
        '*::enums.*',
        'support::languages.*',
    ];
}
