<?php

namespace Modules\User\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\User\Services\Account\AccountService;
use Modules\User\Services\Account\AccountServiceInterface;
use Modules\User\Services\Auth\AccountSecurityState;
use Modules\User\Services\Auth\AccountSecurityStateInterface;
use Modules\User\Services\Auth\AuthService;
use Modules\User\Services\Auth\AuthServiceInterface;
use Modules\User\Services\Auth\PasskeyRegistrationService;
use Modules\User\Services\Auth\PasskeyRegistrationServiceInterface;
use Modules\User\Services\Customer\CustomerService;
use Modules\User\Services\Customer\CustomerServiceInterface;
use Modules\User\Services\CustomerPdpa\CustomerPdpaService;
use Modules\User\Services\CustomerPdpa\CustomerPdpaServiceInterface;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryIdentity;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryIdentityInterface;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryService;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryServiceInterface;
use Modules\User\Services\Role\RoleService;
use Modules\User\Services\Role\RoleServiceInterface;
use Modules\User\Services\TwoFactor\TwoFactorService;
use Modules\User\Services\TwoFactor\TwoFactorServiceInterface;
use Modules\User\Services\User\UserService;
use Modules\User\Services\User\UserServiceInterface;

class DeferredUserServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot the application events.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: AccountSecurityStateInterface::class,
            concrete: fn ($app) => $app->make(AccountSecurityState::class)
        );

        $this->app->singleton(
            abstract: AuthServiceInterface::class,
            concrete: fn ($app) => $app->make(AuthService::class)
        );

        $this->app->singleton(
            abstract: PasskeyRegistrationServiceInterface::class,
            concrete: fn ($app) => $app->make(PasskeyRegistrationService::class)
        );

        $this->app->singleton(
            abstract: PasswordRecoveryIdentityInterface::class,
            concrete: fn ($app) => $app->make(PasswordRecoveryIdentity::class)
        );

        $this->app->singleton(
            abstract: PasswordRecoveryServiceInterface::class,
            concrete: fn ($app) => $app->make(PasswordRecoveryService::class)
        );

        $this->app->singleton(
            abstract: RoleServiceInterface::class,
            concrete: fn ($app) => $app->make(RoleService::class)
        );

        $this->app->singleton(
            abstract: UserServiceInterface::class,
            concrete: fn ($app) => $app->make(UserService::class)
        );

        $this->app->singleton(
            abstract: AccountServiceInterface::class,
            concrete: fn ($app) => $app->make(AccountService::class)
        );

        $this->app->singleton(
            abstract: TwoFactorServiceInterface::class,
            concrete: fn ($app) => $app->make(TwoFactorService::class)
        );

        $this->app->singleton(
            abstract: CustomerServiceInterface::class,
            concrete: fn ($app) => $app->make(CustomerService::class)
        );

        $this->app->singleton(
            abstract: CustomerPdpaServiceInterface::class,
            concrete: fn ($app) => $app->make(CustomerPdpaService::class)
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            AccountSecurityStateInterface::class,
            AuthServiceInterface::class,
            PasskeyRegistrationServiceInterface::class,
            PasswordRecoveryIdentityInterface::class,
            PasswordRecoveryServiceInterface::class,
            RoleServiceInterface::class,
            UserServiceInterface::class,
            AccountServiceInterface::class,
            TwoFactorServiceInterface::class,
            CustomerServiceInterface::class,
            CustomerPdpaServiceInterface::class,
        ];
    }
}
