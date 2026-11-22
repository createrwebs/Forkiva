<?php

namespace Modules\Tool\Tests\Unit;

use Modules\Tool\Providers\DeferredToolServiceProvider;
use Modules\Tool\Services\Database\DatabaseBinaryLocator;
use Modules\Tool\Services\Database\DatabaseBinaryLocatorInterface;
use Modules\Tool\Services\Database\DatabaseRestoreGuard;
use Modules\Tool\Services\Database\DatabaseRestoreGuardInterface;
use Tests\TestCase;

class DatabaseToolProviderTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/forkiva_tool_provider_'.uniqid();
        mkdir($this->temporaryDirectory, 0700, true);

        foreach (['mysqldump', 'mysql'] as $name) {
            $path = $this->temporaryDirectory.'/'.$name;
            file_put_contents($path, "#!/bin/sh\nexit 0\n");
            chmod($path, 0700);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->temporaryDirectory.'/mysqldump');
        @unlink($this->temporaryDirectory.'/mysql');
        @rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_provider_resolves_null_binary_settings_after_boot(): void
    {
        config([
            'database.default' => 'tool_provider_test',
            'database.connections.tool_provider_test.driver' => 'mysql',
            'tool.database.dump_binary' => null,
            'tool.database.restore_binary' => null,
            'tool.database.binary_directories' => [$this->temporaryDirectory],
        ]);

        $provider = new DeferredToolServiceProvider($this->app);
        $provider->boot(app(DatabaseBinaryLocatorInterface::class));

        $this->assertSame(realpath($this->temporaryDirectory.'/mysqldump'), config('tool.database.dump_binary'));
        $this->assertSame(realpath($this->temporaryDirectory.'/mysql'), config('tool.database.restore_binary'));
    }

    public function test_provider_binds_database_service_contracts(): void
    {
        $this->assertInstanceOf(DatabaseBinaryLocator::class, app(DatabaseBinaryLocatorInterface::class));
        $this->assertInstanceOf(DatabaseRestoreGuard::class, app(DatabaseRestoreGuardInterface::class));
    }
}
