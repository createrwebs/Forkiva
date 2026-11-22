<?php

namespace Modules\Tool\Services\Database\Drivers;

use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

class MySqlDatabaseDriver extends AbstractCommandDatabaseDriver
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
            $missing[] = 'mysqldump/mariadb-dump';
        }
        if ($restore === null) {
            $missing[] = 'mysql/mariadb';
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
            throw new RuntimeException(__('tool::database.messages.mysql_dump_required'));
        }

        $arguments = [
            $binary,
            ...$this->connectionArguments($config),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--events',
            '--triggers',
            '--hex-blob',
            '--add-drop-table',
            '--result-file='.$destination,
            (string) $config['database'],
        ];

        $this->run(new Process($arguments), $config);

        return basename($binary);
    }

    /** {@inheritDoc} */
    public function restore(array $config, string $source): string
    {
        $this->validateSqlBackup($source);

        $binary = $this->restoreBinary();
        if ($binary === null) {
            throw new RuntimeException(__('tool::database.messages.mysql_restore_required'));
        }

        $stream = $this->openInput($source);

        try {
            $process = new Process([
                $binary,
                ...$this->connectionArguments($config),
                '--binary-mode=1',
                (string) $config['database'],
            ]);
            $process->setInput($stream);
            $this->run($process, $config);
        } finally {
            fclose($stream);
        }

        return basename($binary);
    }

    /**
     * Build connection arguments shared by dump and restore clients.
     *
     * @return array<int, string>
     */
    private function connectionArguments(array $config): array
    {
        $arguments = ['--user='.(string) ($config['username'] ?? '')];
        $socket = (string) ($config['unix_socket'] ?? '');

        if ($socket !== '') {
            $arguments[] = '--socket='.$socket;
        } else {
            $arguments[] = '--host='.(string) ($config['host'] ?? '127.0.0.1');
            $arguments[] = '--port='.(string) ($config['port'] ?? '3306');
        }

        $sslCa = $config['options'][PDO::MYSQL_ATTR_SSL_CA] ?? null;
        if (is_string($sslCa) && $sslCa !== '') {
            $arguments[] = '--ssl-ca='.$sslCa;
        }

        return $arguments;
    }

    /**
     * Reject empty, SQLite, and clearly PostgreSQL backup files.
     */
    private function validateSqlBackup(string $source): void
    {
        $sample = file_get_contents($source, false, null, 0, 8192);
        if ($sample === false || trim($sample) === '') {
            throw new RuntimeException(__('tool::database.messages.invalid_backup_content'));
        }

        if (str_starts_with($sample, "SQLite format 3\0") || str_contains($sample, 'PostgreSQL database dump')) {
            throw new RuntimeException(__('tool::database.messages.backup_driver_mismatch'));
        }
    }

    private function dumpBinary(): ?string
    {
        return $this->findBinary([
            config('tool.database.dump_binary'),
            'mysqldump',
            'mariadb-dump',
        ]);
    }

    private function restoreBinary(): ?string
    {
        return $this->findBinary([
            config('tool.database.restore_binary'),
            'mysql',
            'mariadb',
        ]);
    }
}
