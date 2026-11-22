<?php

namespace Modules\Notification\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Notification\Enums\NotificationPushTokenClient;
use Modules\Service\Enums\PushNotificationProvider;
use Modules\Support\Eloquent\Model;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $user_id
 * @property PushNotificationProvider|string $provider
 * @property string $token
 * @property string|null $device_id
 * @property string|null $device_name
 * @property string|null $platform
 * @property NotificationPushTokenClient|string $client
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class NotificationPushToken extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'token',
        'device_id',
        'device_name',
        'platform',
        'client',
        'last_used_at',
    ];

    /**
     * Get the token user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PushNotificationProvider::class,
            'client' => NotificationPushTokenClient::class,
            'last_used_at' => 'datetime',
        ];
    }
}
