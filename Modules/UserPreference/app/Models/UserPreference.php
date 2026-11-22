<?php

namespace Modules\UserPreference\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Support\Eloquent\Model;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $user_id
 * @property string $identifier
 * @property bool $is_translatable
 * @property bool $is_encrypted
 * @property string $payload
 * @property-read User $user
 */
class UserPreference extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'identifier',
        'is_translatable',
        'is_encrypted',
        'payload',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = ['payload'];

    /**
     * Link a preference to its owning user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withoutGlobalActive()
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_translatable' => 'boolean',
            'is_encrypted' => 'boolean',
        ];
    }
}
