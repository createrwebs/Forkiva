<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Models\Branch;
use Modules\Core\Traits\HasTenant;

class SlipVerification extends Model
{
    use HasTenant;

    protected $table = 'payment_slip_verifications';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'order_id',
        'payment_id',
        'provider',
        'sending_bank_code',
        'trans_ref',
        'qr_payload_hash',
        'amount',
        'sender_name',
        'receiver_account',
        'status',
        'raw_response',
        'verified_at',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_response' => 'array',
            'verified_at' => 'datetime',
        ];
    }
}
