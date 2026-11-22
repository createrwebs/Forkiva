<?php

namespace Modules\Order\Support;

use Modules\Currency\Currency;
use Modules\Tax\Models\Tax;

final class PricingCatalog
{
    public static function branch(int $id, string $currency): array
    {
        return [
            'precision' => Currency::subunit($currency),
            'global_taxes' => self::taxes(
                Tax::query()
                    ->withOutGlobalBranchPermission()
                    ->global()
                    ->where(fn ($q) => $q->where('branch_id', $id)->orWhereNull('branch_id'))
                    ->orderBy('compound')
                    ->orderBy('id')
                    ->get()
            ),
        ];
    }

    public static function taxes($taxes): array
    {
        return $taxes->map(fn ($tax) => [
            'id' => $tax->id,
            'name' => $tax->name,
            'rate' => (float) $tax->rate,
            'type' => $tax->type->value,
            'compound' => (bool) $tax->compound,
            'order_types' => $tax->order_types ?: [],
        ])->values()->all();
    }
}
