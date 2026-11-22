<?php

return [
    'category' => 'Category',
    'categories' => 'Categories',

    'table' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'parent' => 'Parent Category',
        'menu' => 'Menu',
        'products_count' => 'Products Count',
    ],

    'buttons' => [
        'add_root_category' => 'Add Root Category',
        'add_sub_category' => 'Add Sub Category',
        'expand_all' => 'Expand All',
        'collapse_all' => 'Collapse All',
    ],

    'form' => [
        'create_root_category' => 'Create Root Category',
        'create_sub_category' => 'Create Sub Category',
    ],
    'import' => [
        'parent_slug' => 'Parent slug',
        'self_parent' => 'A category cannot be its own parent.',
        'missing_parent' => 'Parent slug was not found in this menu or import file.',
        'cycle' => 'The parent hierarchy contains a cycle.',
        'invalid_parent' => 'The parent category in this file has validation errors.',
        'exists' => 'This slug already exists in the menu.',
        'missing' => 'No category matches this slug.',
        'cannot_create' => 'You cannot create categories.',
        'cannot_update' => 'You cannot update categories.',
    ],
];
