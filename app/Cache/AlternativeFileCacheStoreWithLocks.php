<?php

namespace App\Cache;

use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
use Illuminate\Cache\FileStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Filesystem\Filesystem;

class AlternativeFileCacheStoreWithLocks extends AlternativeFileCacheStore implements LockProvider
{
    private FileStore $lockStore;

    /**
     * Create an alternative file cache store backed by Laravel's atomic file locks.
     */
    public function __construct(
        $db,
        string $prefix,
        string $directory,
        Filesystem $files,
        ?int $filePermission = null,
        array|bool|null $serializableClasses = true,
    ) {
        parent::__construct($db, $prefix, $directory, $filePermission, $serializableClasses);

        $this->lockStore = new FileStore(
            $files,
            $directory,
            $filePermission,
            $serializableClasses,
        );
    }

    /**
     * Get an atomic file lock instance.
     */
    public function lock($name, $seconds = 0, $owner = null): Lock
    {
        return $this->lockStore->lock($name, $seconds, $owner);
    }

    /**
     * Restore a lock instance using its owner identifier.
     */
    public function restoreLock($name, $owner): Lock
    {
        return $this->lockStore->restoreLock($name, $owner);
    }

    /**
     * Set the directory where locks should be stored.
     */
    public function setLockDirectory($lockDirectory): static
    {
        $this->lockStore->setLockDirectory($lockDirectory);

        return $this;
    }
}
