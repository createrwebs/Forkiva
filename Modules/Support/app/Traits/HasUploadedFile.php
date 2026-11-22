<?php

namespace Modules\Support\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Media\MediaHelper;

/**
 * @property-read string $human_size
 * @property-read string $preview_image_url
 */
trait HasUploadedFile
{
    /**
     * Get the file's preview image.
     */
    public function previewImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isImage() && isset($this->{$this->fileColumn ?? 'document'}['url'])
                ? $this->{$this->fileColumn ?? 'document'}['url']
                : MediaHelper::resolvePreviewImage(
                    $this->isImage()
                        ? 'image'
                        : $this->{$this->fileColumn ?? 'document'}['extension']
                )
        );
    }

    /**
     * Determine if the file type is image.
     */
    public function isImage(): bool
    {
        return strtok($this->{$this->fileColumn ?? 'document'}['mime'], '/') === 'image';
    }

    /**
     * Get the file's human size.
     */
    public function humanSize(): Attribute
    {
        return Attribute::make(
            get: fn () => humanFileSize($this->{$this->fileColumn ?? 'document'}['size'] ?? 0)
        );
    }
}
