<?php

namespace Modules\DailyFinance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Traits\HasBranch;
use Modules\DailyFinance\Traits\HasFinanceReference;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $reference_no
 * @property int $branch_id
 * @property int $created_by
 * @property Carbon $date
 * @property string $timezone
 * @property string $currency
 * @property string $request_key
 * @property string $review_hash
 * @property array<string, mixed> $snapshot
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read User $createdBy
 *
 * @mixin Builder
 */
class DayClosing extends Model
{
    use HasBranch, HasCreatedBy, HasFinanceReference;

    protected $table = 'daily_finance_day_closings';

    protected $fillable = [
        self::BRANCH_COLUMN_NAME,
        'date',
        'timezone',
        'currency',
        'request_key',
        'review_hash',
        'snapshot',
        'notes',
    ];

    protected function referencePrefix(): string
    {
        return 'DC';
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'snapshot' => 'array',
        ];
    }

    /**
     * Approved snapshots are audit evidence; later source activity belongs to the live overview.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Day closing records are immutable.'));
        static::deleting(fn () => throw new LogicException('Day closing records are immutable.'));
    }
}
