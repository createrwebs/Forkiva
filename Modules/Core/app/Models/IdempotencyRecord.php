<?php

namespace Modules\Core\Models;

use Illuminate\Support\Carbon;
use Modules\Core\Enums\IdempotencyRecordStatus;
use Modules\Support\Eloquent\Model;

/**
 * @property int $id
 * @property string $scope
 * @property string $key
 * @property string $payload_fingerprint
 * @property IdempotencyRecordStatus $status
 * @property array<string, mixed>|null $response
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class IdempotencyRecord extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'scope',
        'key',
        'payload_fingerprint',
        'status',
        'response',
        'error',
        'attempts',
        'completed_at',
        'expires_at',
    ];

    /** {@inheritDoc} */
    protected function casts(): array
    {
        return [
            'status' => IdempotencyRecordStatus::class,
            'response' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
