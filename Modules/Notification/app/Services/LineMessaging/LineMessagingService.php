<?php

namespace Modules\Notification\Services\LineMessaging;

use Illuminate\Support\Facades\Http;

class LineMessagingService
{
    private const PUSH_ENDPOINT = 'https://api.line.me/v2/bot/message/push';

    /**
     * Verify the X-Line-Signature header using constant-time HMAC-SHA256 comparison.
     */
    public function verifySignature(string $rawBody, string $signature, string $channelSecret): bool
    {
        if ($signature === '' || $channelSecret === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $channelSecret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Send a plain text push message via LINE Messaging API.
     */
    public function pushText(string $channelAccessToken, string $toUserId, string $message): bool
    {
        $response = Http::withToken($channelAccessToken)
            ->post(self::PUSH_ENDPOINT, [
                'to' => $toUserId,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => $message,
                    ],
                ],
            ]);

        return $response->successful();
    }

    /**
     * Format a concise Thai daily closing summary for store owners.
     */
    public function formatOwnerDailySummary(
        string $branchName,
        string $date,
        string $totalSales,
        int $orderCount,
        string $cashVariance = '0.00'
    ): string {
        $formattedSales = number_format((float) $totalSales, 2, '.', ',');

        return implode("\n", [
            "สรุปยอดปิดวัน — {$branchName}",
            "วันที่: {$date}",
            "ยอดขายรวม: ฿{$formattedSales}",
            "จำนวนบิล: {$orderCount} บิล",
            "ผลต่างเงินสด: ฿{$cashVariance}",
        ]);
    }
}
