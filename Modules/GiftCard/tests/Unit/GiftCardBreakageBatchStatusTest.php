<?php

namespace Modules\GiftCard\Tests\Unit;

use Modules\GiftCard\Enums\GiftCardBreakageBatchStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GiftCardBreakageBatchStatusTest extends TestCase
{
    /**
     * @return array<string, array{GiftCardBreakageBatchStatus, string}>
     */
    public static function statuses(): array
    {
        return [
            'draft' => [GiftCardBreakageBatchStatus::Draft, 'info'],
            'reviewed' => [GiftCardBreakageBatchStatus::Reviewed, 'info'],
            'posted' => [GiftCardBreakageBatchStatus::Posted, 'success'],
            'reversed' => [GiftCardBreakageBatchStatus::Reversed, 'warning'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_status_exposes_its_table_color(
        GiftCardBreakageBatchStatus $status,
        string $color,
    ): void {
        $this->assertSame($color, $status->color());
        $this->assertSame($status->value, $status->toTrans()['id']);
    }
}
