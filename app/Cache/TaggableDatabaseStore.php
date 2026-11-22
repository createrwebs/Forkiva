<?php

namespace App\Cache;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Cache\TagSet;

class TaggableDatabaseStore extends DatabaseStore
{
    /**
     * Use Laravel's tag namespaces while retaining database persistence and locks.
     */
    public function tags($names): TaggedCache
    {
        return new TaggedCache($this, new TagSet($this, is_array($names) ? $names : func_get_args()));
    }
}
