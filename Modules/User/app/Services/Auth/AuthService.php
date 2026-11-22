<?php

namespace Modules\User\Services\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Delivery\Models\DeliveryDriver;
use Modules\Hr\Models\Employee;
use Modules\User\Models\PersonalAccessToken;
use Modules\User\Models\User;
use Modules\User\Services\TwoFactor\TwoFactorServiceInterface;
use Nwidart\Modules\Facades\Module;

class AuthService implements AuthServiceInterface
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$LsCqmYBK.Z.WGY/blqveQOVGE0aGGauqiRNgT1WCln4Lg6c1xNFvq';

    /**
     * Create a new authentication service instance.
     */
    public function __construct(
        protected TwoFactorServiceInterface $twoFactorService,
    ) {}

    /** {@inheritDoc} */
    public function login(array $credentials): array
    {
        $identifier = Str::lower(trim($credentials['identifier']));
        $loginAsEmployee = ! empty($credentials['employee']);
        $loginAsDriver = ! empty($credentials['driver']);
        $this->validateLoginModules($loginAsEmployee, $loginAsDriver);
        $loginWithEmployeeIdentifier = $loginAsEmployee || $loginAsDriver;
        // Never coerce text or an overflowing value into a different employee ID.
        $employeeId = ctype_digit($identifier)
            ? filter_var($identifier, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        $matches = User::query()
            ->where(fn (Builder $query) => $query
                ->whereRaw('LOWER(email) = ?', [$identifier])
                ->orWhereRaw('LOWER(username) = ?', [$identifier])
                ->when(
                    $loginWithEmployeeIdentifier,
                    fn (Builder $query) => $query->orWhereHas('employee', function (Builder $employeeQuery) use ($identifier, $employeeId) {
                        $employeeQuery->withOutGlobalBranchPermission()
                            ->withoutGlobalActive()
                            ->withTrashed()
                            ->where(function (Builder $query) use ($identifier, $employeeId) {
                                $query->whereRaw('LOWER(code) = ?', [$identifier])
                                    ->when($employeeId !== false, fn (Builder $query) => $query->orWhere('id', $employeeId));
                            });
                    })
                )
            )
            ->withTrashed()
            ->withoutGlobalActive()
            ->limit(2)
            ->get();
        // Email-shaped usernames are valid, but multiple matching owners are ambiguous.
        $user = $matches->count() === 1 ? $matches->first() : null;

        $passwordHash = $user?->password ?? self::DUMMY_PASSWORD_HASH;
        abort_if(! Hash::check($credentials['password'], $passwordHash), 401, __('auth.failed'));

        $this->validateUserStatus($user);

        if ($loginAsEmployee || $loginAsDriver) {
            $this->validateEmployeeStatus($user->employee);
        }

        if ($loginAsDriver) {
            $this->validateDriverAccess($user);
        }

        if ($this->twoFactorService->requiredFor($user)) {
            return $this->twoFactorService->createLoginChallenge($user, [
                'employee' => $loginAsEmployee,
                'driver' => $loginAsDriver,
            ]);
        }

        return $this->grantToken($user);
    }

    /**
     * A requested mobile login must never silently become a regular login.
     */
    private function validateLoginModules(bool $employee, bool $driver): void
    {
        if ($employee || $driver) {
            $hr = Module::find('Hr') ?? Module::find('hr');
            abort_unless($hr?->isEnabled(), 401, __('auth.failed'));
        }

        if ($driver) {
            $delivery = Module::find('Delivery') ?? Module::find('delivery');
            abort_unless($delivery?->isEnabled(), 401, __('auth.failed'));
        }
    }

    /** {@inheritDoc} */
    public function validateUserStatus(?User $user): void
    {
        abort_if(! $user, 401, __('auth.failed'));
        abort_if($user->trashed(), 401, __('user::messages.account_deleted'));
        abort_if(! $user->is_active, 401, __('user::messages.account_not_activated'));
    }

    /** {@inheritDoc} */
    public function validateEmployeeStatus(?Employee $employee): void
    {
        abort_if(! $employee, 401, __('auth.failed'));
        abort_if($employee->trashed(), 401, __('user::messages.account_deleted'));
        abort_if(! $employee->is_active, 401, __('user::messages.account_not_activated'));
    }

    /** {@inheritDoc} */
    public function validateDriverAccess(?User $user): void
    {
        $user?->loadMissing('employee');

        $this->validateEmployeeStatus($user?->employee);

        $driverExists = DeliveryDriver::query()
            ->where('employee_id', $user->employee->id)
            ->where('is_active', true)
            ->exists();

        abort_if(
            ! $driverExists,
            401,
            __('delivery::messages.delivery_driver_profile_required')
        );
    }

    /** {@inheritDoc} */
    public function grantToken(User $user, bool $remember = false, string $name = 'Login'): array
    {
        $expirationMinutes = max(1, (int) config('sanctum.expiration', 10080));
        $expiresAt = now()->addMinutes($expirationMinutes);
        $token = $user->createToken($name, ['*'], $expiresAt)->plainTextToken;

        event(new Login('api', $user, $remember));

        return [
            'user' => $user,
            'token' => $token,
            'token_expires_at' => $expiresAt,
        ];
    }

    /** {@inheritDoc} */
    public function completeTwoFactorLogin(string $challengeToken, string $code): array
    {
        $challenge = $this->twoFactorService->verifyLoginChallenge($challengeToken, $code);
        /** @var User $user */
        $user = $challenge['user'];
        $context = $challenge['context'];

        $this->validateLoginModules(! empty($context['employee']), ! empty($context['driver']));
        $this->validateUserStatus($user);
        if (! empty($context['employee']) || ! empty($context['driver'])) {
            $this->validateEmployeeStatus($user->employee);
        }
        if (! empty($context['driver'])) {
            $this->validateDriverAccess($user);
        }

        return $this->grantToken($user);
    }

    /** {@inheritDoc} */
    public function isEmail(string $identifier): bool
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL);
    }

    /** {@inheritDoc} */
    public function logout(): bool
    {
        $user = auth()->user();

        /** @var PersonalAccessToken $currentAccessToken */
        $currentAccessToken = $user->currentAccessToken();
        $logout = $currentAccessToken->delete();

        event(new Logout('api', $user));

        return $logout;
    }

    /** {@inheritDoc} */
    public function validateEmployeeAccess(?User $user): void
    {
        $user?->loadMissing('employee');

        abort_if(
            ! $user?->employee || ! $user->employee->is_active || $user->employee->trashed(),
            401,
            __('hr::employees.employee_panel.validation.employee_required')
        );
    }
}
