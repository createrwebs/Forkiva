<?php

namespace Modules\Installer\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

class InstallerFailure
{
    /**
     * Translate database error codes into hosting instructions without exposing SQL or credentials.
     */
    public static function databaseMessage(Throwable $exception): string
    {
        $code = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
        $state = (string) ($exception instanceof PDOException ? ($exception->errorInfo[0] ?? $exception->getCode()) : $exception->getCode());
        if (in_array($code, [1045, 1698], true) || in_array($state, ['28P01', '28000'], true)) {
            return 'The database server rejected the login. In your hosting database panel, check the full database username (including any account prefix), reset its password if needed, and assign that user to the selected database. Enter those credentials and retry.';
        }
        if ($code === 1049 || $state === '3D000') {
            return 'The selected database does not exist. Create an empty database in your hosting database panel and enter its full name, including any account prefix. Assign the database user to it, then retry.';
        }
        if (in_array($code, [1044, 1142, 1143, 1227], true) || $state === '42501') {
            return 'The database user does not have enough permissions. Ask hosting support to grant this user the schema, table, index, trigger and data permissions required for installation on the selected database only. Then retry. Shared hosting may restrict trigger creation even when other permissions are enabled.';
        }
        if (in_array($code, [2002, 2003, 2005, 2006, 2013], true) || in_array($state, ['08001', '08004', '08006'], true)) {
            return 'The website cannot reach the database server or the connection was interrupted. Use the database host and port supplied by your hosting provider; do not copy local-computer settings unless the provider confirms them. Ask support to check database availability and network access, then retry.';
        }
        if (in_array($code, [1215, 1824, 1825, 1833, 3780], true)) {
            return 'A table relationship could not be created (foreign key error '.$code.'). This is a database schema compatibility problem. Ask your developer to check the migration, table and referenced table in the support log, including column types, indexes and generated-column delete rules. A failed MySQL migration may leave a partially created table; review and repair it before retrying. Do not disable foreign-key checks or reset the database.';
        }
        if ($code === 1050 || $state === '42P07') {
            return 'A table required by this installation already exists. Stop and ask your developer to review the partially completed migration before retrying. Do not delete existing tables or reset the database.';
        }
        if (in_array($code, [1064, 1235], true) || in_array($state, ['0A000', '42601'], true)) {
            return 'The database rejected an operation used by this release. Ask your developer to check this migration against the selected database engine and version. Retrying without a compatibility fix will not resolve it.';
        }

        return 'A database operation could not be completed. Ask your hosting support or developer to check the server logs using the support reference below before retrying. Do not delete tables or share database passwords.';
    }

    /**
     * Record a support reference with safe metadata; exception messages and arguments may contain secrets.
     */
    public static function record(Throwable $exception, string $operation): string
    {
        $reference = strtoupper(bin2hex(random_bytes(6)));
        $context = [
            'reference' => $reference,
            'operation' => $operation,
            'exception' => $exception::class,
            'code' => (string) $exception->getCode(),
            'driver_code' => $exception instanceof PDOException ? ($exception->errorInfo[1] ?? null) : null,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ] + self::schemaContext($exception);
        try {
            Log::error('Installer failure', $context);
        } catch (Throwable) {
            // A logs-folder permission failure must not hide the original client-facing error.
            error_log('Installer failure '.json_encode($context));
        }

        return $reference;
    }

    /**
     * Extract only migration locations and DDL identifiers; never log SQL, bindings or driver messages.
     *
     * @return array<string, string|int>
     */
    private static function schemaContext(Throwable $exception): array
    {
        $context = [];
        foreach ($exception->getTrace() as $frame) {
            if (preg_match('~(?:Modules/[A-Za-z0-9_]+/)?database/migrations/[A-Za-z0-9_]+\.php$~', $frame['file'] ?? '', $match)) {
                $context['migration'] = $match[0];
                $context['migration_line'] = $frame['line'] ?? 0;
                break;
            }
        }

        if ($exception instanceof QueryException && preg_match(
            '~^alter table `([A-Za-z0-9_]{1,64})` add constraint `([A-Za-z0-9_]{1,64})` foreign key \(`([A-Za-z0-9_]{1,64})`\) references `([A-Za-z0-9_]{1,64})` \(`([A-Za-z0-9_]{1,64})`\)(?: on delete (cascade|set null|restrict|no action))?(?: on update (cascade|set null|restrict|no action))?$~iD',
            $exception->getSql(),
            $match
        )) {
            foreach (['table', 'constraint', 'column', 'referenced_table', 'referenced_column', 'on_delete', 'on_update'] as $index => $key) {
                if (isset($match[$index + 1]) && $match[$index + 1] !== '') {
                    $context[$key] = $match[$index + 1];
                }
            }
        }

        return $context;
    }
}
