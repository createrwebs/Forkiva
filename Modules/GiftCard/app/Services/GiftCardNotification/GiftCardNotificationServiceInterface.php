<?php

namespace Modules\GiftCard\Services\GiftCardNotification;

use Modules\GiftCard\Events\GiftCardExpired;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardTransaction;

interface GiftCardNotificationServiceInterface
{
    /**
     * Send the gift-card issued notification.
     */
    public function sendIssued(GiftCard $giftCard): void;

    /**
     * Send the gift-card redeemed notification.
     */
    public function sendRedeemed(GiftCardTransaction $transaction): void;

    /**
     * Send the gift-card recharged notification.
     */
    public function sendRecharged(GiftCardTransaction $transaction): void;

    /**
     * Send the gift-card adjusted notification.
     */
    public function sendAdjusted(GiftCardTransaction $transaction): void;

    /**
     * Send the gift-card expired notification.
     */
    public function sendExpired(GiftCardExpired $event): void;
}
