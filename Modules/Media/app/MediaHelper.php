<?php

namespace Modules\Media;

class MediaHelper
{
    /**
     * The max file size
     */
    public static int $maxFileSize = 30720;

    /**
     * Resolve preview image for the given extension.
     */
    public static function resolvePreviewImage(?string $extension = null): string
    {
        return url('/assets/media/'.($extension ?? 'folder').'.png');
    }
}
