<?php

namespace Modules\Invoice\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\Controller;
use Modules\Invoice\Services\ThaiTax\ETaxXmlBuilder;
use Modules\Invoice\Services\ThaiTax\WithholdingTaxService;
use Modules\Support\ApiResponse;

class ThaiTaxController extends Controller
{
    public function __construct(
        private WithholdingTaxService $whtService,
        private ETaxXmlBuilder $eTaxXmlBuilder
    ) {}

    public function calculateWht(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_amount' => ['required', 'numeric', 'gt:0'],
            'wht_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_juristic_person' => ['required', 'boolean'],
        ]);

        return ApiResponse::success($this->whtService->calculate(
            baseAmount: (string) $validated['base_amount'],
            whtRatePercent: (string) $validated['wht_rate_percent'],
            isJuristicPerson: (bool) $validated['is_juristic_person']
        ));
    }

    public function buildETaxXml(Request $request): Response
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:64'],
            'issued_at' => ['required', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'seller' => ['required', 'array'],
            'buyer' => ['required', 'array'],
            'subtotal' => ['required', 'numeric'],
            'tax_total' => ['required', 'numeric'],
            'total' => ['required', 'numeric'],
        ]);

        $xml = $this->eTaxXmlBuilder->build($validated);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
