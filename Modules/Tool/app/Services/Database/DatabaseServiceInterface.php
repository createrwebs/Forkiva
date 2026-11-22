<?php

namespace Modules\Tool\Services\Database;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface DatabaseServiceInterface
{
    /**
     * Get database tool meta.
     */
    public function index(): array;

    /**
     * Create database backup.
     */
    public function backup(): array;

    /**
     * Restore database from uploaded SQL file.
     */
    public function restore(UploadedFile $file): array;

    /**
     * Restore database from existing backup file name.
     *
     * @throws FileNotFoundException
     */
    public function restoreFromBackup(string $fileName): array;

    /**
     * Download backup file.
     */
    public function download(string $fileName): BinaryFileResponse;
}
