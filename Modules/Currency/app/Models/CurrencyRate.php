<?php

namespace Modules\Currency\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Currency\Services\CurrencyRateExchanger;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;

/**
 * @property int $id
 * @property string $currency
 * @property float $rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CurrencyRate extends Model
{
    use HasFilters,
        HasSortBy,
        HasTagsCache;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'currency',
        'rate',
    ];

    /**
     * Perform any actions required after the model boots.
     */
    public static function booted(): void
    {
        static::saved(function ($currencyRate) {
            Cache::forget(md5("currency_rate.$currencyRate->currency"));
        });
    }

    /**
     * Refresh all supported currencies exchange rate.
     */
    public static function refreshRates(CurrencyRateExchanger $exchanger): void
    {
        $fromCurrency = setting('default_currency');
        foreach (setting('supported_currencies') as $toCurrency) {
            $rate = $exchanger->exchange($fromCurrency, $toCurrency);
            static::where('currency', $toCurrency)->first()->update(['rate' => $rate]);
        }
    }

    /**
     * Get currency rate for the given currency.
     */
    public static function for(string $currency): int|float
    {
        return Cache::rememberForever(md5("currency_rate.$currency"), function () use ($currency) {
            return static::where('currency', $currency)->value('rate') ?? 1;
        });
    }

    /**
     * Get cached system rates keyed by currency.
     *
     * @param  Collection<int, string>|array<int, string>  $currencies
     */
    public static function list(Collection|array $currencies): Collection
    {
        $currencyCodes = collect($currencies)
            ->map(fn (string $currency): string => strtoupper($currency))
            ->sort()
            ->values();

        return Cache::tags('currency_rates')
            ->rememberForever(
                makeCacheKey([
                    'currency_rates',
                    $currencyCodes->implode(','),
                    'list',
                ], false),
                fn () => static::query()
                    ->whereIn('currency', $currencyCodes)
                    ->get(['currency', 'rate', 'updated_at'])
                    ->mapWithKeys(fn (CurrencyRate $rate): array => [strtoupper($rate->currency) => [
                        'rate' => (string) $rate->getRawOriginal('rate'),
                        'updated_at' => dateTimeFormat($rate->updated_at),
                    ]])
            );
    }

    /**
     * Get default allowed filters
     */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $query) use ($value) {
            $query->like('currency', $value)->orLike('rate', $value);
        });
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'currency',
            'rate',
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'float',
        ];
    }
}
