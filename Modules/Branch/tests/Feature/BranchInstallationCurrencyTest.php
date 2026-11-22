<?php

namespace Modules\Branch\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Database\Seeders\BranchDatabaseSeeder;
use Modules\Branch\Models\Branch;
use Modules\Installer\Services\Configuration\InstallerConfigurationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BranchInstallationCurrencyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('installationModes')]
    public function test_installed_branches_use_the_selected_default_currency(bool $demo): void
    {
        config(['app.seed_demo_data' => $demo]);
        app(InstallerConfigurationInterface::class)->apply([
            'app_name' => 'Test Restaurant',
            'supported_locales' => ['en'],
            'default_locale' => 'en',
            'supported_countries' => ['US'],
            'default_country' => 'US',
            'default_timezone' => 'America/New_York',
            'supported_currencies' => ['USD', 'JOD'],
            'default_currency' => 'USD',
        ]);

        $this->seed(BranchDatabaseSeeder::class);

        $this->assertSame('USD', Branch::query()->where('is_main', true)->sole()->currency);
        $this->assertSame($demo ? 3 : 1, Branch::query()->count());
        $this->assertSame(['USD'], Branch::query()->distinct()->pluck('currency')->all());
    }

    public static function installationModes(): array
    {
        return ['standard' => [false], 'with demo branches' => [true]];
    }
}
