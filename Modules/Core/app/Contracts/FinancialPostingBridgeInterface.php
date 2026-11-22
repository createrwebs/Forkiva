<?php

namespace Modules\Core\Contracts;

use JsonException;

interface FinancialPostingBridgeInterface
{
    /**
     * Record a financial source event for optional Accounting processing.
     *
     * Core workflows must continue even when the Accounting add-on is missing,
     * disabled or temporarily unavailable.
     *
     * @param  array<string,mixed>  $payload
     *
     * @throws JsonException
     */
    public function recordEvent(string $sourceType, int $sourceId, string $event, array $payload = []): void;

    /**
     * Record a non-throwing operational failure for Accounting diagnostics.
     *
     * The implementation must not propagate persistence or Accounting errors
     * back to the operational workflow.
     *
     * @param  array<string,mixed>  $payload
     */
    public function recordFailure(string $sourceType, int $sourceId, string $event, string $errorCode, string $message, array $payload = []): void;
}
