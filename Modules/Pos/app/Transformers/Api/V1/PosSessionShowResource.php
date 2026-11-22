<?php

namespace Modules\Pos\Transformers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Pos\Models\PosSession;
use Modules\Support\Money;

/** @mixin PosSession */
class PosSessionShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $payments = $this->meta['payment_reconciliation'] ?? null;
        $custody = $this->meta['closing_custody'] ?? null;

        return [
            ...(new PosSessionResource($this))->resolve(),
            'opening_float' => $this->opening_float,
            'declared_cash' => $this->declared_cash,
            'system_cash_sales' => $this->system_cash_sales,
            'cash_over_short' => $this->cash_over_short,
            'system_card_sales' => $this->system_card_sales,
            'system_other_sales' => $this->system_other_sales,
            // These legacy sales-named fields contain collections, including receipts for older orders.
            'total_sales' => $this->total_sales,
            'total_collections' => $this->total_sales,
            'total_refunds' => $this->total_refunds,
            'orders_count' => $this->orders_count,
            'notes' => $this->notes,
            'closing_custody' => $custody ? [
                'version' => $custody['version'],
                'responsible_cashier' => $custody['responsible_cashier'],
                'counted_by' => $custody['counted_by'],
                'approved_by' => $custody['approved_by'],
                'approved_at' => dateTimeFormat(Carbon::parse($custody['approved_at'])),
                'on_behalf' => $custody['on_behalf'],
                'closing_reason' => $custody['closing_reason'],
                'variance_reason' => $custody['variance_reason'],
            ] : null,
            'payment_reconciliation' => $payments ? [
                'version' => $payments['version'],
                'methods' => array_map(fn (array $method) => [
                    'method' => PaymentMethod::from($method['method'])->toTrans(),
                    'collections' => new Money((float) $method['collections'], $payments['currency']),
                    'refunds' => new Money((float) $method['refunds'], $payments['currency']),
                    'net' => new Money((float) $method['net'], $payments['currency']),
                ], $payments['methods']),
                'legacy_cash_payments_count' => $payments['legacy_cash_payments_count'],
                'unattributed_order_payments_count' => $payments['unattributed_order_payments_count'],
                'currency_mismatch_payments_count' => $payments['currency_mismatch_payments_count'],
            ] : null,
            'cash_reconciliation' => isset($this->meta['cash_reconciliation']) ? [
                'cash_in' => new Money((float) $this->meta['cash_reconciliation']['cash_in'], $this->currency),
                'cash_out' => new Money((float) $this->meta['cash_reconciliation']['cash_out'], $this->currency),
                'expected_cash' => new Money((float) $this->meta['cash_reconciliation']['expected_cash'], $this->currency),
            ] : null,
        ];
    }
}
