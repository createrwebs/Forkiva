<?php

return [
    'disk' => config('filesystems.default_private', 'local'),
    'path' => 'exports',
    'retention_days' => 7,
    'recent_limit' => 10,
    'resources' => [],
];
