<?php

namespace Modules\Sms\Services\SmsProvider;

use Modules\Sms\Enums\SmsProvider;

interface SmsProviderConfiguratorServiceInterface
{
    /**
     * Resolve and configure the enabled SMS provider.
     */
    public function activeProvider(): ?SmsProvider;

    /**
     * Get the configured sender name.
     */
    public function senderName(): ?string;
}
