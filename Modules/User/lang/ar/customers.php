<?php

return [
    'customers' => 'العملاء',
    'customer' => 'العميل',

    'import' => [
        'exists' => 'هذا العميل موجود بالفعل.',
        'missing' => 'لم يتم العثور على عميل مطابق.',
        'missing_id' => 'لم يتم العثور على عميل بهذا المعرّف.',
    ],

    'table' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'username' => 'اسم المستخدم',
        'gender' => 'الجنس',
        'customer_type' => 'نوع العميل',
        'phone' => 'رقم الهاتف',
        'phone_country_iso_code' => 'رمز الدولة للهاتف',
        'national_phone' => 'الهاتف المحلي',
        'birthdate' => 'تاريخ الميلاد',
        'note' => 'ملاحظة',
        'registration_number' => 'رقم التسجيل',
        'vat_tin' => 'الرقم الضريبي',
        'profile_photo_path' => 'مسار الصورة الشخصية',
        'printer' => 'الطابعة',
        'category_slugs' => 'معرفات الفئات',
        'role_names' => 'الأدوار',
    ],

    'filters' => [
        'gender' => 'الجنس',
    ],

    'form' => [
        'cards' => [
            'user_information' => 'معلومات العميل',
            'user_details' => 'تفاصيل العميل',
            'business_details' => 'بيانات الشركة',
            'security' => 'الأمان',
            'avatar' => 'الصورة الشخصية',
        ],
    ],

    'pdpa' => [
        'consent' => 'موافقة PDPA',
        'consent_hint' => 'سجّل تاريخ موافقة العميل على معالجة البيانات الشخصية.',
        'anonymized_name' => 'عميل مجهول الهوية',
        'anonymized' => 'تم إخفاء هوية البيانات الشخصية للعميل.',
        'export_title' => 'تصدير البيانات الشخصية',
    ],
];
