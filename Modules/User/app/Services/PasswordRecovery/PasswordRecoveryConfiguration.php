<?php

namespace Modules\User\Services\PasswordRecovery;

class PasswordRecoveryConfiguration
{
    /**
     * Return recovery methods allowed by both feature and delivery settings.
     *
     * @return list<'email'|'sms'>
     */
    public function channels(): array
    {
        if (! filter_var(setting('password_recovery_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        return array_values(
            array_filter(['email', 'sms'],
                fn (string $channel): bool => filter_var(setting("password_recovery_{$channel}_enabled", true),
                    FILTER_VALIDATE_BOOLEAN
                )
                && filter_var(setting("services.notifications.$channel.enabled", false), FILTER_VALIDATE_BOOLEAN)
            ));
    }
}
