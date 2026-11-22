<?php

use Modules\Printer\Enum\PrinterPaperSize;
use Modules\Printer\Services\Agent\PrintAgentServiceInterface;
use Modules\Printer\Services\Printer\PrinterServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'permissions' => [
        'printers' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
        'print_agents' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export],
    ],

    'media_profiles' => [
        PrinterPaperSize::Paper58mm->value => [
            'paper_width_mm' => 58,
            'printable_width_mm' => 48,
            'pixel_width' => 384,
        ],

        PrinterPaperSize::Paper80mm->value => [
            'paper_width_mm' => 80,
            'printable_width_mm' => 72,
            'pixel_width' => 576,
        ],

        PrinterPaperSize::Label50x30mm->value => [
            'paper_width_mm' => 50,
            'printable_width_mm' => 46,
            'pixel_width' => 368,
        ],
    ],

    'fonts' => [
        'cairo' => [
            'family' => 'Cairo',
            'regular' => storage_path('fonts/Cairo-Regular.ttf'),
            'bold' => storage_path('fonts/Cairo-Bold.ttf'),
        ],
        'inter' => [
            'family' => 'Inter',
            'regular' => storage_path('fonts/Inter.ttf'),
            'bold' => storage_path('fonts/Inter.ttf'),
        ],
        'noto_sans_arabic' => [
            'family' => 'Noto Sans Arabic',
            'regular' => storage_path('fonts/NotoSansArabic.ttf'),
            'bold' => storage_path('fonts/NotoSansArabic.ttf'),
        ],
        'sarabun' => [
            'family' => 'Sarabun',
            'regular' => storage_path('fonts/Sarabun-Regular.ttf'),
            'bold' => storage_path('fonts/Sarabun-Bold.ttf'),
        ],
    ],

    'export_resources' => [
        'printers' => PrinterServiceInterface::class,
        'print_agents' => PrintAgentServiceInterface::class,
    ],
];
