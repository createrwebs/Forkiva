<?php

namespace Modules\Media\Traits;

use Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Modules\Media\Models\Media;

trait HasMedia
{
    /**
     * The "booting" method of the trait.
     */
    public static function bootHasMedia(): void
    {
        static::saved(function (Model $model) {
            $model->syncFiles(request('files', []));
        });
    }

    /**
     * Sync files for the model.
     */
    public function syncFiles(array $files = []): void
    {
        foreach ($files as $zone => $fileIds) {
            $syncList = [];

            foreach (Arr::wrap($fileIds) as $fileId) {
                if (! empty($fileId)) {
                    $syncList[$fileId]['zone'] = $zone;
                    $syncList[$fileId]['model_type'] = $this->getMorphClass();
                }
            }

            $this->filterFiles($zone)->detach();
            $this->filterFiles($zone)->attach($syncList);
        }
    }

    /**
     * Filter files by zone.
     */
    public function filterFiles(string $zone): MorphToMany
    {
        return $this->files()->wherePivot('zone', $zone);
    }

    /**
     * Get all the files for the model.
     */
    public function files(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'model', 'model_files')
            ->withPivot(['id', 'zone'])
            ->withOutGlobalScope('created_by')
            ->withTimestamps();
    }
}
