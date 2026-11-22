<?php

namespace Modules\Notification\Tests\Unit;

use Modules\Notification\Services\LineMessaging\LineMessagingService;
use Modules\Sms\Enums\SmsProvider;
use PHPUnit\Framework\TestCase;

class LineMessagingServiceTest extends TestCase
{
    public function test_verifies_valid_line_webhook_hmac_signature(): void
    {
        $service = new LineMessagingService;
        $secret = 'line-channel-secret-123';
        $body = '{"events":[{"type":"message"}]}';
        $validSignature = base64_encode(hash_hmac('sha256', $body, $secret, true));

        $this->assertTrue($service->verifySignature($body, $validSignature, $secret));
        $this->assertFalse($service->verifySignature($body, 'invalid-signature', $secret));
    }

    public function test_formats_thai_owner_daily_summary_message(): void
    {
        $service = new LineMessagingService;
        $message = $service->formatOwnerDailySummary(
            branchName: 'สาขาสยาม',
            date: '2026-11-22',
            totalSales: '18450.50',
            orderCount: 64,
            cashVariance: '-20.00'
        );

        $this->assertStringContainsString('สาขาสยาม', $message);
        $this->assertStringContainsString('18,450.50', $message);
        $this->assertStringContainsString('64', $message);
        $this->assertStringContainsString('-20.00', $message);
    }

    public function test_thai_sms_providers_are_registered_in_enum(): void
    {
        $this->assertContains('thaibulksms', SmsProvider::values());
        $this->assertContains('smsmkt', SmsProvider::values());
    }
}
