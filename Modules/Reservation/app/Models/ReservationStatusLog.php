<?php

namespace Modules\Reservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Reservation\Database\Factories\ReservationStatusLogFactory;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Support\Eloquent\Model;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $reservation_id
 * @property ReservationStatus|null $old_status
 * @property ReservationStatus $new_status
 * @property string|null $note
 * @property string|null $reason
 * @property int|null $changed_by
 * @property Carbon $changed_at
 * @property-read Reservation $reservation
 * @property-read User|null $changedBy
 */
class ReservationStatusLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reservation_id',
        'old_status',
        'new_status',
        'note',
        'reason',
        'changed_by',
        'changed_at',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReservationStatusLogFactory
    {
        return ReservationStatusLogFactory::new();
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
     * Get changing user.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by')
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
            'old_status' => ReservationStatus::class,
            'new_status' => ReservationStatus::class,
            'changed_at' => 'datetime',
        ];
    }
}
