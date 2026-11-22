<?php

namespace Modules\Branch\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string $currency
 */
trait HasBranchCurrency
{
    /**
     * Get currency
     */
    public function currency(): Attribute
    {
        return Attribute::get(
            fn () => $this->relationLoaded('branch')
                ? ($this->branch?->currency ?: setting('default_currency', 'JOD'))
                : setting('default_currency', 'JOD')
        );
    }
}
