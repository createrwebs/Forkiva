<?php

return [
    'disk' => config('filesystems.default_private', 'local'),
    'path' => 'imports',
    'chunk_size' => 500,
    'validation_batch_size' => 200,
    'apply_batch_size' => 100,
    'finalization_batch_size' => 100,
    'retention_days' => 30,
    'resources' => [],
];
