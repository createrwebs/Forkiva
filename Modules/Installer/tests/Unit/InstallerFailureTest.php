<?php

namespace Modules\Installer\Tests\Unit;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Modules\Installer\Support\InstallerFailure;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstallerFailureTest extends TestCase
{
    /**
     * Explain hosting failures from driver codes without returning sensitive exception messages.
     */
    #[DataProvider('failures')]
    public function test_database_errors_have_specific_safe_instructions(int $code, string $state, string $expected): void
    {
        $exception = new PDOException('sensitive-password SELECT secret FROM users');
        $exception->errorInfo = [$state, $code, 'sensitive-password'];
        $message = InstallerFailure::databaseMessage($exception);
        $this->assertStringContainsString($expected, $message);
        $this->assertStringNotContainsString('sensitive-password', $message);
        $this->assertStringNotContainsString('SELECT', $message);
    }

    /** @return array<string, array{int, string, string}> */
    public static function failures(): array
    {
        return [
            'login' => [1045, '28000', 'rejected the login'],
            'missing database' => [1049, '42000', 'does not exist'],
            'permissions' => [1142, '42000', 'does not have enough permissions'],
            'foreign key' => [1215, 'HY000', 'foreign key error 1215'],
            'missing parent' => [1824, 'HY000', 'referenced table'],
            'connection' => [2002, 'HY000', 'cannot reach'],
            'partial migration' => [1050, '42S01', 'partially completed migration'],
            'unsupported SQL' => [1064, '42000', 'compatibility fix'],
            'unknown' => [9999, 'HY000', 'support reference'],
            'PostgreSQL login' => [0, '28P01', 'rejected the login'],
            'PostgreSQL permissions' => [0, '42501', 'does not have enough permissions'],
            'PostgreSQL database' => [0, '3D000', 'does not exist'],
        ];
    }

    public function test_support_reference_does_not_log_credentials_or_sql(): void
    {
        Log::shouldReceive('error')->once()->with('Installer failure', \Mockery::on(function (array $context): bool {
            $this->assertArrayHasKey('reference', $context);
            $this->assertStringNotContainsString('private-password', json_encode($context));

            return true;
        }));
        $reference = InstallerFailure::record(new PDOException('private-password'), 'database verification');
        $this->assertMatchesRegularExpression('/^[A-F0-9]{12}$/', $reference);
    }

    public function test_foreign_key_log_identifies_relationship_without_sql_or_bindings(): void
    {
        $exception = new QueryException('mysql', 'alter table `delivery_driver_assignments` add constraint `dda_order_fk` foreign key (`order_delivery_id`) references `order_deliveries` (`id`) on delete cascade', ['private-password'], new PDOException('secret driver message'));
        Log::shouldReceive('error')->once()->with('Installer failure', \Mockery::on(function (array $context): bool {
            $this->assertSame('delivery_driver_assignments', $context['table']);
            $this->assertSame('order_deliveries', $context['referenced_table']);
            $this->assertSame('cascade', $context['on_delete']);
            $this->assertArrayNotHasKey('sql', $context);
            $this->assertStringNotContainsString('private-password', json_encode($context));
            $this->assertStringNotContainsString('secret driver message', json_encode($context));

            return true;
        }));
        InstallerFailure::record($exception, 'migrate:Delivery');
    }
}
