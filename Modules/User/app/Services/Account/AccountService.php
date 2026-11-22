<?php

namespace Modules\User\Services\Account;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Events\PasswordChanged;
use Modules\User\Events\ProfileUpdated;
use Modules\User\Models\User;
use Nwidart\Modules\Facades\Module;

class AccountService implements AccountServiceInterface
{
    /** {@inheritDoc} */
    public function me(): User
    {
        $query = $this->getModel()
            ->with(['branch'])
            ->where('id', auth()->id())
            ->firstOrFail();

        if (Module::find('Hr') || Module::find('hr')) {
            $query->load([
                'employee.branch',
                'employee.department',
                'employee.designation',
            ]);

            $query->employee?->loadExists('subordinates');
        }

        return $query;
    }

    /** {@inheritDoc} */
    public function getModel(): User
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return User::class;
    }

    /** {@inheritDoc} */
    public function updateProfile(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->lockForUpdate()->findOrFail(auth()->id());
            if ($user->two_factor_confirmed_at !== null
                && $user->two_factor_method === TwoFactorMethod::Email
                && isset($data['email']) && $data['email'] !== $user->email) {
                throw ValidationException::withMessages([
                    'email' => __('user::auth.two_factor_email_change_protected'),
                ]);
            }
            $trackedFields = ['name', 'username', 'email'];
            $original = $user->only($trackedFields);

            $user->update($data);
            $user = $user->fresh();

            $changedFields = collect($trackedFields)
                ->filter(fn (string $field) => array_key_exists($field, $data) && ($original[$field] ?? null) !== ($user->{$field} ?? null))
                ->values()
                ->all();

            if (! empty($changedFields)) {
                event(new ProfileUpdated(
                    user: $user,
                    changedFields: $changedFields,
                    ipAddress: request()->ip(),
                    changedAt: now(),
                    locale: app()->getLocale()
                ));
            }

            return $user;
        });
    }

    /** {@inheritDoc} */
    public function updatePassword(array $data): bool
    {
        $user = auth()->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('user::messages.current_password_incorrect'),
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('user::messages.new_password_same_current_password'),
            ]);
        }

        $isUpdated = $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        if ($isUpdated && isset($data['logout_from_other_devices']) && $data['logout_from_other_devices']) {
            $this->logoutFromOtherDevices($user);
        }

        if ($isUpdated) {
            event(new PasswordChanged(
                user: $user->fresh(),
                ipAddress: request()->ip(),
                changedAt: Carbon::now(),
                locale: locale(),
            ));
        }

        return $isUpdated;
    }

    /** {@inheritDoc} */
    public function logoutFromOtherDevices(User $user): void
    {
        $user->tokens()->where('id', '!=', optional($user->currentAccessToken())->id)->delete();
    }
}
