<?php

namespace Modules\GiftCard\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Models\Branch;
use Modules\Currency\Models\CurrencyRate;
use Modules\GiftCard\Enums\GiftCardAnalyticsWidget;
use Modules\GiftCard\Enums\GiftCardFundingType;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardBatch;
use Modules\GiftCard\Models\GiftCardTransaction;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GiftCardAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'default_currency' => 'JOD',
                    'default_locale' => 'en',
                    default => $default,
                };
            }
        });

        Cache::flush();
        CarbonImmutable::setTestNow('2026-09-08 10:00:00');
        $this->user = User::factory()->create(['is_active' => true]);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_analytics_routes_require_permission(): void
    {
        $this->getJson('/api/v1/gift-cards-analytics/filters')->assertForbidden();
        $this->getJson('/api/v1/gift-cards-analytics?widgets[]=total_cards')->assertForbidden();
    }

    public function test_filters_match_the_dashboard_contract(): void
    {
        $this->grantAnalyticsPermission();

        $this->getJson('/api/v1/gift-cards-analytics/filters')
            ->assertOk()
            ->assertJsonPath('body.defaults.from', '2026-01-01')
            ->assertJsonPath('body.defaults.to', '2026-09-08')
            ->assertJsonPath('body.meta.branch_locked', false)
            ->assertJsonPath('body.meta.max_range_days', 367)
            ->assertJsonCount(4, 'body.presets');
    }

    public function test_api_calculates_only_requested_widgets(): void
    {
        $this->grantAnalyticsPermission();
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        GiftCard::factory()->count(2)->branchScoped($branch->id)->withBalance(25, 25)->create([
            'currency' => 'JOD',
            'created_at' => CarbonImmutable::now(),
        ]);

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'branch_id' => $branch->id,
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => ['total_cards'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.meta.branch_id', $branch->id)
            ->assertJsonPath('body.meta.display_currency', 'JOD')
            ->assertJsonPath('body.widgets.total_cards.authorized', true)
            ->assertJsonPath('body.widgets.total_cards.data.value', 2)
            ->assertJsonMissingPath('body.widgets.sold_value');
    }

    public function test_assigned_user_cannot_read_another_branch(): void
    {
        $this->grantAnalyticsPermission();
        $assignedBranch = Branch::factory()->create(['currency' => 'JOD']);
        $otherBranch = Branch::factory()->create(['currency' => 'JOD']);
        GiftCard::factory()->branchScoped($assignedBranch->id)->withBalance(10, 10)->create();
        GiftCard::factory()->branchScoped($otherBranch->id)->withBalance(10, 10)->create();
        $this->user->forceFill(['branch_id' => $assignedBranch->id])->save();
        $this->actingAs($this->user->fresh());

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'branch_id' => $otherBranch->id,
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => ['total_cards'],
        ]))->assertNotFound();

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => ['total_cards'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.meta.branch_id', $assignedBranch->id)
            ->assertJsonPath('body.meta.branch_locked', true)
            ->assertJsonPath('body.widgets.total_cards.data.value', 1);
    }

    public function test_branch_widgets_return_localized_plain_text_labels(): void
    {
        $this->grantAnalyticsPermission();
        $branch = Branch::factory()->create([
            'name' => ['en' => 'City Center', 'ar' => 'وسط المدينة'],
            'currency' => 'JOD',
        ]);
        $giftCard = GiftCard::factory()
            ->branchScoped($branch->id)
            ->withBalance(25, 25)
            ->create([
                'currency' => 'JOD',
                'created_at' => CarbonImmutable::now(),
            ]);
        GiftCardTransaction::factory()
            ->forGiftCard($giftCard)
            ->purchase()
            ->create([
                'funding_type' => GiftCardFundingType::Paid->value,
                'currency_rate' => 1,
                'transaction_at' => CarbonImmutable::now(),
            ]);

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => ['sales_by_branch', 'branch_highlights'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.widgets.sales_by_branch.data.labels.0', 'City Center')
            ->assertJsonPath('body.widgets.branch_highlights.data.highlights.0.branch_name', 'City Center');
    }

    public function test_analytics_separates_period_activity_from_current_snapshots_and_converts_currencies(): void
    {
        $this->grantAnalyticsPermission();
        CurrencyRate::query()->updateOrCreate(['currency' => 'JOD'], ['rate' => 1]);
        CurrencyRate::query()->updateOrCreate(['currency' => 'USD'], ['rate' => 2]);
        $jodBranch = Branch::factory()->create(['name' => ['en' => 'JOD Branch'], 'currency' => 'JOD']);
        $usdBranch = Branch::factory()->create(['name' => ['en' => 'USD Branch'], 'currency' => 'USD']);
        $jodCard = GiftCard::factory()->branchScoped($jodBranch->id)->noExpiry()->withBalance(10, 10)->create([
            'currency' => 'JOD',
            'created_at' => CarbonImmutable::now(),
        ]);
        $usdCard = GiftCard::factory()->branchScoped($usdBranch->id)->noExpiry()->withBalance(20, 20)->create([
            'currency' => 'USD',
            'created_at' => CarbonImmutable::now(),
        ]);
        GiftCard::factory()->branchScoped($usdBranch->id)->withBalance(40, 40)->create([
            'currency' => 'USD',
            'status' => GiftCardStatus::Active->value,
            'expiry_date' => CarbonImmutable::now()->addDays(5),
            'created_at' => CarbonImmutable::now()->subMonth(),
        ]);
        GiftCard::factory()->branchScoped($usdBranch->id)->noExpiry()->withBalance(10, 10)->create([
            'currency' => 'JOD',
            'created_at' => CarbonImmutable::now()->subMonth(),
        ]);
        GiftCardBatch::factory()->withBranch($jodBranch->id)->create([
            'name' => ['en' => 'JOD Batch'],
            'quantity' => 1,
            'value' => 100,
            'currency' => 'JOD',
            'created_at' => CarbonImmutable::now(),
        ]);
        GiftCardBatch::factory()->withBranch($usdBranch->id)->create([
            'name' => ['en' => 'USD Batch'],
            'quantity' => 1,
            'value' => 150,
            'currency' => 'USD',
            'created_at' => CarbonImmutable::now(),
        ]);

        $this->createTransaction($jodCard, GiftCardTransactionType::Purchase, 10, GiftCardFundingType::Paid, 1);
        $this->createTransaction($jodCard, GiftCardTransactionType::Purchase, 4, GiftCardFundingType::Promotional, 1);
        $this->createTransaction($jodCard, GiftCardTransactionType::Purchase, 5, GiftCardFundingType::Paid, 1);
        $this->createTransaction($jodCard, GiftCardTransactionType::IssueVoid, 5, GiftCardFundingType::Paid, 1);
        $this->createTransaction($usdCard, GiftCardTransactionType::Purchase, 20, GiftCardFundingType::Paid, 0.5);
        $this->createTransaction($usdCard, GiftCardTransactionType::Redeem, 8, null, 0.5, CarbonImmutable::now());
        $this->createTransaction($usdCard, GiftCardTransactionType::Redeem, 6, null, 0.5);

        $response = $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => [
                'total_cards',
                'active_cards',
                'total_transactions',
                'total_batches',
                'expiring_soon_cards',
                'outstanding_balance',
                'sold_value',
                'redeemed_value',
                'batch_face_value',
                'cards_health',
                'batch_activity',
                'expiry_watch',
                'sales_over_time',
                'redemption_over_time',
                'sales_by_branch',
                'liability_by_currency',
                'usage_by_day_of_week',
            ],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('body.meta.display_currency', 'JOD')
            ->assertJsonPath('body.widgets.total_cards.data.value', 2)
            ->assertJsonPath('body.widgets.active_cards.data.value', 4)
            ->assertJsonPath('body.widgets.total_transactions.data.value', 7)
            ->assertJsonPath('body.widgets.total_batches.data.value', 2)
            ->assertJsonPath('body.widgets.expiring_soon_cards.data.value', 1)
            ->assertJsonPath('body.widgets.outstanding_balance.data.value.amount', 50)
            ->assertJsonPath('body.widgets.sold_value.data.value.amount', 20)
            ->assertJsonPath('body.widgets.redeemed_value.data.value.amount', 3)
            ->assertJsonPath('body.widgets.batch_face_value.data.value.amount', 175)
            ->assertJsonPath('body.widgets.batch_activity.data.summary.total_value.amount', 175)
            ->assertJsonPath('body.widgets.batch_activity.data.top_batches.0.name', 'JOD Batch')
            ->assertJsonPath('body.widgets.batch_activity.data.top_batches.1.name', 'USD Batch')
            ->assertJsonCount(1, 'body.widgets.cards_health.data.status_mix')
            ->assertJsonPath('body.widgets.expiry_watch.data.summary.expiring_in_7_days', 1);

        $this->assertEquals(
            20.0,
            array_sum($response->json('body.widgets.sales_by_branch.data.series')),
        );
        $this->assertEquals(
            20.0,
            array_sum($response->json('body.widgets.sales_over_time.data.series')),
        );
        $this->assertEquals(
            3.0,
            array_sum($response->json('body.widgets.redemption_over_time.data.series')),
        );
        $this->assertEquals(
            1,
            array_sum($response->json('body.widgets.usage_by_day_of_week.data.series')),
        );
        $this->assertEquals(
            ['USD' => 30.0, 'JOD' => 20.0],
            array_combine(
                $response->json('body.widgets.liability_by_currency.data.labels'),
                $response->json('body.widgets.liability_by_currency.data.series'),
            ),
        );
        $response
            ->assertJsonPath('body.widgets.liability_by_currency.data.currency', 'JOD')
            ->assertJsonPath('body.widgets.liability_by_currency.data.precision', 3);

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'branch_id' => $usdBranch->id,
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => ['total_cards', 'outstanding_balance', 'sold_value', 'redeemed_value'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.meta.display_currency', 'USD')
            ->assertJsonPath('body.widgets.total_cards.data.value', 1)
            ->assertJsonPath('body.widgets.outstanding_balance.data.value.amount', 80)
            ->assertJsonPath('body.widgets.sold_value.data.value.amount', 20)
            ->assertJsonPath('body.widgets.redeemed_value.data.value.amount', 6);
    }

    public function test_unknown_widgets_and_oversized_ranges_are_rejected(): void
    {
        $this->grantAnalyticsPermission();

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => ['unknown'],
        ]))->assertUnprocessable();

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'from' => '2025-01-01',
            'to' => '2026-09-08',
            'widgets' => ['total_cards'],
        ]))->assertUnprocessable();
    }

    public function test_every_widget_executes_on_the_supported_test_database(): void
    {
        $this->grantAnalyticsPermission();
        $widgets = collect(GiftCardAnalyticsWidget::cases());

        $response = $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => $widgets->pluck('value')->all(),
        ]))->assertOk();

        $this->assertCount($widgets->count(), $response->json('body.widgets'));
        foreach ($widgets as $widget) {
            $response->assertJsonPath("body.widgets.{$widget->value}.authorized", true);
        }
    }

    public function test_default_dashboard_uses_a_bounded_number_of_gift_card_queries(): void
    {
        $this->grantAnalyticsPermission();
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $giftCard = GiftCard::factory()->branchScoped($branch->id)->withBalance(25, 25)->create([
            'currency' => 'JOD',
            'created_at' => CarbonImmutable::now(),
        ]);
        $this->createTransaction($giftCard, GiftCardTransactionType::Purchase, 25, GiftCardFundingType::Paid, 1);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            'branch_id' => $branch->id,
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => GiftCardAnalyticsWidget::defaultValues(),
        ]))->assertOk();

        $giftCardQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'gift_card'))
            ->count();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $giftCardQueries);
    }

    public function test_cached_widgets_are_reused_until_an_explicit_refresh(): void
    {
        $this->grantAnalyticsPermission();
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        GiftCard::factory()->branchScoped($branch->id)->withBalance(10, 10)->create([
            'currency' => 'JOD',
            'created_at' => CarbonImmutable::now(),
        ]);
        $query = [
            'branch_id' => $branch->id,
            'from' => '2026-09-08',
            'to' => '2026-09-08',
            'widgets' => ['total_cards'],
        ];

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('body.widgets.total_cards.data.value', 1);

        GiftCard::factory()->branchScoped($branch->id)->withBalance(10, 10)->create([
            'currency' => 'JOD',
            'created_at' => CarbonImmutable::now(),
        ]);

        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('body.widgets.total_cards.data.value', 1);
        $this->getJson('/api/v1/gift-cards-analytics?'.http_build_query([
            ...$query,
            'refresh' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('body.widgets.total_cards.data.value', 2);
    }

    public function test_gift_card_analytics_reporting_indexes_are_present(): void
    {
        $this->assertContains(
            'gift_cards_analytics_created_branch_idx',
            collect(Schema::getIndexes('gift_cards'))->pluck('name')->all(),
        );
        $this->assertContains(
            'gift_cards_analytics_branch_status_expiry_idx',
            collect(Schema::getIndexes('gift_cards'))->pluck('name')->all(),
        );
        $this->assertContains(
            'gift_cards_analytics_branch_created_idx',
            collect(Schema::getIndexes('gift_cards'))->pluck('name')->all(),
        );
        $this->assertContains(
            'gift_cards_analytics_branch_currency_idx',
            collect(Schema::getIndexes('gift_cards'))->pluck('name')->all(),
        );
        $this->assertContains(
            'gift_card_tx_analytics_at_branch_type_idx',
            collect(Schema::getIndexes('gift_card_transactions'))->pluck('name')->all(),
        );
        $this->assertContains(
            'gift_card_tx_analytics_branch_at_type_idx',
            collect(Schema::getIndexes('gift_card_transactions'))->pluck('name')->all(),
        );
        $this->assertContains(
            'gift_card_batches_analytics_created_branch_idx',
            collect(Schema::getIndexes('gift_card_batches'))->pluck('name')->all(),
        );
        $this->assertContains(
            'gift_card_batches_analytics_branch_created_idx',
            collect(Schema::getIndexes('gift_card_batches'))->pluck('name')->all(),
        );
    }

    protected function createTransaction(
        GiftCard $giftCard,
        GiftCardTransactionType $type,
        float $amount,
        ?GiftCardFundingType $fundingType,
        float $currencyRate,
        ?CarbonImmutable $reversedAt = null,
    ): GiftCardTransaction {
        return GiftCardTransaction::factory()
            ->forGiftCard($giftCard)
            ->create([
                'type' => $type->value,
                'amount' => $amount,
                'balance_before' => $giftCard->getRawOriginal('current_balance'),
                'balance_after' => $giftCard->getRawOriginal('current_balance'),
                'funding_type' => $fundingType?->value,
                'currency_rate' => $currencyRate,
                'reversed_at' => $reversedAt,
                'transaction_at' => CarbonImmutable::now(),
            ]);
    }

    protected function grantAnalyticsPermission(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::query()->firstOrCreate([
            'name' => 'admin.gift_cards.analytics',
            'guard_name' => 'api',
        ]);
        $this->user->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
