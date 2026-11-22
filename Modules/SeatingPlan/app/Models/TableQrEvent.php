<?php

namespace Modules\SeatingPlan\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Models\Order;
use Modules\SeatingPlan\Enums\TableQrEventType;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $table_id
 * @property TableQrEventType $event_type
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $cart_uuid
 * @property int|null $order_id
 * @property int|null $created_by
 * @property-read User|null $createdBy
 * @property Carbon|null $created_at
 */
class TableQrEvent extends Model
{
    use HasCreatedBy;

    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'table_id',
        'event_type',
        'ip_address',
        'user_agent',
        'cart_uuid',
        'order_id',
        'created_by',
    ];

    /**
     * Get related table.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get related order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withOutGlobalBranchPermission();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => TableQrEventType::class,
        ];
    }
}
