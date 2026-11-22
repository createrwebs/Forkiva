<?php

namespace Modules\Tool\Services\Database\Drivers;

use RuntimeException;
use Symfony\Component\Process\Process;

class PostgresDatabaseDriver extends AbstractCommandDatabaseDriver
{
    /** {@inheritDoc} */
    public function extension(): string
    {
        return 'sql';
    }

    /** {@inheritDoc} */
    public function capabilities(array $config): array
    {
        $dump = $this->dumpBinary();
        $restore = $this->restoreBinary();
        $missing = [];

        if ($dump === null) {
            $missing[] = 'pg_dump';
        }
        if ($restore === null) {
            $missing[] = 'psql';
        }

        return [
            'backup_available' => $dump !== null,
            'restore_available' => $dump !== null && $restore !== null,
            'missing_tools' => $missing,
            'format' => 'sql',
        ];
    }

    /** {@inheritDoc} */
    public function backup(array $config, string $destination): string
    {
        $binary = $this->dumpBinary();
        if ($binary === null) {
            throw new RuntimeException(__('tool::database.messages.postgres_dump_required'));
        }

        $process = new Process([
            $binary,
            ...$this->connectionArguments($config),
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
            '--format=plain',
            '--file='.$destination,
        ]);
        $this->run($process, $config);

        return basename($binary);
    }

    /** {@inheritDoc} */
    public function restore(array $config, string $source): string
    {
        $this->validateSqlBackup($source);

        $binary = $this->restoreBinary();
        if ($binary === null) {
            throw new RuntimeException(__('tool::database.messages.postgres_restore_required'));
        }

        $stream = $this->openInput($source);

        try {
            $process = new Process([
                $binary,
                ...$this->connectionArguments($config),
                '--set=ON_ERROR_STOP=on',
                '--single-transaction',
            ]);
            $process->setInput($stream);
            $this->run($process, $config);
        } finally {
            fclose($stream);
        }

        return basename($binary);
    }

    /**
     * Build PostgreSQL connection arguments.
     *
     * @return array<int, string>
     */
    private function connectionArguments(array $config): array
    {
        return [
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '5432'),
            '--username='.(string) ($config['username'] ?? ''),
            '--dbname='.(string) $config['database'],
        ];
    }

    /**
     * Reject empty, SQLite, and clearly MySQL backup files.
     */
    private function validateSqlBackup(string $source): void
    {
        $sample = file_get_contents($source, false, null, 0, 8192);
        if ($sample === false || trim($sample) === '') {
            throw new RuntimeException(__('tool::database.messages.invalid_backup_content'));
        }

        if (str_starts_with($sample, "SQLite format 3\0") || str_contains($sample, 'MySQL dump')) {
            throw new RuntimeException(__('tool::database.messages.backup_driver_mismatch'));
        }
    }

    private function dumpBinary(): ?string
    {
        return $this->findBinary([
            config('tool.database.dump_binary'),
            'pg_dump',
        ]);
    }

    private function restoreBinary(): ?string
    {
        return $this->findBinary([
            config('tool.database.restore_binary'),
            'psql',
        ]);
    }
}
