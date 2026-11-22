<?php

namespace Modules\GiftCard\Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Core\Services\Accounting\NullFinancialAccountLookup;
use Modules\GiftCard\Enums\GiftCardBreakageBatchStatus;
use Modules\GiftCard\Enums\GiftCardFundingType;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardTransaction;
use Modules\GiftCard\Services\GiftCard\GiftCardServiceInterface;
use Modules\GiftCard\Services\GiftCardBreakage\GiftCardBreakageServiceInterface;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\User\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GiftCardProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_lookup_rejects_malformed_uuid_without_a_database_error(): void
    {
        $this->expectException(ModelNotFoundException::class);
        app(GiftCardTransactionServiceInterface::class)->findOrFail('not-a-uuid');
    }

    public function test_lookup_accepts_card_codes_uuids_and_ids(): void
    {
        $branch = Branch::factory()->create();
        $card = GiftCard::factory()->branchScoped($branch->id)->create();
        $service = app(GiftCardServiceInterface::class);
        foreach ([$card->id, (string) $card->id, $card->uuid, strtolower($card->code)] as $identifier) {
            $this->assertSame($card->id, $service->findOrFail($identifier)->id);
        }
    }

    public function test_card_currency_rate_never_falls_back_to_order_exchange_rate(): void
    {
        $branch = Branch::factory()->create(['currency' => 'USD']);
        $card = GiftCard::factory()->branchScoped($branch->id)->withBalance(100, 100)->create(['currency' => 'USD']);

        $transaction = app(GiftCardTransactionServiceInterface::class)->record($card, [
            'type' => GiftCardTransactionType::Redeem,
            'amount' => '10.0000',
            'exchange_rate' => '9.00000000',
            'currency_rate' => '0.70000000',
            'branch_id' => $branch->id,
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'card-rate-is-authoritative',
        ]);

        $this->assertSame('0.70000000', $transaction->getRawOriginal('currency_rate'));
        $this->assertSame('9.00000000', $transaction->getRawOriginal('exchange_rate'));
        $this->assertSame($branch->id, $card->fresh()->liability_branch_id);
    }

    public function test_empty_financial_amount_or_frozen_rate_returns_field_validation_errors(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $card = GiftCard::factory()->branchScoped($branch->id)->withBalance(20, 20)->create();
        $service = app(GiftCardTransactionServiceInterface::class);

        try {
            $service->record($card, [
                'type' => GiftCardTransactionType::Adjustment,
                'amount' => '',
                'currency_rate' => '1.00000000',
                'economic_date' => '2026-07-22',
                'idempotency_key' => 'empty-gift-card-amount',
                'notes' => 'Validation test.',
            ]);
            $this->fail('An empty amount should not reach BigDecimal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        try {
            $service->record($card, [
                'type' => GiftCardTransactionType::Adjustment,
                'amount' => '1.0000',
                'currency_rate' => '',
                'economic_date' => '2026-07-22',
                'idempotency_key' => 'empty-gift-card-rate',
                'notes' => 'Validation test.',
            ]);
            $this->fail('An empty frozen rate should not reach BigDecimal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('currency_rate', $exception->errors());
        }
    }

    public function test_financial_identity_and_deletion_are_blocked_after_issue(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $other = Branch::factory()->create(['currency' => 'USD']);
        $card = app(GiftCardServiceInterface::class)->store([
            'branch_id' => $branch->id,
            'initial_balance' => '25.0000',
            'status' => GiftCardStatus::Active->value,
            'funding_type' => GiftCardFundingType::Promotional->value,
            'idempotency_key' => 'immutable-card-issue',
        ]);

        try {
            app(GiftCardServiceInterface::class)->update($card->id, [
                'branch_id' => $other->id,
                'status' => GiftCardStatus::Active->value,
            ]);
            $this->fail('Changing the branch should have failed.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->expectException(HttpException::class);
        app(GiftCardServiceInterface::class)->destroy($card->id);
    }

    public function test_idempotency_fingerprint_rejects_changed_economic_date(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $card = GiftCard::factory()->branchScoped($branch->id)->withBalance(20, 20)->create();
        $service = app(GiftCardTransactionServiceInterface::class);
        $payload = [
            'type' => GiftCardTransactionType::Adjustment,
            'amount' => '5.0000',
            'currency_rate' => '1.00000000',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'fingerprint-conflict-test',
            'notes' => 'Approved correction.',
        ];
        $service->record($card, $payload);

        $this->expectException(HttpException::class);
        $service->record($card, [...$payload, 'economic_date' => '2026-07-23']);
    }

    public function test_breakage_requires_review_and_reverses_through_batch_owner(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $card = GiftCard::factory()->branchScoped($branch->id)->expired()->withBalance(10, 10)->create();
        $service = app(GiftCardBreakageServiceInterface::class);
        $batch = $service->store([
            'branch_id' => $branch->id,
            'gift_card_ids' => [$card->id],
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'breakage-production-test',
            'review_note' => 'Reviewed expiry evidence.',
        ]);
        $this->assertSame(GiftCardBreakageBatchStatus::Draft, $batch->status);
        $batch = $service->review($batch->id, ['economic_date' => '2026-07-22', 'review_note' => 'Reviewed expiry evidence.']);
        $this->assertSame(GiftCardBreakageBatchStatus::Reviewed, $batch->status);
        $batch = $service->post($batch->id);
        $this->assertSame(GiftCardBreakageBatchStatus::Posted, $batch->status);
        $this->assertEquals(0, $card->fresh()->getRawOriginal('current_balance'));

        $transaction = GiftCardTransaction::query()->findOrFail($batch->lines->first()->gift_card_transaction_id);
        try {
            app(GiftCardTransactionServiceInterface::class)->reverse($transaction->id, [
                'economic_date' => '2026-07-22',
                'idempotency_key' => 'invalid-direct-breakage-reversal',
                'reason' => 'Not batch-owned.',
            ]);
            $this->fail('Direct breakage reversal should have failed.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $reversal = $service->reverse($batch->id, [
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'valid-breakage-reversal',
            'reason' => 'Approved correction.',
        ]);
        $this->assertSame(GiftCardBreakageBatchStatus::Posted, $reversal->status);
        $this->assertEquals(10, $card->fresh()->getRawOriginal('current_balance'));
    }

    public function test_breakage_form_meta_separates_branches_from_branch_dependent_cards(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $otherBranch = Branch::factory()->create(['currency' => 'JOD']);
        $eligibleCard = GiftCard::factory()->branchScoped($branch->id)->expired()->withBalance(10, 10)->create();
        GiftCard::factory()->branchScoped($otherBranch->id)->expired()->withBalance(20, 20)->create();
        $service = app(GiftCardBreakageServiceInterface::class);

        $staticMeta = $service->getFormMeta();
        $this->assertArrayHasKey('branches', $staticMeta);
        $this->assertArrayNotHasKey('eligible_cards', $staticMeta);

        $branchMeta = $service->getFormMeta($branch->id);
        $this->assertArrayHasKey('eligible_cards', $branchMeta);
        $this->assertArrayNotHasKey('branches', $branchMeta);
        $this->assertSame([$eligibleCard->id], $branchMeta['eligible_cards']->pluck('id')->all());
    }

    public function test_breakage_form_meta_resolves_and_enforces_the_assigned_branch(): void
    {
        $assignedBranch = Branch::factory()->create(['currency' => 'JOD']);
        $otherBranch = Branch::factory()->create(['currency' => 'JOD']);
        $assignedCard = GiftCard::factory()->branchScoped($assignedBranch->id)->expired()->withBalance(10, 10)->create();
        GiftCard::factory()->branchScoped($otherBranch->id)->expired()->withBalance(20, 20)->create();
        auth()->setUser(User::factory()->create(['branch_id' => $assignedBranch->id]));
        $service = app(GiftCardBreakageServiceInterface::class);

        $meta = $service->getFormMeta();
        $this->assertArrayNotHasKey('branches', $meta);
        $this->assertSame([$assignedCard->id], $meta['eligible_cards']->pluck('id')->all());

        try {
            $service->getFormMeta($otherBranch->id);
            $this->fail('An assigned user must not resolve another branch.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_numeric_transaction_reversal_never_matches_a_numeric_looking_uuid(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $purchaseCard = GiftCard::factory()->branchScoped($branch->id)->withBalance(10, 10)->create();
        $targetCard = GiftCard::factory()->branchScoped($branch->id)->withBalance(10, 10)->create();
        $service = app(GiftCardTransactionServiceInterface::class);

        $purchase = $service->record($purchaseCard, [
            'type' => GiftCardTransactionType::Purchase,
            'amount' => '10.0000',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'numeric-uuid-purchase',
        ]);
        $target = $service->record($targetCard, [
            'type' => GiftCardTransactionType::Adjustment,
            'amount' => '2.0000',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'numeric-uuid-adjustment',
            'notes' => 'Approved balance correction.',
        ]);

        DB::table('gift_card_transactions')
            ->where('id', $purchase->id)
            ->update(['uuid' => str_pad((string) $target->id, 8, 'a').'-0000-4000-8000-000000000001']);

        $reversal = $service->reverse($target->id, [
            'economic_date' => '2026-07-23',
            'idempotency_key' => 'numeric-uuid-adjustment-reversal',
            'reason' => 'Approved reversal.',
        ]);

        $this->assertSame($target->id, $reversal->original_transaction_id);
        $this->assertNotNull($target->fresh()->reversed_at);
        $this->assertNull($purchase->fresh()->reversed_at);
    }

    public function test_global_operational_card_remains_available_without_accounting(): void
    {
        $this->app->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
        $card = GiftCard::factory()->global()->withBalance(15, 15)->create(['liability_branch_id' => null]);

        $transaction = app(GiftCardTransactionServiceInterface::class)->record($card, [
            'type' => GiftCardTransactionType::Redeem,
            'amount' => '5.0000',
            'currency_rate' => '1.00000000',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'global-card-accounting-disabled',
        ]);

        $this->assertNull($card->fresh()->liability_branch_id);
        $this->assertEquals(10, $transaction->balance_after->amount());
    }

    public function test_legacy_liability_remediation_is_reviewed_and_idempotent(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD', 'is_active' => true]);
        $card = GiftCard::factory()->global()->withBalance(20, 20)->create(['liability_branch_id' => null]);
        $transaction = GiftCardTransaction::factory()->forGiftCard($card)->purchase()->create([
            'branch_id' => $branch->id,
            'currency' => 'JOD',
        ]);
        DB::table('gift_card_transactions')->where('id', $transaction->id)->update([
            'currency_rate' => null,
            'currency_rate_source' => null,
            'economic_date' => null,
            'idempotency_key' => null,
            'request_fingerprint' => null,
        ]);

        $this->artisan('accounting:remediate-gift-card-liability')->assertExitCode(1);

        $path = tempnam(sys_get_temp_dir(), 'gift-card-branches-');
        $ratePath = tempnam(sys_get_temp_dir(), 'gift-card-rates-');
        file_put_contents($path, implode("\n", [
            'gift_card_id,liability_branch_id,reviewed_by,reviewed_at,note',
            "$card->id,$branch->id,Production Reviewer,2026-07-22T10:00:00+03:00,Approved liability ownership",
        ]));
        file_put_contents($ratePath, implode("\n", [
            'transaction_id,currency_rate,reviewed_by,reviewed_at,note',
            "$transaction->id,1.00000000,Production Reviewer,2026-07-22T10:00:00+03:00,Approved immutable rate",
        ]));
        try {
            $exitCode = Artisan::call('accounting:remediate-gift-card-liability', [
                '--apply' => true,
                '--branches' => $path,
                '--rates' => $ratePath,
            ]);
            $this->assertSame(0, $exitCode, Artisan::output());
            $this->artisan('accounting:remediate-gift-card-liability')->assertSuccessful();
        } finally {
            @unlink($path);
            @unlink($ratePath);
        }

        $this->assertSame($branch->id, $card->fresh()->liability_branch_id);
        $transaction->refresh();
        $this->assertSame('1.00000000', $transaction->currency_rate);
        $this->assertSame('reviewed_csv', $transaction->currency_rate_source);
        $this->assertNotNull($transaction->economic_date);
        $this->assertNotNull($transaction->request_fingerprint);
    }
}
