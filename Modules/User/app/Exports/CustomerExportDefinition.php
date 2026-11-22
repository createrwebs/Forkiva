<?php

namespace Modules\User\Exports;

use Modules\Export\Contracts\ModelExportDefinitionInterface;

class CustomerExportDefinition implements ModelExportDefinitionInterface
{
    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'user::customers.table.name'],
            ['path' => 'username', 'label' => 'user::customers.table.username'],
            ['path' => 'email', 'label' => 'user::customers.table.email'],
            ['path' => 'gender', 'label' => 'user::customers.table.gender'],
            ['path' => 'phone_country_iso_code', 'label' => 'user::customers.table.phone_country_iso_code'],
            ['path' => 'phone', 'label' => 'user::customers.table.phone'],
            ['path' => 'national_phone', 'label' => 'user::customers.table.national_phone'],
            ['path' => 'birthdate', 'label' => 'user::customers.table.birthdate', 'type' => 'date'],
            ['path' => 'note', 'label' => 'user::customers.table.note'],
            ['path' => 'customer_type', 'label' => 'user::customers.table.customer_type'],
            ['path' => 'registration_number', 'label' => 'user::customers.table.registration_number'],
            ['path' => 'vat_tin', 'label' => 'user::customers.table.vat_tin'],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }
}
