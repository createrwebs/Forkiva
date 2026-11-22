<?php

namespace Modules\Core\Tests\Unit;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Modules\Core\Database\MySqlSchemaGrammar;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MySqlSchemaGrammarTest extends TestCase
{
    /** Only the legacy MySQL definitions that conflict with cascading FKs become virtual. */
    #[DataProvider('definitions')]
    public function test_generated_columns_are_changed_only_for_known_mysql_definitions(string $table, string $column, string $expression, bool $maria, bool $virtual): void
    {
        $pdo = Mockery::mock(\PDO::class);
        $pdo->shouldReceive('getAttribute')->with(\PDO::ATTR_SERVER_VERSION)->andReturn($maria ? '10.4.28-MariaDB' : '8.0.46');
        $connection = new MySqlConnection($pdo, 'grammar_test', '', ['driver' => 'mysql']);
        $connection->setSchemaGrammar(new MySqlSchemaGrammar($connection));
        $blueprint = new Blueprint($connection, $table);
        $blueprint->create();
        $blueprint->unsignedBigInteger($column)->nullable()->storedAs($expression);
        $sql = implode(';', $blueprint->toSql());
        $this->assertStringContainsString($expression, $sql);
        if ($virtual) {
            $this->assertStringNotContainsString(' stored', $sql);
        } else {
            $this->assertStringContainsString(' stored', $sql);
        }
    }

    /** @return array<string, array{string, string, string, bool, bool}> */
    public static function definitions(): array
    {
        $expression = 'case when `is_current` = 1 then `order_delivery_id` else null end';

        return [
            'mysql assignment' => ['delivery_driver_assignments', 'current_order_delivery_id', $expression, false, true],
            'mysql driver' => ['delivery_driver_assignments', 'current_driver_id', 'case when `is_current` = 1 then `driver_id` else null end', false, true],
            'mysql earnings' => ['delivery_driver_earning_entries', 'per_delivery_unique_key', 'case when `type` = "per_delivery" and `order_delivery_id` is not null then concat(`order_delivery_id`, "-per_delivery") else null end', false, true],
            'mysql provider settings' => ['branch_delivery_provider_settings', 'active_branch_provider_key', "case when deleted_at is null then concat(branch_id, ':', provider_id) else null end", false, true],
            'mariadb unchanged' => ['delivery_driver_assignments', 'current_order_delivery_id', $expression, true, false],
            'unrelated table unchanged' => ['other_assignments', 'current_order_delivery_id', $expression, false, false],
            'unrelated column unchanged' => ['delivery_driver_assignments', 'other_column', $expression, false, false],
            'new expression unchanged' => ['delivery_driver_assignments', 'current_order_delivery_id', 'order_delivery_id + 1', false, false],
        ];
    }
}
