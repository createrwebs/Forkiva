<?php

namespace Modules\Tool\Services\Database\Drivers;

interface DatabaseDriverInterface
{
    /**
     * Get the file extension used by backups from this driver.
     */
    public function extension(): string;

    /**
     * Describe whether backup and restore dependencies are available.
     *
     * @return array{backup_available: bool, restore_available: bool, missing_tools: array<int, string>, format: string}
     */
    public function capabilities(array $config): array;

    /**
     * Create a consistent backup at the supplied destination path.
     *
     * @return string Engine used to create the backup.
     */
    public function backup(array $config, string $destination): string;

    /**
     * Validate and restore a backup into the configured database.
     *
     * @return string Engine used to restore the backup.
     */
    public function restore(array $config, string $source): string;
}
