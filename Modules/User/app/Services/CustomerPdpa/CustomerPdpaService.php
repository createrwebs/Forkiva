<?php

namespace Modules\User\Services\CustomerPdpa;

use Illuminate\Support\Facades\DB;
use Modules\User\Models\User;
use Modules\User\Services\Customer\CustomerServiceInterface;

class CustomerPdpaService implements CustomerPdpaServiceInterface
{
    public function __construct(protected CustomerServiceInterface $customers) {}

    /** {@inheritDoc} */
    public function exportPersonalData(int $id): array
    {
        $customer = $this->customers->findOrFail($id);

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'username' => $customer->username,
            'email' => $customer->email,
            'phone' => $customer->getRawOriginal('phone'),
            'phone_country_iso_code' => $customer->phone_country_iso_code,
            'gender' => $customer->gender?->value,
            'birthdate' => $customer->birthdate?->format('Y-m-d'),
            'note' => $customer->note,
            'customer_type' => $customer->customer_type?->value,
            'address_line1' => $customer->address_line1,
            'address_line2' => $customer->address_line2,
            'city' => $customer->city,
            'state' => $customer->state,
            'postal_code' => $customer->postal_code,
            'vat_tin' => $customer->vat_tin,
            'registration_number' => $customer->registration_number,
            'pdpa_consent_at' => $customer->pdpa_consent_at?->toIso8601String(),
            'pdpa_anonymized_at' => $customer->pdpa_anonymized_at?->toIso8601String(),
            'created_at' => $customer->created_at?->toIso8601String(),
        ];
    }

    /** {@inheritDoc} */
    public function anonymize(int $id): User
    {
        $customer = $this->customers->findOrFail($id);

        return DB::transaction(function () use ($customer): User {
            // Release any enrolled factor first so the profile-change guard does
            // not block erasure of the contact details it protects.
            if (! is_null($customer->two_factor_confirmed_at)) {
                $customer->forceFill([
                    'two_factor_method' => null,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ])->saveQuietly();
            }

            // Historical invoice/order snapshots are intentionally retained to
            // satisfy tax and accounting retention duties; only live personal
            // data on the customer record is erased. forceFill is used because
            // the erasure timestamp is system-managed, not mass-assignable.
            $customer->forceFill([
                'name' => __('user::customers.pdpa.anonymized_name'),
                'username' => null,
                'email' => null,
                'phone' => null,
                'phone_country_iso_code' => null,
                'gender' => null,
                'birthdate' => null,
                'note' => null,
                'address_line1' => null,
                'address_line2' => null,
                'city' => null,
                'state' => null,
                'postal_code' => null,
                'vat_tin' => null,
                'registration_number' => null,
                'profile_photo_path' => null,
                'is_active' => false,
                'pdpa_anonymized_at' => now(),
            ])->save();

            return $customer;
        });
    }
}
