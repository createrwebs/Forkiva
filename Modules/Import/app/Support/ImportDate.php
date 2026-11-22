<?php

namespace Modules\Import\Support;

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportDate
{
    /**
     * Excel stores dates as day counts; leave other input unchanged for normal validation.
     */
    public static function normalizeSerial(?string $value, int $calendar = ExcelDate::CALENDAR_WINDOWS_1900): ?string
    {
        if ($value === null || ! preg_match('/^\d+(?:\.\d+)?$/', trim($value))) {
            return $value;
        }

        $serial = (float) trim($value);
        if ($serial < 1 || $serial > 2958465) {
            return $value;
        }

        $previousCalendar = ExcelDate::getExcelCalendar();
        try {
            ExcelDate::setExcelCalendar($calendar);

            return ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d');
        } finally {
            ExcelDate::setExcelCalendar($previousCalendar);
        }
    }
}
