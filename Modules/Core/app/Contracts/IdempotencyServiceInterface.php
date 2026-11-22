<?php

namespace Modules\Core\Contracts;

use Closure;

interface IdempotencyServiceInterface
{
    /**
     * Execute an operation once for a scope/key/payload tuple.
     *
     * The callback must return a JSON-serializable associative array. Replays receive
     * the original response. Reusing the key with another payload raises a conflict.
     *
     * @param  array<string, mixed>  $payload
     * @param  Closure(): array<string, mixed>  $operation
     * @return array{response: array<string, mixed>, replayed: bool}
     */
    public function execute(
        string $scope,
        string $key,
        array $payload,
        Closure $operation,
        ?int $expiresInSeconds = null,
    ): array;
}
