<?php

namespace Modules\GiftCard\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\GiftCard\Enums\GiftCardFundingType;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardTransaction;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GiftCardFinancialLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_insufficient_card_balance_returns_validation_error_without_changing_balance(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['admin.gift_card_transactions.create', 'admin.gift_card_transactions.manual_adjust'] as $name) {
            $user->givePermissionTo(Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'api']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $card = GiftCard::factory()->branchScoped($branch->id)->withBalance(10, 10)->create();

        $this->postJson("/api/v1/gift-card-transactions/gift-card/{$card->id}/financial-action", [
            'type' => GiftCardTransactionType::AdjustmentDecrease->value,
            'amount' => '11.0000',
            'idempotency_key' => 'insufficient-balance-test',
            'economic_date' => '2026-09-25',
            'notes' => 'Balance validation test.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertEquals(10, $card->fresh()->getRawOriginal('current_balance'));
        $this->assertSame(0, $card->transactions()->count());
    }

    public function test_promotional_issue_void_preserves_the_original_snapshot_and_is_idempotent(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $card = GiftCard::factory()->branchScoped($branch->id)->withBalance(0, 0)->create();
        $service = app(GiftCardTransactionServiceInterface::class);

        $issue = $service->record($card, [
            'type' => GiftCardTransactionType::Purchase,
            'amount' => '50.0000',
            'funding_type' => GiftCardFundingType::Promotional->value,
            'currency_rate' => '0.70921986',
            'idempotency_key' => 'gift-issue-promotional-test',
            'economic_date' => '2026-07-22',
            'notes' => 'Promotional issue test.',
        ]);

        $payload = [
            'type' => GiftCardTransactionType::IssueVoid,
            'original_transaction_id' => $issue->id,
            'amount' => '50.0000',
            'funding_type' => GiftCardFundingType::Paid->value,
            'currency_rate' => '9.00000000',
            'idempotency_key' => 'gift-issue-void-test',
            'economic_date' => '2026-07-22',
            'notes' => 'Void issue test.',
        ];
        $void = $service->recordFinancialAction($card->id, $payload);
        $retry = $service->recordFinancialAction($card->id, $payload);

        $this->assertSame($void->id, $retry->id);
        $this->assertSame($issue->id, $void->original_transaction_id);
        $this->assertSame(GiftCardFundingType::Promotional->value, $void->funding_type);
        $this->assertSame('0.70921986', $void->getRawOriginal('currency_rate'));
        $this->assertNull($void->financial_account_key);
        $this->assertEquals(0, $card->fresh()->getRawOriginal('current_balance'));
        $this->assertSame(1, GiftCardTransaction::query()->where('type', GiftCardTransactionType::IssueVoid)->count());

        $this->expectException(HttpException::class);
        $service->recordFinancialAction($card->id, [
            ...$payload,
            'idempotency_key' => 'gift-issue-void-second-test',
        ]);
    }
}
