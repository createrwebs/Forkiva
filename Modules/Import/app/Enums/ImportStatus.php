<?php

namespace Modules\Import\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ImportStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Uploaded = 'uploaded';
    case Staging = 'staging';
    case Staged = 'staged';
    case Validating = 'validating';
    case ValidationFailed = 'validation_failed';
    case Ready = 'ready';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case PartiallyCompleted = 'partially_completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * These phases permit remapping; the service also checks that no rows were applied.
     */
    public function allowsMapping(): bool
    {
        return in_array($this, [
            self::Uploaded,
            self::Staged,
            self::ValidationFailed,
            self::Ready,
            self::Failed,
        ], true);
    }

    /**
     * Preview changes and validation share the same editable states.
     */
    public function allowsValidation(): bool
    {
        return in_array($this, [
            self::Staged,
            self::ValidationFailed,
            self::Ready,
        ], true);
    }

    /**
     * A failed import resumes from its persisted progress.
     */
    public function allowsRetry(): bool
    {
        return in_array($this, [self::Failed, self::PartiallyCompleted], true);
    }

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'import::imports.statuses';
    }
}
