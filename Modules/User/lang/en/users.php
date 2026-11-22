<?php

return [
    'users' => 'Users',
    'user' => 'User',

    'import' => [
        'exists' => 'A user with this username already exists.',
        'missing' => 'No user matches this username.',
        'missing_id' => 'No user matches this ID.',
        'main_user' => 'The main user cannot be changed by import.',
        'branch_required' => 'A branch is required for this role.',
        'password_setup' => 'New users receive a random password. Do not include passwords in the file. Set their password through account editing or password recovery before they sign in.',
    ],

    'table' => [
        'name' => 'Name',
        'email' => 'Email',
        'username' => 'Username',
        'gender' => 'Gender',
        'phone_country_iso_code' => 'Phone Country ISO Code',
        'phone' => 'Phone',
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
        'role' => 'Role',
    ],

    'form' => [
        'cards' => [
            'user_information' => 'User information',
            'security' => 'Security',
            'kitchen_configuration' => 'Kitchen Configuration',
        ],
    ],
];
