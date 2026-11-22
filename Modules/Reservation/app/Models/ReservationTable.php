<?php

namespace Modules\Reservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Reservation\Database\Factories\ReservationTableFactory;
use Modules\SeatingPlan\Models\Table;
use Modules\Support\Eloquent\Model;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $reservation_id
 * @property int $table_id
 * @property Carbon|null $assigned_at
 * @property int|null $assigned_by
 * @property-read Reservation $reservation
 * @property-read Table $table
 * @property-read User|null $assignedBy
 */
class ReservationTable extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reservation_id',
        'table_id',
        'assigned_at',
        'assigned_by',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReservationTableFactory
    {
        return ReservationTableFactory::new();
    }

    /**
     * Get reservation.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get assigned table.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get assigning user.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }
}
