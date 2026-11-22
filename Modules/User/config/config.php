<?php

use Modules\User\Enums\PermissionAction as Action;
use Modules\User\Imports\CustomerImportDefinition;
use Modules\User\Imports\UserImportDefinition;
use Modules\User\Services\Customer\CustomerServiceInterface;
use Modules\User\Services\Role\RoleServiceInterface;
use Modules\User\Services\User\UserServiceInterface;

return [
    'password_recovery' => [
        'cache_store' => env(
            'PASSWORD_RECOVERY_CACHE_STORE',
            in_array(config('cache.default'), ['array', 'null'], true)
                ? 'file'
                : config('cache.default')),
    ],

    'two_factor' => [
        'cache_store' => env(
            'TWO_FACTOR_CACHE_STORE',
            in_array(config('cache.default'), ['array', 'null'], true)
                ? 'file'
                : config('cache.default')
        ),
        'challenge_ttl_minutes' => 10,
        'resend_cooldown_seconds' => 60,
        'max_attempts' => 5,
        'attempt_decay_seconds' => 300,
        'code_digits' => 6,
        'totp_period' => 30,
        'totp_window' => 1,
        'recovery_code_count' => 8,
    ],

    'permissions' => [
        'users' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
        'customers' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import, Action::Pdpa],
        'roles' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
        'profiles' => [Action::Edit],
    ],

    'export_resources' => [
        'users' => UserServiceInterface::class,
        'customers' => CustomerServiceInterface::class,
        'roles' => RoleServiceInterface::class,
    ],

    'import_resources' => [
        'users' => UserImportDefinition::class,
        'customers' => CustomerImportDefinition::class,
    ],
];
