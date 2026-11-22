<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Enums\AccountingFinancialAccountType;
use Modules\Accounting\Enums\AccountingJournalEntryType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountingDimension;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Accounting\Models\FinancialAccount;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountingSetup\AccountingSetupServiceInterface;
use Modules\Accounting\Services\FiscalYear\FiscalYearServiceInterface;
use Modules\Accounting\Services\JournalEntry\JournalEntryServiceInterface;
use Modules\Branch\Models\Branch;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Throwable;

trait InteractsWithAccounting
{
    protected Branch $accountingBranch;

    protected User $accountingUser;

    protected function bootAccountingTest(?Branch $branch = null, ?User $user = null): void
    {
        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'default_currency' => 'JOD',
                    'default_locale' => 'en',
                    'supported_locales' => ['en', 'ar'],
                    'supported_currencies' => ['JOD', 'USD'],
                    default => $default,
                };
            }

            public function set(array $data): array
            {
                return $data;
            }
        });

        config()->set('filesystems.default_private', 'local');
        Storage::fake('local');

        $this->accountingBranch = $branch ?: Branch::factory()->create([
            'currency' => 'JOD',
            'timezone' => 'Asia/Amman',
            'is_active' => true,
        ]);

        $this->accountingUser = $user ?: User::factory()->create(['is_active' => true]);
        $this->actingAs($this->accountingUser);
    }

    /**
     * Grant API permissions to the current accounting user.
     *
     * @param  string|array<int, string>  $permissions
     */
    protected function grantAccountingPermissions(string|array $permissions): void
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        $this->accountingUser->givePermissionTo($permissions);
    }

    protected function accountingDimension(array $attributes = [], ?Branch $branch = null): AccountingDimension
    {
        $branch = $branch ?: $this->accountingBranch;

        return AccountingDimension::query()
            ->create([
                'branch_id' => $attributes['branch_id'] ?? $branch->id,
                'scope' => $attributes['scope'] ?? 'branch',
                'type' => $attributes['type'] ?? 'cost_center',
                'code' => $attributes['code'] ?? 'FOH',
                'name' => $attributes['name'] ?? [
                    'en' => 'Front of House',
                    'ar' => 'Front of House',
                ],
                'is_system' => $attributes['is_system'] ?? false,
                'is_active' => $attributes['is_active'] ?? true,
                'effective_from' => $attributes['effective_from'] ?? null,
                'effective_to' => $attributes['effective_to'] ?? null,
            ]);
    }

    protected function accountingPeriodFor(string $date, ?Branch $branch = null): AccountingPeriod
    {
        return AccountingPeriod::query()
            ->withOutGlobalBranchPermission()
            ->where('branch_id', ($branch ?: $this->accountingBranch)->id)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->firstOrFail();
    }

    protected function createDraftJournal(array $overrides = []): JournalEntry
    {
        $this->seedAccountingChart();
        $this->createAccountingFiscalYear();

        $cash = $this->accountingAccount('1000');
        $equity = $this->accountingAccount('3000');

        return app(JournalEntryServiceInterface::class)->store([
            'branch_id' => $this->accountingBranch->id,
            'entry_date' => $overrides['entry_date'] ?? '2026-01-15',
            'entry_type' => $overrides['entry_type'] ?? AccountingJournalEntryType::Opening->value,
            'description' => $overrides['description'] ?? 'Opening balance',
            'currency' => $overrides['currency'] ?? $this->accountingBranch->currency,
            'currency_rate' => $overrides['currency_rate'] ?? 1,
            'lines' => $overrides['lines'] ?? [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 100],
            ],
        ]);
    }

    /**
     * Seed the branch chart and return the result summary.
     *
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    protected function seedAccountingChart(?Branch $branch = null): array
    {
        return app(AccountingSetupServiceInterface::class)->seedDefaultChart(($branch ?: $this->accountingBranch)->id);
    }

    protected function createAccountingFiscalYear(?Branch $branch = null, array $attributes = []): FiscalYear
    {
        $branch = $branch ?: $this->accountingBranch;
        $startsOn = $attributes['starts_on'] ?? '2026-01-01';
        $endsOn = $attributes['ends_on'] ?? '2026-12-31';

        $existing = FiscalYear::query()
            ->withOutGlobalBranchPermission()
            ->where('branch_id', $branch->id)
            ->whereDate('starts_on', $startsOn)
            ->whereDate('ends_on', $endsOn)
            ->first();

        if ($existing) {
            return $existing;
        }

        return app(FiscalYearServiceInterface::class)->store([
            'branch_id' => $branch->id,
            'name' => $attributes['name'] ?? 'FY 2026',
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $attributes['status'] ?? null,
        ]);
    }

    protected function accountingAccount(string $code, ?Branch $branch = null): Account
    {
        return Account::query()
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->where('branch_id', ($branch ?: $this->accountingBranch)->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    protected function financialAccount(Account $account, AccountingFinancialAccountType $type, string $name = 'Financial Account'): FinancialAccount
    {
        return FinancialAccount::query()->create([
            'branch_id' => $this->accountingBranch->id,
            'account_id' => $account->id,
            'type' => $type,
            'name' => ['en' => $name, 'ar' => $name],
            'currency' => $this->accountingBranch->currency,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->accountingUser->id,
        ]);
    }

    protected function assertJournalLine(JournalEntry $entry, int $accountId, float $debit, float $credit): void
    {
        $line = $entry->fresh('lines')->lines->firstWhere('account_id', $accountId);

        $this->assertNotNull($line, "Expected journal line for account {$accountId}.");
        $this->assertEqualsWithDelta($debit, (float) $line->getRawOriginal('debit'), 0.000001);
        $this->assertEqualsWithDelta($credit, (float) $line->getRawOriginal('credit'), 0.000001);
    }
}
