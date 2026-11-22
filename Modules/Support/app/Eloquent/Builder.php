<?php

namespace Modules\Support\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Support\Traits\HasTagsCache;

class Builder extends EloquentBuilder
{
    /**
     * Prevent recursive event-aware deletes when a hydrated model performs its
     * final hard-delete query.
     */
    protected static bool $deletingWithModelEvents = false;

    /** {@inheritDoc} */
    public function delete(): mixed
    {
        $model = $this->getModel();

        if (! static::$deletingWithModelEvents && $this->usesActivityLog($model)) {
            return $this->deleteWithModelEvents();
        }

        $delete = parent::delete();
        $this->clearModelTaggedCache($model);

        return $delete;
    }

    /** {@inheritDoc} */
    public function update(array $values): int
    {
        $update = parent::update($values);
        $this->clearModelTaggedCache($this->getModel());

        return $update;
    }

    /**
     * Delete matched models through Eloquent model instances so model events
     * and activity logs are recorded for each row.
     */
    protected function deleteWithModelEvents(): int
    {
        $deleted = 0;
        $model = $this->getModel();

        static::$deletingWithModelEvents = true;

        try {
            $this->chunkById(100, function (Collection $models) use (&$deleted): void {
                $models->each(function (Model $model) use (&$deleted): void {
                    if ($model->delete()) {
                        $deleted++;
                    }
                });
            }, $model->getQualifiedKeyName(), $model->getKeyName());
        } finally {
            static::$deletingWithModelEvents = false;
            $this->clearModelTaggedCache($model);
        }

        return $deleted;
    }

    /**
     * Determine whether the model records activity through model events.
     */
    protected function usesActivityLog(Model $model): bool
    {
        return in_array(HasActivityLog::class, class_uses_recursive($model), true);
    }

    /**
     * Clear tagged cache for models that opt in to model tag caching.
     */
    protected function clearModelTaggedCache(Model $model): void
    {
        if (in_array(HasTagsCache::class, class_uses_recursive($model), true)) {
            $model->clearModelTaggedCache();
        }
    }
}
