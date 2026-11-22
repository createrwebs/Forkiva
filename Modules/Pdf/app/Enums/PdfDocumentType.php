<?php

namespace Modules\Pdf\Enums;

enum PdfDocumentType: string
{
    case Invoice = 'invoice';
    case OrderPrint = 'order_print';
    case PayrollRun = 'payroll_run';
    case Payslip = 'payslip';
    case PayrollReport = 'payroll_report';
}
