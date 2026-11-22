<?php

return [
    'customers' => 'Customers',
    'customer' => 'Customer',

    'import' => [
        'exists' => 'This customer already exists.',
        'missing' => 'No matching customer was found.',
        'missing_id' => 'No customer with this ID was found.',
    ],

    'table' => [
        'name' => 'Name',
        'email' => 'Email',
        'username' => 'Username',
        'gender' => 'Gender',
        'customer_type' => 'Customer Type',
        'phone' => 'Phone',
        'phone_country_iso_code' => 'Phone Country ISO Code',
        'national_phone' => 'National Phone',
        'birthdate' => 'Birthdate',
        'note' => 'Note',
        'registration_number' => 'Registration Number',
        'vat_tin' => 'VAT / TIN',
        'profile_photo_path' => 'Profile Photo Path',
        'printer' => 'Printer',
        'category_slugs' => 'Category Slugs',
        'role_names' => 'Roles',
    ],

    'filters' => [
        'gender' => 'Gender',
    ],

    'form' => [
        'cards' => [
            'user_information' => 'Customer information',
            'user_details' => 'Customer Details',
            'business_details' => 'Business Details',
            'security' => 'Security',
            'avatar' => 'Avatar',
        ],
    ],

    'pdpa' => [
        'consent' => 'PDPA Consent',
        'consent_hint' => 'Record when the customer consented to personal-data processing.',
        'anonymized_name' => 'Anonymized Customer',
        'anonymized' => 'Customer personal data has been anonymized.',
        'export_title' => 'Personal Data Export',
    ],
];
