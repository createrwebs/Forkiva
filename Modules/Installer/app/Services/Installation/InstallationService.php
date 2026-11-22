<?php

namespace Modules\Installer\Services\Installation;

use Faker\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Installer\Services\Checks\InstallerChecksInterface;
use Modules\Installer\Services\Configuration\InstallerConfigurationInterface;
use Modules\Installer\Services\DatabaseProbe\InstallerDatabaseProbeInterface;
use Modules\Installer\Services\Environment\InstallerEnvironmentInterface;
use Modules\Installer\Services\State\InstallerStateInterface;
use Modules\Installer\Support\InstallerFailure;
use Modules\Setting\Database\Seeders\SettingDatabaseSeeder;
use Modules\User\Enums\DefaultRole;
use Modules\User\Enums\GenderType;
use Modules\User\Models\User;
use Nwidart\Modules\Facades\Module;
use PDO;
use RuntimeException;
use Throwable;

readonly class InstallationService implements InstallationServiceInterface
{
    /**
     * Create the installation service with persistence and readiness checks.
     */
    public function __construct(
        private InstallerStateInterface $tracker,
        private InstallerChecksInterface $checks,
        private InstallerEnvironmentInterface $environment,
        private InstallerDatabaseProbeInterface $probe,
        private InstallerConfigurationInterface $configuration,
    ) {}

    /** {@inheritDoc} */
    public function configuration(array $state, array $data): array
    {
        abort_if(
            $state['database'],
            409,
            'Configuration is locked after database preparation starts.'
        );

        abort_unless(
            $state['checks_passed'],
            409,
            'Complete the server checks first.'
        );

        $this->configuration->persist($data);

        $state['configuration'] = $data;
        $state['owner'] = $this->tracker->owner();

        $this->tracker->save($state);

        return $state;
    }

    /** {@inheritDoc} */
    public function database(array $state, array $data): array
    {
        abort_if(
            $state['database'],
            409,
            'Database configuration is already saved. Continue installation.'
        );

        $state = $this->check($state);

        if (! $state['checks_passed']) {
            throw ValidationException::withMessages([
                'database' => 'Resolve all server checks before configuring the database.',
            ]);
        }

        abort_unless(
            ! empty($state['configuration']),
            409,
            'Save the application configuration first.'
        );

        if ($data['with_demo'] && ! class_exists(Factory::class)) {
            throw ValidationException::withMessages([
                'with_demo' => 'Sample data requires the Faker development dependency. '
                    .'Disable sample data for this production installation.',
            ]);
        }

        $driver = $data['db_connection'] ?? 'mysql';
        if (! in_array($driver, ['mysql', 'pgsql'], true) || ! extension_loaded('pdo_'.$driver)) {
            throw ValidationException::withMessages([
                'db_connection' => 'Enable the PDO extension for the selected database in your web PHP configuration.',
            ]);
        }

        $connection = array_replace(
            config('database.connections.'.$driver),
            [
                'url' => null,
                'unix_socket' => '',
                'host' => $data['db_host'],
                'port' => (int) $data['db_port'],
                'database' => $data['db_name'],
                'username' => $data['db_user'],
                'password' => $data['db_pass'] ?? '',
                'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            ]
        );

        if ($driver === 'mysql') {
            $connection['options'][PDO::ATTR_TIMEOUT] = 5;
        }

        config([
            'database.connections.installer_probe' => $connection,
        ]);

        DB::purge('installer_probe');

        try {
            $db = DB::connection('installer_probe');

            $db->getPdo();

            $version = (string) $db
                ->selectOne('SELECT VERSION() AS version')
                ->version;

            if ($driver === 'pgsql') {
                if ((int) $db->selectOne('SHOW server_version_num')->server_version_num < 170000) {
                    throw ValidationException::withMessages(['database' => 'Use PostgreSQL 17 or newer.']);
                }
                if ($db->selectOne('SHOW server_encoding')->server_encoding !== 'UTF8') {
                    throw ValidationException::withMessages(['db_name' => 'Create the PostgreSQL database with UTF8 encoding to support all application languages.']);
                }
            } else {
                $this->assertDatabaseVersion($version);
            }

            if (
                $db->getSchemaBuilder()->getTables($driver === 'pgsql' ? 'public' : $db->getDatabaseName()) !== []
                || $db->getSchemaBuilder()->getViews($driver === 'pgsql' ? 'public' : $db->getDatabaseName()) !== []
            ) {
                throw ValidationException::withMessages([
                    'db_name' => 'Choose an empty database. '
                        .'Existing databases are never overwritten by the installer.',
                ]);
            }

            $this->probe->run($db);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $reference = InstallerFailure::record($exception, 'database verification');
            throw ValidationException::withMessages([
                'database' => InstallerFailure::databaseMessage($exception).' Support reference: '.$reference.'.',
            ]);
        } finally {
            DB::purge('installer_probe');

            config([
                'database.connections.installer_probe' => null,
            ]);
        }

        $this->environment->write([
            'DB_CONNECTION' => $driver,
            'DB_CHARSET' => $connection['charset'],
            'DB_URL' => '',
            'DB_SOCKET' => '',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => $data['db_port'],
            'DB_DATABASE' => $data['db_name'],
            'DB_USERNAME' => $data['db_user'],
            'DB_PASSWORD' => $data['db_pass'] ?? '',
            'APP_SEED_DEMO_DATA' => $data['with_demo'] ? 'true' : 'false',
        ]);

        config([
            'database.default' => $driver,
            'database.connections.'.$driver => $connection,
            'app.seed_demo_data' => $data['with_demo'],
        ]);

        DB::purge($driver);

        $state['database'] = true;
        $state['owner'] = $this->tracker->owner();
        $state['connection_fingerprint'] = $this->fingerprint();
        $state['tasks'] = $this->tasks();

        $this->tracker->save($state);

        return $state;
    }

    /** {@inheritDoc} */
    public function check(array $state): array
    {
        $result = $this->checks->run();

        $state['checks_passed'] = $result['passed'];
        $state['checks'] = $result['checks'];

        $this->tracker->save($state);

        return $state;
    }

    /**
     * Reject unsupported server versions before any schema or environment changes.
     *
     * @throws ValidationException
     */
    private function assertDatabaseVersion(string $version): void
    {
        $maria = stripos($version, 'MariaDB') !== false;

        // MariaDB may prepend a MySQL compatibility version
        // before its actual version.
        $normalized = $maria
            ? preg_replace('/^5\.5\.5-/', '', trim($version))
            : trim($version);

        if (
            ! preg_match(
                '/^(\d+\.\d+\.\d+)(?:[-+].*)?$/',
                $normalized,
                $matches
            )
        ) {
            throw ValidationException::withMessages([
                'database' => 'The database server version could not be recognized. '
                    .'Use MySQL 8.0.16+ or MariaDB 10.3+.',
            ]);
        }

        // Inventory requires enforced CHECK constraints (MySQL 8.0.16);
        // Laravel supports MariaDB 10.3+.
        $minimum = $maria ? '10.3.0' : '8.0.16';

        if (version_compare($matches[1], $minimum, '<')) {
            $engine = $maria ? 'MariaDB' : 'MySQL';

            throw ValidationException::withMessages([
                'database' => "Detected {$engine} {$matches[1]}. "
                    ."Forkiva requires {$engine} {$minimum} or newer. "
                    .'Upgrade the database server or connect to a supported server, '
                    .'then retry.',
            ]);
        }
    }

    /**
     * Identify the verified database configuration without storing its credentials.
     *
     * @throws JsonException
     */
    private function fingerprint(): string
    {
        $connection = config(
            'database.connections.'.config('database.default')
        );

        return hash_hmac(
            'sha256',
            json_encode(
                array_map(
                    fn ($key) => (string) ($connection[$key] ?? ''),
                    [
                        'driver',
                        'url',
                        'host',
                        'port',
                        'database',
                        'username',
                        'password',
                        'unix_socket',
                    ]
                ),
                JSON_THROW_ON_ERROR
            ),
            config('app.key')
        );
    }

    /**
     * Build the migration and seeder plan in enabled module priority order.
     *
     * @return list<array{
     *     id: string,
     *     label: string,
     *     kind: string,
     *     status: string,
     *     path?: string,
     *     class?: class-string
     * }>
     */
    protected function tasks(): array
    {
        $tasks = [
            [
                'id' => 'host',
                'label' => 'Create application tables',
                'kind' => 'migration',
                'path' => database_path('migrations'),
            ],
        ];

        foreach (Module::getOrdered() as $module) {
            $path = $module->getPath().'/database/migrations';

            if (is_dir($path)) {
                $tasks[] = [
                    'id' => 'migrate:'.$module->getName(),
                    'label' => 'Prepare '.$module->getName(),
                    'kind' => 'migration',
                    'path' => $path,
                ];
            }
        }

        foreach (Module::getOrdered() as $module) {
            $classes = $module->get('migration.seeds')
                ?? [
                    'Modules\\'.$module->getName()
                    .'\\Database\\Seeders\\'
                    .$module->getName()
                    .'DatabaseSeeder',
                ];

            foreach ((array) $classes as $class) {
                if (class_exists($class)) {
                    $tasks[] = [
                        'id' => 'seed:'.$class,
                        'label' => 'Initialize '.$module->getName(),
                        'kind' => 'seed',
                        'class' => $class,
                    ];
                }
            }
        }

        return array_map(
            fn ($task) => $task + ['status' => 'pending'],
            $tasks
        );
    }

    /** {@inheritDoc} */
    public function prepareProcess(array $state): array
    {
        $this->requireDatabase($state);

        foreach ($state['tasks'] as $index => $task) {
            if ($task['status'] === 'complete') {
                continue;
            }

            $state['tasks'][$index]['status'] = 'running';
            $state['error'] = null;

            $this->tracker->save($state);

            return $state + ['processing' => true];
        }

        return $state;
    }

    /**
     * Reject processing until the current database matches
     * the verified configuration.
     *
     * @param  array<string, mixed>  $state
     *
     * @throws JsonException
     */
    private function requireDatabase(array $state): void
    {
        abort_unless(
            $state['database'],
            409,
            'Configure and verify the database first.'
        );

        abort_unless(
            hash_equals(
                $state['connection_fingerprint'] ?? '',
                $this->fingerprint()
            ),
            409,
            'Database configuration changed during setup. '
            .'Restore the verified configuration before continuing.'
        );
    }

    /** {@inheritDoc} */
    public function process(array $state): array
    {
        $this->requireDatabase($state);

        foreach ($state['tasks'] as $index => $task) {
            if ($task['status'] === 'complete') {
                continue;
            }

            // A lost HTTP response is recoverable:
            // seed receipts commit in the same transaction as their data.
            $state['tasks'][$index]['status'] = 'running';
            $state['error'] = null;

            $this->tracker->save($state);

            $previousTimeLimit = (int) ini_get('max_execution_time');
            try {
                if (function_exists('set_time_limit')) {
                    set_time_limit(120);
                }

                $this->runTask($task);

                $state['tasks'][$index]['status'] = 'complete';
            } catch (Throwable $exception) {
                $reference = InstallerFailure::record($exception, $task['id']);
                $state['tasks'][$index]['status'] = 'failed';
                $reason = $exception instanceof \PDOException
                    ? InstallerFailure::databaseMessage($exception)
                    : 'Ask your developer or hosting support to check the installation task in storage/logs before retrying.';
                $state['error'] = $task['label'].' failed. '.$reason.' Support reference: '.$reference.'. Completed seed tasks will not run again. Do not reset the database.';
            } finally {
                if (function_exists('set_time_limit')) {
                    set_time_limit($previousTimeLimit);
                }
            }

            $this->tracker->save($state);

            break;
        }

        return $state;
    }

    /**
     * Run a migration or atomically commit a seeder
     * with its completion receipt.
     *
     * @param  array{
     *     id: string,
     *     kind: string,
     *     path?: string,
     *     class?: class-string
     * }  $task
     *
     * @throws RuntimeException|Throwable
     */
    protected function runTask(array $task): void
    {
        if ($task['kind'] === 'migration') {
            $this->command(
                'migrate',
                [
                    '--path' => [$task['path']],
                    '--realpath' => true,
                    '--force' => true,
                ]
            );

            return;
        }

        DB::transaction(function () use ($task) {
            if (
                DB::table('installation_tasks')
                    ->where('name', $task['id'])
                    ->exists()
            ) {
                return;
            }

            // db:seed propagates exceptions;
            // module:seed can swallow them and return success.
            $this->command(
                'db:seed',
                [
                    '--class' => $task['class'],
                    '--force' => true,
                ]
            );

            $this->synchronizePostgresSequences();

            if ($task['class'] === SettingDatabaseSeeder::class) {
                $this->configuration->apply(
                    $this->tracker->read()['configuration']
                );
            }

            DB::table('installation_tasks')->insert([
                'name' => $task['id'],
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Run an installation command and reject unsuccessful exit codes.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws RuntimeException
     */
    protected function command(
        string $name,
        array $arguments = []
    ): void {
        if (Artisan::call($name, $arguments) !== 0) {
            throw new RuntimeException(
                'Installation command failed: '.$name
            );
        }
    }

    /**
     * Explicit IDs in installation seed data do not advance PostgreSQL sequences.
     * Keep future inserts above both the seeded IDs and existing sequence values.
     */
    private function synchronizePostgresSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE item record; maximum_id bigint; current_id bigint;
            BEGIN
                FOR item IN
                    SELECT table_schema, table_name, column_name,
                        pg_get_serial_sequence(format('%I.%I', table_schema, table_name), column_name) AS sequence_name
                    FROM information_schema.columns
                    WHERE table_schema = current_schema()
                      AND (column_default LIKE 'nextval(%' OR is_identity = 'YES')
                LOOP
                    IF item.sequence_name IS NOT NULL THEN
                        EXECUTE format('SELECT MAX(%I) FROM %I.%I', item.column_name, item.table_schema, item.table_name) INTO maximum_id;
                        IF maximum_id IS NOT NULL THEN
                            EXECUTE format('SELECT last_value FROM %s', item.sequence_name) INTO current_id;
                            PERFORM setval(item.sequence_name::regclass, GREATEST(maximum_id, current_id), true);
                        END IF;
                    END IF;
                END LOOP;
            END $$;
            SQL);
    }

    /** {@inheritDoc} */
    public function admin(array $state, array $data): array
    {
        $this->requireDatabase($state);
        $this->requireTasks($state);

        abort_if(
            $state['admin'],
            409,
            'The administrator has already been saved. Continue to launch.'
        );

        Validator::make(
            $data,
            [
                'username' => [
                    Rule::unique('users', 'username')->ignore(1),
                ],
                'email' => [
                    Rule::unique('users', 'email')->ignore(1),
                ],
            ]
        )->validate();

        DB::transaction(function () use ($data) {
            $admin = User::withTrashed()->find(1) ?? new User;

            $admin->forceFill([
                'id' => 1,
                'deleted_at' => null,
            ]);

            $admin->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'gender' => GenderType::Male,
                'is_active' => true,
            ])->save();

            $admin->assignRole(
                DefaultRole::SuperAdmin->value
            );
            $this->synchronizePostgresSequences();
        });

        $state['admin'] = true;

        $this->tracker->save($state);

        return $state;
    }

    /**
     * Reject later steps while any preparation task is incomplete.
     *
     * @param  array<string, mixed>  $state
     */
    private function requireTasks(array $state): void
    {
        abort_unless(
            $state['tasks'] !== []
            && collect($state['tasks'])->every(
                fn ($task) => $task['status'] === 'complete'
            ),
            409,
            'Complete database preparation before continuing.'
        );
    }

    /** {@inheritDoc} */
    public function finish(array $state, array $data): array
    {
        $this->requireDatabase($state);
        $this->requireTasks($state);

        abort_unless(
            $state['admin'],
            409,
            'Create the administrator before launching.'
        );

        $admin = User::query()->find(1);

        abort_unless(
            $admin
            && $admin->is_active
            && $admin->hasRole(DefaultRole::SuperAdmin->value),
            409,
            'Administrator verification failed.'
        );

        abort_unless(
            ! empty($state['configuration']),
            409,
            'Application configuration is missing.'
        );

        $url = rtrim($data['app_url'], '/');

        if ($url !== $state['configuration']['app_url']) {
            throw ValidationException::withMessages([
                'app_url' => 'Launch with the URL saved in application configuration.',
            ]);
        }

        $this->configuration->verifyCache(
            $state['configuration']['cache_store']
        );

        if ($state['configuration']['cache_store'] !== 'file') {
            $this->configuration->verifyCache('file');
        }

        $parts = parse_url($url);

        if (
            isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! empty($parts['path'])
        ) {
            throw ValidationException::withMessages([
                'app_url' => 'Enter the public origin only, '
                    .'for example https://pos.example.com.',
            ]);
        }

        $state = $this->check($state);

        abort_unless(
            $state['checks_passed'],
            409,
            'Server checks have changed. Resolve them before launching.'
        );

        $link = public_path('storage');
        $target = storage_path('app/public');

        if (is_link($link)) {
            abort_unless(
                realpath($link) === realpath($target),
                409,
                'public/storage links to the wrong location. '
                .'Correct it before launching.'
            );
        } elseif (file_exists($link)) {
            abort(
                409,
                'public/storage already exists and is not a symbolic link. '
                .'Resolve it before launching.'
            );
        } else {
            $this->command('storage:link');

            abort_unless(
                is_link($link)
                && realpath($link) === realpath($target),
                409,
                'The public storage link could not be verified.'
            );
        }

        $this->clearInstallationCaches($state['configuration']['cache_store']);

        $this->environment->write([
            'APP_URL' => $url,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_INSTALLED' => 'true',
        ]);

        // This marker also locks setup when a stale process
        // still has APP_INSTALLED=false in memory.
        File::replace(
            storage_path('framework/installed.json'),
            json_encode(
                [
                    'installed_at' => now()->toIso8601String(),
                ],
                JSON_THROW_ON_ERROR
            ),
            0600
        );

        $state['finished'] = true;

        $this->tracker->save($state);

        return $state;
    }

    /**
     * Clear the configured persistent store while setup still uses the array cache.
     */
    private function clearInstallationCaches(string $store): void
    {
        $this->command('cache:clear', ['store' => $store]);
        $this->command('optimize:clear', ['--except' => 'cache']);
    }
}
