<?php

namespace Modules\DailyFinance\Traits;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @mixin Model */
trait HasFinanceReference
{
    /**
     * Persist the public reference after ID allocation, within the owning service's transaction.
     */
    protected static function bootHasFinanceReference(): void
    {
        static::creating(function (self $record): void {
            // A replicated record must receive its own reference.
            $record->setAttribute('reference_no', null);
        });

        static::created(function (self $record): void {
            $reference = $record->referencePrefix().'-'.str_pad((string) $record->getKey(), 4, '0', STR_PAD_LEFT);

            // Initial identity assignment must not rewrite immutable snapshots or their audit timestamps.
            $record->newModelQuery()->whereKey($record->getKey())->toBase()
                ->update(['reference_no' => $reference]);
            $record->setAttribute('reference_no', $reference)->syncOriginalAttribute('reference_no');
        });

        static::updating(function (self $record): void {
            if ($record->isDirty('reference_no')) {
                throw new LogicException('Daily finance references are immutable.');
            }
        });
    }

    abstract protected function referencePrefix(): string;
}
