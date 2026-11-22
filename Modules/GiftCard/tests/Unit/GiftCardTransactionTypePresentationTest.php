<?php

namespace Modules\GiftCard\Tests\Unit;

use Modules\GiftCard\Enums\GiftCardTransactionType;
use Tests\TestCase;

class GiftCardTransactionTypePresentationTest extends TestCase
{
    public function test_every_transaction_type_exposes_a_table_enum_color(): void
    {
        $expectedColors = [
            GiftCardTransactionType::Purchase->value => 'success',
            GiftCardTransactionType::Redeem->value => 'primary',
            GiftCardTransactionType::Refund->value => 'info',
            GiftCardTransactionType::Recharge->value => 'success',
            GiftCardTransactionType::Adjustment->value => 'info',
            GiftCardTransactionType::Expire->value => 'warning',
            GiftCardTransactionType::CashOut->value => 'error',
            GiftCardTransactionType::IssueVoid->value => 'error',
            GiftCardTransactionType::AdjustmentDecrease->value => 'warning',
            GiftCardTransactionType::Breakage->value => 'warning',
            GiftCardTransactionType::Reversal->value => 'info',
        ];

        foreach (GiftCardTransactionType::cases() as $type) {
            $this->assertSame($expectedColors[$type->value], $type->color());
            $this->assertSame($expectedColors[$type->value], $type->toTrans()['color']);
        }
    }
}
