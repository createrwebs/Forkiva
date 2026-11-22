<?php

namespace Modules\Tool\Services\Database\Drivers;

use Modules\Tool\Services\Database\DatabaseBinaryLocatorInterface;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class AbstractCommandDatabaseDriver implements DatabaseDriverInterface
{
    public function __construct(private readonly DatabaseBinaryLocatorInterface $binaries) {}

    /**
     * Find the first configured or system database executable.
     *
     * @param  array<int, string|null>  $candidates
     */
    protected function findBinary(array $candidates): ?string
    {
        return $this->binaries->find($candidates);
    }

    /**
     * Run a database command without exposing credentials in its arguments.
     */
    protected function run(Process $process, array $config): void
    {
        $environment = [];
        $driver = (string) ($config['driver'] ?? '');

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $environment['MYSQL_PWD'] = (string) ($config['password'] ?? '');
        } elseif ($driver === 'pgsql') {
            $environment['PGPASSWORD'] = (string) ($config['password'] ?? '');
            $environment['PGSSLMODE'] = (string) ($config['sslmode'] ?? 'prefer');
            foreach (['sslrootcert' => 'PGSSLROOTCERT', 'sslcert' => 'PGSSLCERT', 'sslkey' => 'PGSSLKEY'] as $option => $variable) {
                if (! empty($config[$option])) {
                    $environment[$variable] = (string) $config[$option];
                }
            }
        }

        $process->setEnv($environment);
        $process->setTimeout((float) config('tool.database.process_timeout_seconds', 1800));

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $error = trim($exception->getProcess()->getErrorOutput());

            throw new RuntimeException($error !== '' ? $error : __('tool::database.messages.operation_failed'));
        }
    }

    /**
     * Open a backup as a process input stream.
     *
     * @return resource
     */
    protected function openInput(string $source)
    {
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            throw new RuntimeException(__('tool::database.messages.backup_not_readable'));
        }

        return $stream;
    }
}
