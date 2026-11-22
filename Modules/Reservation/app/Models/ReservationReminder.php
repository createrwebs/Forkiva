<?php

namespace Modules\Reservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Reservation\Database\Factories\ReservationReminderFactory;
use Modules\Reservation\Enums\ReservationReminderStatus;
use Modules\Support\Eloquent\Model;

/**
 * @property int $id
 * @property int $reservation_id
 * @property string $channel
 * @property string $reminder_type
 * @property Carbon $scheduled_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property ReservationReminderStatus $status
 * @property string|null $error_message
 * @property-read Reservation $reservation
 */
class ReservationReminder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reservation_id',
        'channel',
        'reminder_type',
        'scheduled_at',
        'sent_at',
        'failed_at',
        'status',
        'error_message',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReservationReminderFactory
    {
        return ReservationReminderFactory::new();
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
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'status' => ReservationReminderStatus::class,
        ];
    }
}
