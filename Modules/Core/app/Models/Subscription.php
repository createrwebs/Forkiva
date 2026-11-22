<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'billing_cycle',
        'status',
        'current_period_start',
        'current_period_end',
        'grace_period_ends_at',
        'cancel_at_period_end',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }
}
