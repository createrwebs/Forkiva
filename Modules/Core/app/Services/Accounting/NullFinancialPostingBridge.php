<?php

namespace Modules\Core\Services\Accounting;

use Modules\Core\Contracts\FinancialPostingBridgeInterface;

class NullFinancialPostingBridge implements FinancialPostingBridgeInterface
{
    /** {@inheritDoc} */
    public function recordEvent(string $sourceType, int $sourceId, string $event, array $payload = []): void {}

    /** {@inheritDoc} */
    public function recordFailure(string $sourceType, int $sourceId, string $event, string $errorCode, string $message, array $payload = []): void {}
}
