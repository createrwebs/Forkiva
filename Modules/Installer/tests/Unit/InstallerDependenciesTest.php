<?php

namespace Modules\Installer\Tests\Unit;

use Illuminate\Support\Facades\File;
use Modules\Installer\Services\Dependencies\InstallerDependencies;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstallerDependenciesTest extends TestCase
{
    public function test_postgres_driver_can_replace_mysql_driver(): void
    {
        $packages = [['name' => 'app', 'require' => ['php' => '^8.2']]];
        $extensions = ['pdo' => '8.4.7', 'curl' => '8.4.7', 'pdo_pgsql' => '8.4.7'];
        $this->assertTrue((new InstallerDependencies)->platform($packages, '8.4.7', 8, $extensions)['extensions']['passed']);
        unset($extensions['pdo_pgsql']);
        $this->assertFalse((new InstallerDependencies)->platform($packages, '8.4.7', 8, $extensions)['extensions']['passed']);
    }

    /**
     * Keep effective PHP requirements accurate when the root constraint is broader than the lock.
     */
    #[DataProvider('runtimes')]
    public function test_php_versions_and_architecture_are_checked(string $version, int $size, bool $passed): void
    {
        $checks = (new InstallerDependencies)->platform([
            ['name' => 'app', 'require' => ['php' => '^8.2']],
            ['name' => 'runtime-library', 'require' => ['php' => '>=8.4.1']],
            ['name' => 'spreadsheet', 'require' => ['php' => '<8.5.0']],
            ['name' => 'zip-library', 'require' => ['php-64bit' => '^8.3']],
        ], $version, $size, ['pdo_mysql' => $version]);

        $this->assertSame($passed, $checks['php']['passed']);
        $this->assertStringContainsString('>= 8.4.1 < 8.5.0, 64-bit', $checks['php']['detail']);
    }

    /**
     * @return array<string, array{string, int, bool}>
     */
    public static function runtimes(): array
    {
        return [
            'PHP shown in phpMyAdmin' => ['8.2.4', 8, false],
            'below patch minimum' => ['8.4.0', 8, false],
            'minimum supported' => ['8.4.1', 8, true],
            'current runtime' => ['8.4.7', 8, true],
            'upper bound' => ['8.5.0', 8, false],
            '32-bit runtime' => ['8.4.7', 4, false],
        ];
    }

    public function test_extensions_are_checked_for_presence_and_version(): void
    {
        $checks = (new InstallerDependencies)->platform([
            ['name' => 'app', 'require' => ['php' => '^8.4', 'ext-intl' => '*', 'ext-zip' => '>=1.22']],
        ], '8.4.7', 8, ['zip' => '1.21.0', 'pdo_mysql' => '8.4.7']);
        $this->assertFalse($checks['extensions']['passed']);
        $this->assertStringContainsString('ext-intl', $checks['extensions']['remedy']);
        $this->assertStringContainsString('ext-zip >=1.22', $checks['extensions']['remedy']);
    }

    public function test_merged_module_requirements_and_missing_locked_packages_are_checked(): void
    {
        File::shouldReceive('get')->with(base_path('composer.json'))->once()->andReturn(json_encode([
            'name' => 'app', 'require' => ['php' => '^8.2'],
            'extra' => ['merge-plugin' => ['include' => ['Modules/*/composer.json']]],
        ]));
        File::shouldReceive('glob')->with(base_path('Modules/*/composer.json'))->once()->andReturn([base_path('Modules/Fixture/composer.json')]);
        File::shouldReceive('get')->with(base_path('Modules/Fixture/composer.json'))->once()->andReturn(json_encode([
            'name' => 'fixture/module', 'require' => ['ext-forkiva_missing' => '*', 'forkiva/unlocked-package' => '^2.0'],
        ]));
        File::shouldReceive('get')->with(base_path('composer.lock'))->once()->andReturn(json_encode([
            'packages' => [['name' => 'forkiva/missing-package', 'version' => '1.0.0']],
            'packages-dev' => [['name' => 'fixture/dev', 'require' => ['php' => '>=99.0']]],
        ]));

        $checks = (new InstallerDependencies)->run();
        $this->assertTrue($checks['php']['passed']);
        $this->assertFalse($checks['extensions']['passed']);
        $this->assertStringContainsString('fixture/module requires ext-forkiva_missing', $checks['extensions']['remedy']);
        $this->assertFalse($checks['dependencies']['passed']);
        $this->assertStringContainsString('forkiva/missing-package 1.0.0', $checks['dependencies']['remedy']);
        $this->assertStringContainsString('forkiva/unlocked-package ^2.0 required by fixture/module', $checks['dependencies']['remedy']);
    }
}
