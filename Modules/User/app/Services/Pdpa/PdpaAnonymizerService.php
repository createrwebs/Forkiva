<?php

namespace Modules\User\Services\Pdpa;

use Modules\User\Models\User;

/**
 * Handles Thailand PDPA consent recording and right-to-erasure anonymization
 * while preserving the user primary key for statutory tax/invoice history.
 */
class PdpaAnonymizerService
{
    public function recordConsent(User $user): User
    {
        $user->forceFill(['pdpa_consent_at' => now()])->save();

        return $user;
    }

    public function anonymize(User $user): User
    {
        $user->forceFill([
            'name' => "Anonymized User #{$user->id}",
            'email' => "anonymized-{$user->id}@anonymized.local",
            'phone' => null,
            'phone_country_iso_code' => null,
            'birthdate' => null,
            'note' => null,
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'pdpa_anonymized_at' => now(),
        ])->save();

        return $user;
    }
}
