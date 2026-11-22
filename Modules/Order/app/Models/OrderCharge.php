<?php

namespace Modules\Order\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property int $order_id
 * @property string $code
 * @property array $name
 * @property string $currency
 * @property float $currency_rate
 * @property Money $unit_amount
 * @property float $quantity
 * @property Money $amount
 * @property bool $taxable
 * @property array|null $tax_metadata
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 */
class OrderCharge extends Model
{
    use Translatable;

    protected $fillable = [
        'order_id',
        'code',
        'name',
        'currency',
        'currency_rate',
        'unit_amount',
        'quantity',
        'amount',
        'taxable',
        'tax_metadata',
        'meta',
    ];

    protected array $translatable = ['name'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)
            ->withOutGlobalBranchPermission();
    }

    public function unitAmount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    public function amount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'tax_metadata' => 'array',
            'taxable' => 'boolean',
            'quantity' => 'float',
        ];
    }
}
