<?php

namespace Modules\Tax\Support;

use BackedEnum;
use Illuminate\Support\Collection;

final class TaxSummary
{
    /**
     * Aggregate persisted product/line taxes and global taxes without mixing their scopes.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function summarize(Collection $taxes, string $lineForeignKey): Collection
    {
        return $taxes
            ->groupBy(function ($tax) use ($lineForeignKey): string {
                $scope = is_null($tax->getAttribute($lineForeignKey)) ? 'global' : 'product';
                $type = $tax->type instanceof BackedEnum ? $tax->type->value : (string) $tax->type;
                $identity = $tax->tax_id ?? implode('|', [$tax->name, $tax->rate, $type]);

                return $scope.':'.$identity;
            })
            ->map(function (Collection $group, string $key): array {
                $tax = $group->first();
                $scope = str($key)->before(':')->toString();
                $type = $tax->type instanceof BackedEnum ? $tax->type->value : (string) $tax->type;

                return [
                    'key' => $key,
                    'tax_id' => $tax->tax_id,
                    'name' => $tax->name,
                    'rate' => (float) $tax->rate,
                    'type' => $type,
                    'scope' => $scope,
                    'compound' => (bool) $tax->compound,
                    'amount' => (float) $group->sum(fn ($row) => $row->amount->amount()),
                    'currency' => $tax->currency,
                    'currency_rate' => (float) $tax->currency_rate,
                ];
            })
            ->values();
    }
}
