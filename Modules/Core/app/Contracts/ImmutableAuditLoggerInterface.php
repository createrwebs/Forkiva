<?php

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\ImmutableAuditEvent;

interface ImmutableAuditLoggerInterface
{
    /**
     * Append immutable evidence for a sensitive action.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        string $reason,
        ?int $actorId = null,
        ?Model $subject = null,
        ?int $branchId = null,
        ?array $before = null,
        ?array $after = null,
        array $metadata = [],
    ): ImmutableAuditEvent;
}
