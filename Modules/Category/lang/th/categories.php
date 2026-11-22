<?php

return [
    'category' => 'หมวดหมู่',
    'categories' => 'หมวดหมู่',

    'table' => [
        'name' => 'ชื่อ',
        'slug' => 'Slug',
        'parent' => 'หมวดหมู่หลัก',
        'menu' => 'เมนู',
        'products_count' => 'จำนวนสินค้า',
    ],

    'buttons' => [
        'add_root_category' => 'เพิ่มหมวดหมู่หลัก',
        'add_sub_category' => 'เพิ่มหมวดหมู่ย่อย',
        'expand_all' => 'ขยายทั้งหมด',
        'collapse_all' => 'ย่อทั้งหมด',
    ],

    'form' => [
        'create_root_category' => 'สร้างหมวดหมู่หลัก',
        'create_sub_category' => 'สร้างหมวดหมู่ย่อย',
    ],
    'import' => [
        'parent_slug' => 'Slug หมวดหมู่หลัก',
        'self_parent' => 'หมวดหมู่ไม่สามารถเป็นหมวดหมู่หลักของตัวเองได้',
        'missing_parent' => 'ไม่พบ Slug หมวดหมู่หลักในเมนูนี้หรือไฟล์นำเข้า',
        'cycle' => 'ลำดับชั้นหมวดหมู่หลักมีวงวน',
        'invalid_parent' => 'หมวดหมู่หลักในไฟล์นี้มีข้อผิดพลาดในการตรวจสอบ',
        'exists' => 'Slug นี้มีอยู่แล้วในเมนู',
        'missing' => 'ไม่มีหมวดหมู่ที่ตรงกับ Slug นี้',
        'cannot_create' => 'คุณไม่มีสิทธิ์สร้างหมวดหมู่',
        'cannot_update' => 'คุณไม่มีสิทธิ์แก้ไขหมวดหมู่',
    ],
];
