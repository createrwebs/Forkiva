<?php

namespace Modules\GiftCard\Services\GiftCardCode;

interface GiftCardCodeServiceInterface
{
    /**
     * Generate a unique gift card code.
     */
    public function generate(?string $prefix = null): string;

    /**
     * Normalize a user-entered gift card code for resilient lookup.
     */
    public function normalize(string $code): string;
}
