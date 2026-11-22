<?php

namespace Modules\Installer\Tests\Feature;

use Dotenv\Dotenv;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Modules\Core\Providers\CoreServiceProvider;
use Modules\Installer\Http\Controllers\InstallerController;
use Modules\Installer\Services\Checks\InstallerChecksInterface as InstallerChecks;
use Modules\Installer\Services\Configuration\InstallerConfigurationInterface;
use Modules\Installer\Services\Environment\InstallerEnvironmentInterface as InstallerEnvironment;
use Modules\Installer\Services\Installation\InstallationServiceInterface;
use Modules\Installer\Services\State\InstallerStateInterface as InstallerState;
use Modules\Setting\Database\Seeders\SettingDatabaseSeeder;
use Modules\Setting\Models\Setting;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    private string $sandbox;

    private string $originalStorage;

    private string $originalEnvironment;

    private string $originalPublic;

    private string $originalBootstrap;

    /**
     * Cover minimum versions, MariaDB compatibility prefixes and vendor suffixes.
     *
     * @return array<string, array{string, bool, string}>
     */
    public static function databaseVersions(): array
    {
        return [
            'local MariaDB' => ['10.4.28-MariaDB', true, 'Choose an empty database.'],
            'old MariaDB' => ['10.2.44-MariaDB', false, 'Detected MariaDB 10.2.44. Forkiva requires MariaDB 10.3.0 or newer.'],
            'old MySQL' => ['5.7.44', false, 'Detected MySQL 5.7.44. Forkiva requires MySQL 8.0.16 or newer.'],
            'unenforced checks' => ['8.0.15', false, 'Forkiva requires MySQL 8.0.16 or newer.'],
            'unrecognized' => ['unknown', false, 'server version could not be recognized'],
            'minimum MySQL' => ['8.0.16', true, 'Choose an empty database.'],
            'MySQL vendor' => ['8.4.6-commercial', true, 'Choose an empty database.'],
            'minimum MariaDB' => ['10.3.0-MariaDB', true, 'Choose an empty database.'],
            'MariaDB compatibility prefix' => ['5.5.5-10.11.6-MariaDB-0+deb12u1', true, 'Choose an empty database.'],
            'MariaDB vendor suffix' => ['10.11.6-0+deb12u1-MariaDB', true, 'Choose an empty database.'],
        ];
    }

    public static function invalidConfigurations(): array
    {
        return [
            [['supported_countries' => ['XX']], 'supported_countries.0'],
            [['default_country' => 'GB'], 'default_country'],
            [['default_locale' => 'fr'], 'default_locale'],
            [['supported_locales' => []], 'supported_locales'],
            [['supported_currencies' => ['FAKE']], 'supported_currencies.0'],
            [['default_currency' => 'EUR'], 'default_currency'],
            [['default_timezone' => 'not-a-timezone'], 'default_timezone'],
            [['app_name' => "Bad\nAPP_DEBUG=true"], 'app_name'],
            [['app_url' => 'https://pos.example.test/path'], 'app_url'],
            [['app_url' => 'https://user@pos.example.test'], 'app_url'],
            [['cache_store' => 'array'], 'cache_store'],
            [['passkeys_relying_party_id' => 'https://example.test'], 'passkeys_relying_party_id'],
            [['passkeys_allowed_origins' => ['https://unrelated.test']], 'passkeys_relying_party_id'],
            [['passkeys_allowed_origins' => ['http://pos.example.test']], 'passkeys_relying_party_id'],
            [['passkeys_allowed_origins' => ['https://pos.example.test/path']], 'passkeys_relying_party_id'],
            [['passkeys_allowed_origins' => ['https://order.example.test']], 'passkeys_allowed_origins'],
        ];
    }

    /**
     * Allow the installer controller to consume a replacement implementation of the contract.
     */
    public function test_installer_controller_uses_the_installation_service_contract(): void
    {
        $state = app(InstallerState::class)->read();
        $this->mock(InstallationServiceInterface::class)
            ->shouldReceive('check')
            ->once()
            ->with($state)
            ->andReturn(array_replace($state, ['checks_passed' => true]));

        $this->postJson('/installer/checks')
            ->assertOk()
            ->assertJsonPath('body.checks_passed', true);
    }

    public function test_vue_shell_and_old_bookmarks_never_reset_or_finish_installation(): void
    {
        $tracker = app(InstallerState::class);
        $state = $tracker->read();
        $state['checks_passed'] = true;
        $tracker->save($state);
        foreach (['', '/requirements', '/permissions', '/configuration', '/database', '/admin', '/finish'] as $path) {
            $this->get('/installer'.$path)->assertOk()->assertSee('id="installer"', false)->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->assertSame($state, $tracker->read());
        $this->assertFileDoesNotExist($this->sandbox.'/framework/installed.json');
        $this->getJson('/installer/not-a-route')->assertNotFound();
    }

    public function test_page_embeds_only_public_state_for_immediate_rendering(): void
    {
        $tracker = app(InstallerState::class);
        $state = $tracker->read() + [
            'connection_fingerprint' => 'private-fingerprint',
        ];
        $state['checks_passed'] = true;
        $state['tasks'] = [['id' => 'seed', 'label' => 'Initialize data', 'status' => 'pending', 'path' => '/private/task']];
        $tracker->save($state);

        $page = $this->get('/installer')->assertOk();
        $page->assertDontSee('Loading setup')->assertDontSee('private-fingerprint')->assertDontSee('/private/task');
        preg_match('/data-state="([^"]+)"/', $page->getContent(), $matches);
        $initial = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($initial['checks_passed']);
        $this->assertArrayHasKey('options', $initial);
        $this->assertSame(['id' => 'seed', 'label' => 'Initialize data', 'status' => 'pending'], $initial['tasks'][0]);
        $this->assertSame($state, $tracker->read());
    }

    public function test_page_does_not_embed_another_sessions_setup(): void
    {
        config(['app.debug' => true]);
        $tracker = app(InstallerState::class);
        $tracker->save($tracker->read() + ['owner' => 'another-session']);
        $this->get('/installer')->assertStatus(409)
            ->assertSee('Continue your existing setup session')
            ->assertSee('What to do next')
            ->assertDontSee('another-session')
            ->assertDontSee('Exception trace')
            ->assertDontSee('data-state=', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('another-session', $tracker->read()['owner']);
    }

    public function test_page_read_failure_has_safe_support_instructions_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        $this->mock(InstallerState::class)->shouldReceive('snapshot')->once()
            ->andThrow(new RuntimeException('private-database-password'));

        $this->get('/installer')->assertStatus(503)
            ->assertSee('Setup could not open')
            ->assertSee('Support reference:')
            ->assertDontSee('private-database-password')
            ->assertDontSee('Exception trace')
            ->assertDontSee('data-state=', false);
    }

    public function test_installer_uses_built_assets_without_a_development_reload_client(): void
    {
        $vite = new Vite;
        app()->instance(Vite::class, $vite);
        File::makeDirectory(public_path('build'), 0755, true);
        File::put(public_path('hot'), 'http://localhost:3101');
        File::put(public_path('build/manifest.json'), json_encode([
            'Modules/Installer/resources/assets/js/main.ts' => [
                'file' => 'assets/installer-fixture.js',
                'src' => 'Modules/Installer/resources/assets/js/main.ts',
                'isEntry' => true,
            ],
        ], JSON_THROW_ON_ERROR));

        $this->get('/installer')->assertOk()
            ->assertSee('/build/assets/installer-fixture.js', false)
            ->assertDontSee('@vite/client', false)
            ->assertDontSee('localhost:3101', false);
        $this->assertTrue($vite->isRunningHot());
    }

    public function test_installer_can_use_development_assets_before_the_first_build(): void
    {
        app()->instance('env', 'local');
        app()->instance(Vite::class, new Vite);
        File::put(public_path('hot'), 'http://localhost:3101');
        $this->get('/installer')->assertOk()->assertSee('http://localhost:3101/@vite/client', false);
    }

    public function test_server_with_missing_build_and_stale_hot_file_shows_recovery_page(): void
    {
        app()->instance('env', 'production');
        config(['app.debug' => true]);
        app()->instance(Vite::class, new Vite);
        File::put(public_path('hot'), 'http://localhost:3101');

        $this->get('/installer')->assertStatus(503)
            ->assertSee('public/build')
            ->assertSee('Support reference:')
            ->assertDontSee('localhost:3101', false)
            ->assertDontSee('Exception trace');
    }

    public function test_status_failure_does_not_expose_debug_exception_details(): void
    {
        config(['app.debug' => true]);
        $this->mock(InstallerState::class)->shouldReceive('snapshot')->once()
            ->andThrow(new RuntimeException('private-database-password'));

        $this->getJson('/installer/status')->assertStatus(500)
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('trace')
            ->assertSee('support reference')
            ->assertDontSee('private-database-password');
    }

    public function test_encryption_failure_has_recovery_instructions_for_page_and_api(): void
    {
        config(['app.key' => null, 'app.debug' => true]);
        $this->mock(InstallerState::class)->shouldReceive('locked')->twice()
            ->andThrow(new RuntimeException('private-bootstrap-secret'));

        $this->get('/installer')->assertStatus(503)
            ->assertSee('Setup needs a server adjustment')
            ->assertSee('temporary create/rename access')
            ->assertSee('Support reference:')
            ->assertDontSee('private-bootstrap-secret');
        $this->getJson('/installer/status')->assertStatus(503)
            ->assertJsonStructure(['message'])
            ->assertSee('php artisan config:clear')
            ->assertDontSee('private-bootstrap-secret');
    }

    public function test_installation_actions_use_web_routes_and_old_api_routes_are_closed(): void
    {
        $this->get('/installer/status')
            ->assertOk()
            ->assertJsonPath('body.database', false)
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/installer/not-a-route')->assertNotFound();
        $this->getJson('/installer/checks')->assertMethodNotAllowed();
        $this->postJson('/installer/status')->assertMethodNotAllowed();

        // All product APIs remain unavailable until setup is complete.
        $this->getJson('/api/v1/installer/status')->assertStatus(503);
        foreach (['checks', 'configuration', 'database', 'process', 'admin', 'finish'] as $action) {
            $this->postJson('/api/v1/installer/'.$action)->assertStatus(503);
        }
        $this->assertFileDoesNotExist(app(InstallerState::class)->path());

        File::put($this->sandbox.'/framework/installed.json', '{}');
        $this->getJson('/api/v1/installer/status')->assertNotFound();
        $this->postJson('/api/v1/installer/checks')->assertNotFound();
    }

    public function test_web_actions_use_the_browser_session_for_csrf_and_installer_ownership(): void
    {
        $this->app['env'] = 'local';
        $this->mock(InstallerChecks::class)->shouldReceive('run')->once()->andReturn(['passed' => true, 'checks' => []]);
        $page = $this->get('/installer')->assertOk();
        preg_match('/name="csrf-token" content="([^"]+)"/', $page->getContent(), $matches);
        $this->assertNotEmpty($matches[1]);
        $cookie = $page->getCookie('forkiva_installer_session');
        $this->assertNotNull($cookie);
        $this->withCookie($cookie->getName(), $cookie->getValue());
        $tracker = app(InstallerState::class);
        $tracker->save($tracker->read() + ['owner' => hash('sha256', $matches[1])]);
        $this->withHeader('X-CSRF-TOKEN', $matches[1])
            ->postJson('/installer/checks')
            ->assertOk()
            ->assertJsonPath('body.checks_passed', true)
            ->assertJsonMissingPath('body.owner');
        $this->getJson('/installer/status')->assertOk()->assertJsonPath('body.checks_passed', true);
    }

    public function test_installed_web_status_returns_json_without_an_accept_header(): void
    {
        File::put($this->sandbox.'/framework/installed.json', '{}');
        $this->get('/installer/status')
            ->assertForbidden()
            ->assertJsonPath('message', 'Installation is already complete.');
    }

    public function test_checks_record_failures_and_block_database_setup(): void
    {
        $this->mock(InstallerChecks::class)->shouldReceive('run')->twice()->andReturn(['passed' => false, 'checks' => [['id' => 'php', 'passed' => false]]]);
        $this->postJson('/installer/checks')->assertOk()->assertJsonPath('body.checks_passed', false);
        $this->postJson('/installer/database', $this->databaseData())->assertUnprocessable()->assertJsonValidationErrors('database');
        $this->assertStringContainsString('DB_PASSWORD=original', File::get($this->sandbox.'/.env'));
    }

    private function databaseData(): array
    {
        return ['db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => 'installer_test', 'db_user' => 'installer', 'db_pass' => 'example', 'with_demo' => false];
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $this->postJson('/installer/process')->assertStatus(409);
        $this->postJson('/installer/admin', $this->adminData())->assertStatus(409);
        $this->postJson('/installer/finish', ['app_url' => 'https://example.com'])->assertStatus(409);
    }

    private function adminData(): array
    {
        return ['name' => 'Test Admin', 'username' => 'installer', 'email' => 'installer@example.test', 'password' => 'Example123!', 'password_confirmation' => 'Example123!'];
    }

    public function test_database_validation_rejects_invalid_ports_control_characters_and_flags(): void
    {
        foreach ([0, 65536, 3306.5, 'invalid'] as $port) {
            $this->postJson('/installer/database', array_replace($this->databaseData(), ['db_port' => $port]))->assertUnprocessable()->assertJsonValidationErrors('db_port');
        }
        $this->postJson('/installer/database', array_replace($this->databaseData(), ['db_pass' => "bad\nAPP_DEBUG=true", 'with_demo' => 'yes', 'db_host' => 'host;command', 'db_name' => '../database']))
            ->assertUnprocessable()->assertJsonValidationErrors(['db_pass', 'with_demo', 'db_host', 'db_name']);
    }

    /**
     * Verify server version handling without writing to a real database or environment.
     */
    #[DataProvider('databaseVersions')]
    public function test_database_version_errors_explain_the_required_server(string $version, bool $supported, string $detail): void
    {
        $this->mock(InstallerChecks::class)->shouldReceive('run')->once()->andReturn(['passed' => true, 'checks' => []]);
        $connection = Mockery::mock(MySqlConnection::class);
        $connection->shouldReceive('getPdo')->once()->andReturn(new PDO('sqlite::memory:'));
        $connection->shouldReceive('selectOne')->with('SELECT VERSION() AS version')->once()->andReturn((object) ['version' => $version]);
        DB::partialMock()->shouldReceive('connection')->with('installer_probe')->once()->andReturn($connection);
        if ($supported) {
            // Stop at the next guard so a recognized version never performs schema writes in this test.
            $schema = Mockery::mock(MySqlBuilder::class);
            $connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);
            $connection->shouldReceive('getDatabaseName')->once()->andReturn('installer_test');
            $schema->shouldReceive('getTables')->with('installer_test')->once()->andReturn([['name' => 'existing']]);
        }

        $state = app(InstallerState::class)->read();
        $state['configuration'] = $this->configurationData();
        app(InstallerState::class)->save($state);

        $field = $supported ? 'db_name' : 'database';
        $response = $this->postJson('/installer/database', $this->databaseData())
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertStringContainsString($detail, $response->json('errors.'.$field.'.0'));
        $this->assertFalse(app(InstallerState::class)->read()['database']);
        $this->assertStringContainsString('DB_PASSWORD=original', File::get($this->sandbox.'/.env'));
        $this->assertNull(config('database.connections.installer_probe'));
    }

    private function configurationData(): array
    {
        return [
            'app_name' => 'My Restaurant', 'app_url' => 'https://pos.example.test',
            'supported_countries' => ['JO', 'US'], 'default_country' => 'US',
            'supported_locales' => ['en', 'ar'], 'default_locale' => 'ar',
            'default_timezone' => 'America/New_York',
            'supported_currencies' => ['USD', 'JOD'], 'default_currency' => 'USD',
            'passkeys_enabled' => true, 'passkeys_relying_party_id' => 'example.test',
            'passkeys_allowed_origins' => ['https://pos.example.test', 'https://order.example.test'],
            'cache_enabled' => true, 'cache_store' => 'file',
        ];
    }

    public function test_admin_validation_requires_password_confirmation_and_application_password_policy(): void
    {
        $this->postJson('/installer/admin', array_replace($this->adminData(), ['password_confirmation' => 'different']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/installer/admin', array_replace($this->adminData(), ['password' => 'weak', 'password_confirmation' => 'weak', 'email' => 'invalid', 'username' => 'bad user']))->assertUnprocessable()->assertJsonValidationErrors(['password', 'email', 'username']);
    }

    public function test_finish_rejects_non_http_urls(): void
    {
        $this->postJson('/installer/finish', ['app_url' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('app_url');
    }

    public function test_durable_marker_locks_all_mutations(): void
    {
        File::put($this->sandbox.'/framework/installed.json', '{}');
        $this->postJson('/installer/checks')->assertForbidden();
        $this->get('/installer')->assertRedirect('/auth/login');
        foreach (['checks', 'configuration', 'database', 'process', 'admin', 'finish'] as $path) {
            $this->postJson('/installer/'.$path)->assertForbidden();
        }
    }

    public function test_api_is_unavailable_while_seeded_admin_is_not_secured(): void
    {
        $this->postJson('/api/v1/auth/login', ['identifier' => 'admin', 'password' => 'example'])->assertStatus(503);
    }

    public function test_another_browser_cannot_take_over_configured_installation(): void
    {
        $tracker = app(InstallerState::class);
        $tracker->save($tracker->read() + ['owner' => hash('sha256', 'another-session')]);
        $this->getJson('/installer/status')->assertStatus(409);
        $this->postJson('/installer/checks')->assertStatus(409);
    }

    public function test_status_distinguishes_a_live_worker_from_interrupted_progress(): void
    {
        $tracker = app(InstallerState::class);
        $state = $tracker->read();
        $state['tasks'] = [['id' => 'seed', 'label' => 'Initialize data', 'status' => 'running']];
        $tracker->save($state);
        $handle = fopen($this->sandbox.'/framework/installer.lock', 'c');
        flock($handle, LOCK_EX);
        try {
            $this->getJson('/installer/status')->assertOk()->assertJsonPath('body.processing', true);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        $this->getJson('/installer/status')->assertOk()->assertJsonPath('body.processing', false)
            ->assertJsonPath('body.tasks.0.status', 'running');
        $this->assertSame($state, $tracker->read());
    }

    public function test_concurrent_requests_do_not_run_installation_commands(): void
    {
        $handle = fopen($this->sandbox.'/framework/installer.lock', 'c');
        flock($handle, LOCK_EX);
        try {
            $this->postJson('/installer/process')->assertStatus(409)->assertJsonPath('message', 'Another installation action is running. Wait, then refresh the status.');
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function test_failed_command_does_not_complete_a_task_or_leak_exception_details(): void
    {
        $tracker = app(InstallerState::class);
        $state = $this->configuredState();
        $state['tasks'] = [['id' => 'host', 'label' => 'Create application tables', 'kind' => 'migration', 'path' => '/temporary', 'status' => 'pending']];
        $tracker->save($state);
        Artisan::shouldReceive('call')->once()->andReturn(1);
        $this->postJson('/installer/process')->assertAccepted()->assertJsonPath('body.processing', true)->assertDontSee('password');
        $this->getJson('/installer/status')->assertOk()->assertJsonPath('body.tasks.0.status', 'failed')->assertJsonPath('body.processing', false);
        $this->assertFalse($tracker->read()['admin']);
        $this->postJson('/installer/admin', $this->adminData())->assertStatus(409);
    }

    private function configuredState(): array
    {
        $state = app(InstallerState::class)->read();
        $state['configuration'] = $this->configurationData();
        $state['database'] = true;
        $connection = config('database.connections.sqlite');
        $state['connection_fingerprint'] = hash_hmac('sha256', json_encode(array_map(fn ($key) => (string) ($connection[$key] ?? ''), ['driver', 'url', 'host', 'port', 'database', 'username', 'password', 'unix_socket'])), config('app.key'));

        return $state;
    }

    public function test_process_acknowledges_before_work_and_holds_the_lock_until_termination(): void
    {
        $tracker = app(InstallerState::class);
        $state = $this->configuredState();
        $state['tasks'] = [['id' => 'slow', 'label' => 'Initialize data', 'kind' => 'migration', 'path' => '/fixture', 'status' => 'pending']];
        $tracker->save($state);
        $ran = false;
        Artisan::shouldReceive('call')->once()->andReturnUsing(function () use (&$ran) {
            $ran = true;

            return 0;
        });
        $controller = app(InstallerController::class);
        $response = $controller->process();
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame((string) strlen($response->getContent()), $response->headers->get('Content-Length'));
        $this->assertFalse($ran);
        $this->assertTrue($tracker->snapshot()['processing']);
        $this->assertSame('running', $tracker->read()['tasks'][0]['status']);
        $this->assertSame(409, $controller->process()->getStatusCode());

        app()->terminate();
        $this->assertTrue($ran);
        $this->assertFalse($tracker->snapshot()['processing']);
        $this->assertSame('complete', $tracker->read()['tasks'][0]['status']);
        app()->terminate();
    }

    public function test_process_completes_only_one_task_per_request_and_does_not_repeat_completed_tasks(): void
    {
        $tracker = app(InstallerState::class);
        $state = $this->configuredState();
        $state['tasks'] = [
            ['id' => 'first', 'label' => 'First', 'kind' => 'migration', 'path' => '/first', 'status' => 'complete'],
            ['id' => 'second', 'label' => 'Second', 'kind' => 'migration', 'path' => '/second', 'status' => 'pending'],
        ];
        $tracker->save($state);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--path' => ['/second'], '--realpath' => true, '--force' => true])->andReturn(0);
        $this->postJson('/installer/process')->assertAccepted()->assertJsonPath('body.tasks.1.status', 'running')->assertJsonMissingPath('body.tasks.1.path');
        $this->getJson('/installer/status')->assertOk()->assertJsonPath('body.tasks.1.status', 'complete');
        $this->postJson('/installer/process')->assertOk()->assertJsonPath('body.tasks.1.status', 'complete');
    }

    public function test_database_configuration_changes_block_resume(): void
    {
        $tracker = app(InstallerState::class);
        $state = $this->configuredState();
        $tracker->save($state);
        config(['database.connections.sqlite.database' => '/changed']);
        $this->postJson('/installer/process')->assertStatus(409)->assertJsonPath('message', 'Database configuration changed during setup. Restore the verified configuration before continuing.');
    }

    public function test_seed_data_and_receipt_commit_together_and_lost_response_does_not_duplicate_seed(): void
    {
        DB::purge('sqlite');
        Schema::create('installation_tasks', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->timestamp('completed_at');
        });
        Schema::create('installer_test_records', function (Blueprint $table) {
            $table->id();
        });
        $service = app(InstallationServiceInterface::class);
        $fail = true;
        Artisan::shouldReceive('call')->andReturnUsing(function () use (&$fail) {
            DB::table('installer_test_records')->insert(['id' => 1]);
            if ($fail) {
                throw new RuntimeException('Example failure');
            }

            return 0;
        });
        $seed = fn () => (new ReflectionMethod($service, 'runTask'))->invoke($service, ['kind' => 'seed', 'id' => 'seed:test', 'class' => 'TestSeeder']);
        try {
            $seed();
        } catch (RuntimeException) {
        }
        $this->assertSame(0, DB::table('installer_test_records')->count());
        $this->assertSame(0, DB::table('installation_tasks')->count());
        $fail = false;
        $seed();
        $seed();
        $this->assertSame(1, DB::table('installer_test_records')->count());
        $this->assertSame(1, DB::table('installation_tasks')->count());
    }

    public function test_environment_values_round_trip_without_interpolation_or_secret_exposure(): void
    {
        $values = ['DB_PASSWORD' => '  p$ss${APP_NAME}#"\\end  ', 'DB_HOST' => '127.0.0.1', 'APP_DEBUG' => 'false'];
        app(InstallerEnvironment::class)->write($values);
        $parsed = Dotenv::parse(File::get($this->sandbox.'/.env'));
        foreach ($values as $key => $value) {
            $this->assertSame($value, $parsed[$key]);
        }
        $this->assertSame('Forkiva', $parsed['APP_NAME']);
        $this->getJson('/installer/status')->assertOk()->assertDontSee('DB_PASSWORD');
    }

    public function test_environment_creates_missing_file_and_preserves_existing_key(): void
    {
        File::delete($this->sandbox.'/.env');
        app(InstallerEnvironment::class)->write(['APP_KEY' => 'test-existing-key']);
        app(InstallerEnvironment::class)->write(['DB_DATABASE' => 'example_test']);
        $this->assertSame('test-existing-key', Dotenv::parse(File::get($this->sandbox.'/.env'))['APP_KEY']);
    }

    public function test_missing_application_key_is_initialized_before_session_and_csrf(): void
    {
        config(['app.key' => null]);
        $this->get('/installer')->assertOk();
        $values = Dotenv::parse(File::get($this->sandbox.'/.env'));
        $this->assertStringStartsWith('base64:', $values['APP_KEY']);
        $this->assertSame(32, strlen(base64_decode(substr($values['APP_KEY'], 7))));
        $key = $values['APP_KEY'];
        $this->get('/installer')->assertOk();
        $this->assertSame($key, Dotenv::parse(File::get($this->sandbox.'/.env'))['APP_KEY']);
    }

    public function test_csrf_is_required_for_mutations_outside_testing_bypass(): void
    {
        $this->app['env'] = 'local';
        foreach (['checks', 'configuration', 'database', 'process', 'admin', 'finish'] as $action) {
            $this->postJson('/installer/'.$action)->assertStatus(419);
        }
        $this->assertFileDoesNotExist(app(InstallerState::class)->path());
    }

    public function test_real_permission_checks_inspect_nested_runtime_directories(): void
    {
        File::deleteDirectory($this->sandbox.'/framework/views');
        $result = app(InstallerChecks::class)->run();
        $permission = collect($result['checks'])->firstWhere('id', 'permissions');
        $this->assertFalse($permission['passed']);
        $this->assertStringContainsString('storage/framework/views', $permission['detail']);
        $this->assertStringContainsString('hosting support', $permission['remedy']);
        $this->assertSame([], File::glob($this->sandbox.'/framework/.installer-probe-*'));
    }

    /**
     * Settings can live outside a read-only application release directory.
     */
    public function test_application_root_does_not_need_write_access_when_settings_are_elsewhere(): void
    {
        $originalBase = base_path();
        $release = $this->sandbox.'/readonly-release';
        File::makeDirectory($release.'/bootstrap/cache', 0755, true);
        $this->mock(\Modules\Installer\Services\Dependencies\InstallerDependenciesInterface::class)
            ->shouldReceive('run')->andReturn(array_fill_keys(['php', 'extensions', 'dependencies'], ['passed' => true, 'detail' => 'Ready', 'remedy' => '']));
        chmod($release, 0555);
        try {
            app()->setBasePath($release);
            $checks = collect(app(InstallerChecks::class)->run()['checks']);
            $this->assertTrue($checks->firstWhere('id', 'permissions')['passed']);
            $this->assertTrue($checks->firstWhere('id', 'environment')['passed']);
            $this->assertStringContainsString($this->sandbox, $checks->firstWhere('id', 'environment')['detail']);
        } finally {
            app()->setBasePath($originalBase);
            chmod($release, 0755);
        }
    }

    public function test_settings_folder_permission_failure_has_actionable_instructions(): void
    {
        chmod($this->sandbox, 0555);
        try {
            $check = collect(app(InstallerChecks::class)->run()['checks'])->firstWhere('id', 'environment');
            $this->assertFalse($check['passed']);
            $this->assertStringContainsString('file-only write permission is not enough', $check['remedy']);
            $this->assertStringContainsString('Restore the previous folder permissions', $check['remedy']);
        } finally {
            chmod($this->sandbox, 0755);
        }
    }

    public function test_built_assets_check_includes_imported_chunks_and_stylesheets(): void
    {
        File::makeDirectory($this->sandbox.'/public/build', 0755, true);
        $entry = 'Modules/Installer/resources/assets/js/main.ts';
        File::put($this->sandbox.'/public/build/manifest.json', json_encode([
            $entry => ['file' => 'installer.js', 'css' => ['installer.css'], 'imports' => ['shared']],
            'shared' => ['file' => 'shared.js'],
        ]));
        File::put($this->sandbox.'/public/build/installer.js', '// fixture');
        File::put($this->sandbox.'/public/build/installer.css', '/* fixture */');
        $result = app(InstallerChecks::class)->run();
        $this->assertFalse(collect($result['checks'])->firstWhere('id', 'assets')['passed']);
        File::put($this->sandbox.'/public/build/shared.js', '// fixture');
        $result = app(InstallerChecks::class)->run();
        $this->assertTrue(collect($result['checks'])->firstWhere('id', 'assets')['passed']);
    }

    public function test_live_mysql_verification_scopes_empty_database_and_preserves_environment_on_failure(): void
    {
        $port = getenv('INSTALLER_MYSQL_TEST_PORT');
        if (! $port) {
            $this->markTestSkipped('Set INSTALLER_MYSQL_TEST_PORT and provide an empty forkiva_installer_probe_test database.');
        }
        $this->mock(InstallerChecks::class)->shouldReceive('run')->andReturn(['passed' => true, 'checks' => []]);
        $state = app(InstallerState::class)->read();
        $state['configuration'] = $this->configurationData();
        app(InstallerState::class)->save($state);
        $data = array_replace($this->databaseData(), ['db_port' => (int) $port, 'db_name' => 'forkiva_installer_probe_test', 'db_user' => 'root', 'db_pass' => '']);
        $this->postJson('/installer/database', array_replace($data, ['db_port' => 13317]))->assertUnprocessable()->assertJsonValidationErrors('database');
        $this->assertStringContainsString('DB_PASSWORD=original', File::get($this->sandbox.'/.env'));
        config(['database.connections.installer_fixture' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => (int) $port,
            'database' => 'forkiva_installer_probe_test', 'username' => 'root', 'password' => '',
        ]]);
        $fixture = DB::connection('installer_fixture');
        $this->assertSame([], $fixture->getSchemaBuilder()->getTables('forkiva_installer_probe_test'));
        $fixture->getSchemaBuilder()->create('installer_test_existing', fn (Blueprint $table) => $table->id());
        try {
            $this->postJson('/installer/database', $data)->assertUnprocessable()->assertJsonValidationErrors('db_name');
            $this->assertStringContainsString('DB_PASSWORD=original', File::get($this->sandbox.'/.env'));
        } finally {
            $fixture->getSchemaBuilder()->drop('installer_test_existing');
            DB::purge('installer_fixture');
        }
        $this->postJson('/installer/database', $data)->assertOk()->assertJsonPath('body.database', true)->assertJsonMissingPath('body.connection_fingerprint');
        $this->assertSame([], DB::connection()->getSchemaBuilder()->getTables('forkiva_installer_probe_test'));
        DB::disconnect('mysql');
    }

    public static function installationDatabases(): array
    {
        return [
            'mysql' => ['mysql', 'INSTALLER_MYSQL_TEST_PORT', 'forkiva_installer_test', 'root', false],
            'mysql recovery' => ['mysql', 'INSTALLER_MYSQL_TEST_PORT', 'forkiva_installer_recovery_test', 'root', false, true],
            'postgresql' => ['pgsql', 'INSTALLER_PGSQL_TEST_PORT', 'forkiva_installer_pg_test', getenv('INSTALLER_PGSQL_TEST_USER') ?: 'postgres', false],
            'postgresql demo' => ['pgsql', 'INSTALLER_PGSQL_DEMO_TEST_PORT', 'forkiva_installer_pg_demo_test', getenv('INSTALLER_PGSQL_TEST_USER') ?: 'postgres', true],
        ];
    }

    #[DataProvider('installationDatabases')]
    public function test_complete_installation_on_disposable_database(string $driver, string $portVariable, string $database, string $username, bool $withDemo, bool $recover = false): void
    {
        $port = getenv($portVariable);
        if (! $port) {
            $this->markTestSkipped("Set {$portVariable} for the disposable {$database} database.");
        }
        $this->mock(InstallerChecks::class)->shouldReceive('run')->andReturn(['passed' => true, 'checks' => []]);
        $this->postJson('/installer/checks')->assertOk();
        $this->postJson('/installer/configuration', $this->configurationData())->assertOk();
        $this->postJson('/installer/database', array_replace($this->databaseData(), [
            'db_connection' => $driver, 'db_port' => (int) $port, 'db_name' => $database, 'db_user' => $username, 'db_pass' => '', 'with_demo' => $withDemo,
        ]))->assertOk()->assertJsonPath('body.database', true);
        $tracker = app(InstallerState::class);
        if ($recover) {
            // Reproduce the deployed pre-fix DDL, then recover the partial table without a reset.
            DB::connection()->setSchemaGrammar(new \Illuminate\Database\Schema\Grammars\MySqlGrammar(DB::connection()));
        }
        foreach ($tracker->read()['tasks'] as $task) {
            $this->postJson('/installer/process')->assertAccepted();
            $response = $this->getJson('/installer/status')->assertOk();
            if ($recover && $task['id'] === 'migrate:Delivery') {
                $this->assertStringContainsString('foreign key error 1215', $response->json('body.error'));
                DB::connection()->setSchemaGrammar(new \Modules\Core\Database\MySqlSchemaGrammar(DB::connection()));
                $tables = Schema::getTableListing();
                DB::table('delivery_driver_assignments')->insert(['id' => 1, 'order_delivery_id' => 1, 'branch_id' => 1, 'driver_id' => 1]);
                $this->artisan('delivery:repair-installation', ['--apply' => true])->assertFailed();
                $this->assertSame(1, DB::table('delivery_driver_assignments')->count());
                DB::table('delivery_driver_assignments')->delete();
                $saved = $tracker->read();
                $tracker->save(array_replace($saved, ['connection_fingerprint' => 'different-connection']));
                $this->artisan('delivery:repair-installation', ['--apply' => true])->assertFailed();
                $tracker->save($saved);
                $this->artisan('delivery:repair-installation')->assertSuccessful();
                $this->assertSame($tables, Schema::getTableListing());
                $this->artisan('delivery:repair-installation', ['--apply' => true])->assertSuccessful();
                $backups = array_values(array_filter(Schema::getTableListing(), fn ($table) => str_contains($table, 'delivery_driver_assignments_setup_backup_')));
                $this->assertCount(1, $backups);
                $this->assertSame(0, DB::table($backups[0])->count());
                $this->assertCount(4, Schema::getForeignKeys('delivery_driver_assignments'));
                $this->artisan('delivery:repair-installation', ['--apply' => true])->assertFailed();
                $this->postJson('/installer/process')->assertAccepted();
                $response = $this->getJson('/installer/status')->assertOk();
            }
            $this->assertNull($response->json('body.error'), $task['label'].' did not complete.');
        }
        $this->assertTrue(collect($tracker->read()['tasks'])->every(fn ($task) => $task['status'] === 'complete'));
        $this->assertGreaterThan(0, DB::table('installation_tasks')->count());
        $this->postJson('/installer/admin', $this->adminData())->assertOk()->assertJsonPath('body.admin', true);
        $admin = User::findOrFail(1);
        $this->assertTrue(Hash::check('Example123!', $admin->password));
        $this->assertSame('installer', $admin->username);
        $this->assertTrue($admin->hasRole(DefaultRole::SuperAdmin->value));
        $this->postJson('/installer/finish', ['app_url' => 'https://pos.example.test'])->assertOk()->assertJsonPath('body.finished', true);
        $this->assertFileExists($this->sandbox.'/framework/installed.json');
        $values = Dotenv::parse(File::get($this->sandbox.'/.env'));
        $this->assertSame($driver, $values['DB_CONNECTION']);
        $this->assertSame($driver === 'pgsql' ? 'utf8' : 'utf8mb4', $values['DB_CHARSET']);
        $this->assertSame('true', $values['APP_INSTALLED']);
        $this->assertSame('false', $values['APP_DEBUG']);
        $this->assertSame('production', $values['APP_ENV']);
        $this->assertSame('https://pos.example.test', $values['APP_URL']);
        $this->assertTrue(is_link($this->sandbox.'/public/storage'));
        $this->postJson('/installer/process')->assertForbidden();
        $this->assertGreaterThan(1, User::factory()->create()->id);
        if ($driver === 'mysql') {
            $this->assertSame('virtual', collect(Schema::getColumns('delivery_driver_assignments'))->firstWhere('name', 'current_order_delivery_id')['generation']['type']);
            $this->assertSame('virtual', collect(Schema::getColumns('delivery_driver_earning_entries'))->firstWhere('name', 'per_delivery_unique_key')['generation']['type']);
        }
        DB::disconnect($driver);
    }

    public function test_configuration_requires_checks_and_cannot_be_changed_after_database_setup(): void
    {
        $this->postJson('/installer/configuration', $this->configurationData())->assertStatus(409);
        $state = $this->configuredState();
        $state['checks_passed'] = true;
        app(InstallerState::class)->save($state);
        $this->postJson('/installer/configuration', $this->configurationData())->assertStatus(409);
    }

    public function test_database_cannot_skip_application_configuration(): void
    {
        $this->mock(InstallerChecks::class)->shouldReceive('run')->once()->andReturn(['passed' => true, 'checks' => []]);
        $this->postJson('/installer/database', $this->databaseData())->assertStatus(409);
    }

    public function test_configuration_rotates_keys_once_and_preserves_installer_cookie_decryption(): void
    {
        $oldKey = config('app.key');
        $oldEncrypter = new Encrypter(base64_decode(substr($oldKey, 7)), config('app.cipher'));
        $cookie = $oldEncrypter->encryptString('installer-session');
        $state = app(InstallerState::class)->read();
        $state['checks_passed'] = true;
        app(InstallerState::class)->save($state);
        $response = $this->postJson('/installer/configuration', $this->configurationData())->assertOk();
        $values = Dotenv::parse(File::get($this->sandbox.'/.env'));
        $this->assertNotSame($oldKey, $values['APP_KEY']);
        $this->assertSame(32, strlen(base64_decode($values['VITE_STORAGE_ENCRYPTION_KEY'], true)));
        $encrypter = new Encrypter(base64_decode(substr($values['APP_KEY'], 7)), config('app.cipher'));
        $encrypter->previousKeys(array_map(fn ($key) => base64_decode(substr($key, 7)), explode(',', $values['APP_PREVIOUS_KEYS'])));
        $this->assertSame('installer-session', $encrypter->decryptString($cookie));
        $this->assertStringNotContainsString($values['APP_KEY'], $response->getContent());
        $this->assertStringNotContainsString($values['VITE_STORAGE_ENCRYPTION_KEY'], $response->getContent());
        $this->postJson('/installer/configuration', array_replace($this->configurationData(), ['cache_store' => 'database', 'cache_enabled' => false]))->assertOk();
        $updated = Dotenv::parse(File::get($this->sandbox.'/.env'));
        $this->assertSame($values['APP_KEY'], $updated['APP_KEY']);
        $this->assertSame($values['VITE_STORAGE_ENCRYPTION_KEY'], $updated['VITE_STORAGE_ENCRYPTION_KEY']);
        foreach (['CACHE_STORE', 'PASSKEYS_CACHE_STORE', 'TWO_FACTOR_CACHE_STORE', 'PASSWORD_RECOVERY_CACHE_STORE', 'FIREBASE_CACHE_STORE', 'SESSION_STORE', 'APP_MAINTENANCE_STORE'] as $key) {
            $this->assertSame('database', $updated[$key]);
        }
        $this->assertSame('false', $updated['CACHE_ENABLED']);
        $this->assertSame('file', $updated['CART_MUTATION_LOCK_STORE']);
        $this->get('/installer/status')->assertOk()->assertJsonPath('body.configuration.default_currency', 'USD');
    }

    #[DataProvider('invalidConfigurations')]
    public function test_configuration_validates_regional_defaults_and_passkey_origins(array $changes, string $field): void
    {
        $this->postJson('/installer/configuration', array_replace($this->configurationData(), $changes))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertStringContainsString('DB_PASSWORD=original', File::get($this->sandbox.'/.env'));
        $this->assertArrayNotHasKey('INSTALLER_KEYS_GENERATED', Dotenv::parse(File::get($this->sandbox.'/.env')));
    }

    public function test_configuration_seeds_selected_defaults_before_dependent_records(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('payload')->nullable();
            $table->boolean('is_translatable')->default(false);
            $table->boolean('is_encryptable')->default(false);
            $table->timestamps();
        });
        Schema::create('installation_tasks', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->timestamp('completed_at');
        });
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency')->unique();
            $table->decimal('rate', 8, 4)->nullable();
            $table->timestamps();
        });
        $state = $this->configuredState();
        $class = SettingDatabaseSeeder::class;
        $state['tasks'] = [['id' => 'seed:'.$class, 'kind' => 'seed', 'class' => $class, 'label' => 'Settings', 'status' => 'pending']];
        app(InstallerState::class)->save($state);
        $this->postJson('/installer/process')->assertAccepted();
        $this->getJson('/installer/status')->assertOk()->assertJsonPath('body.tasks.0.status', 'complete');
        $this->assertSame('USD', Setting::get('default_currency'));
        $this->assertSame('US', Setting::get('default_country'));
        $this->assertSame('ar', Setting::get('default_locale'));
        $this->assertSame('America/New_York', Setting::get('default_timezone'));
        $this->assertSame('My Restaurant', setting('app_name'));
        $this->assertSame('USD', setting('default_currency'));
        $this->assertEqualsCanonicalizing(['USD', 'JOD'], DB::table('currency_rates')->pluck('currency')->all());
        $this->postJson('/installer/process')->assertOk();
        $this->assertSame(1, DB::table('installation_tasks')->count());
    }

    public function test_cache_is_isolated_until_installation_finishes(): void
    {
        $provider = app()->getProvider(CoreServiceProvider::class);
        $method = new ReflectionMethod($provider, 'setupAppCacheDriver');
        config(['cache.enabled' => true, 'cache.default' => 'database']);
        $method->invoke($provider);
        $this->assertSame('array', config('cache.default'));
        File::put($this->sandbox.'/framework/installed.json', '{}');
        config(['cache.default' => 'database']);
        $method->invoke($provider);
        $this->assertSame('database', config('cache.default'));
    }

    public function test_file_and_database_cache_support_tags_and_exclusive_locks(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
        config(['cache.stores.database.connection' => 'sqlite', 'cache.stores.database.lock_connection' => 'sqlite',
            'cache.stores.file.path' => $this->sandbox.'/framework/cache/data', 'cache.stores.file.lock_path' => $this->sandbox.'/framework/cache/data']);
        foreach (['database', 'file'] as $store) {
            Cache::forgetDriver($store);
            app(InstallerConfigurationInterface::class)->verifyCache($store);
            $this->assertTrue(Cache::store($store)->supportsTags());
        }
    }

    public function test_finish_clears_persistent_cache_even_when_setup_uses_array_cache(): void
    {
        config(['cache.stores.file.path' => $this->sandbox.'/framework/cache/data']);
        Cache::forgetDriver('file');
        Cache::store('file')->put('installer-stale-setting', 'old value', 600);
        $kernel = app(\Illuminate\Contracts\Console\Kernel::class);
        Artisan::shouldReceive('call')->once()->ordered()
            ->with('cache:clear', ['store' => 'file'])
            ->andReturnUsing(fn ($command, $arguments) => $kernel->call($command, $arguments));
        Artisan::shouldReceive('call')->once()->ordered()
            ->with('optimize:clear', ['--except' => 'cache'])->andReturn(0);

        $service = app(InstallationServiceInterface::class);
        (new ReflectionMethod($service, 'clearInstallationCaches'))->invoke($service, 'file');

        $this->assertSame('array', config('cache.default'));
        $this->assertNull(Cache::store('file')->get('installer-stale-setting'));
    }

    public function test_finish_stops_when_the_selected_database_cache_cannot_be_cleared(): void
    {
        Artisan::shouldReceive('call')->once()->with('cache:clear', ['store' => 'database'])->andReturn(1);
        Artisan::shouldReceive('call')->with('optimize:clear', Mockery::any())->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Installation command failed: cache:clear');
        $service = app(InstallationServiceInterface::class);
        (new ReflectionMethod($service, 'clearInstallationCaches'))->invoke($service, 'database');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = sys_get_temp_dir().'/forkiva-installer-test-'.Str::uuid();
        $this->originalStorage = storage_path();
        $this->originalEnvironment = app()->environmentPath();
        $this->originalPublic = public_path();
        $this->originalBootstrap = app()->bootstrapPath();
        File::ensureDirectoryExists($this->sandbox.'/bootstrap/cache');
        foreach (['framework/sessions', 'framework/views', 'framework/cache', 'app/public', 'logs', 'public'] as $path) {
            File::makeDirectory($this->sandbox.'/'.$path, 0755, true);
        }
        app()->useStoragePath($this->sandbox);
        app()->useEnvironmentPath($this->sandbox);
        app()->usePublicPath($this->sandbox.'/public');
        app()->useBootstrapPath($this->sandbox.'/bootstrap');
        config(['filesystems.links' => [$this->sandbox.'/public/storage' => $this->sandbox.'/app/public']]);
        config(['view.compiled' => $this->sandbox.'/framework/views']);
        File::put($this->sandbox.'/.env', "APP_NAME=Forkiva\nAPP_INSTALLED=false\nDB_PASSWORD=original\n");
        config(['app.installed' => false, 'app.key' => 'base64:'.base64_encode(random_bytes(32)), 'session.files' => $this->sandbox.'/framework/sessions', 'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        app()->useStoragePath($this->originalStorage);
        app()->useEnvironmentPath($this->originalEnvironment);
        app()->usePublicPath($this->originalPublic);
        app()->useBootstrapPath($this->originalBootstrap);
        File::deleteDirectory($this->sandbox);
        parent::tearDown();
    }
}
