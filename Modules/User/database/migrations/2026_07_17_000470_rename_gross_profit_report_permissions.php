<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'admin.reports.cost_and_revenue_by_order' => 'admin.reports.cost_and_gross_profit_by_order',
        'admin.reports.cost_and_revenue_by_product' => 'admin.reports.cost_and_gross_profit_by_product',
    ];

    public function up(): void
    {
        $this->renamePermissions(self::PERMISSIONS);
    }

    public function down(): void
    {
        $this->renamePermissions(array_flip(self::PERMISSIONS));
    }

    private function renamePermissions(array $permissions): void
    {
        $tableNames = config('permission.table_names');
        $permissionTable = $tableNames['permissions'] ?? 'permissions';

        if (! Schema::hasTable($permissionTable)) {
            return;
        }

        DB::transaction(function () use ($permissions, $tableNames, $permissionTable): void {
            foreach ($permissions as $oldName => $newName) {
                $oldPermissions = DB::table($permissionTable)
                    ->where('name', $oldName)
                    ->get();

                foreach ($oldPermissions as $oldPermission) {
                    $newPermission = DB::table($permissionTable)
                        ->where('name', $newName)
                        ->where('guard_name', $oldPermission->guard_name)
                        ->first();

                    if ($newPermission === null) {
                        DB::table($permissionTable)
                            ->where('id', $oldPermission->id)
                            ->update([
                                'name' => $newName,
                                'updated_at' => now(),
                            ]);

                        continue;
                    }

                    $this->mergePermissionAssignments(
                        $tableNames,
                        (int) $oldPermission->id,
                        (int) $newPermission->id,
                    );

                    DB::table($permissionTable)
                        ->where('id', $oldPermission->id)
                        ->delete();
                }
            }
        });

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    private function mergePermissionAssignments(array $tableNames, int $oldPermissionId, int $newPermissionId): void
    {
        foreach (['role_has_permissions', 'model_has_permissions'] as $tableKey) {
            $table = $tableNames[$tableKey] ?? $tableKey;

            if (! Schema::hasTable($table)) {
                continue;
            }

            $assignments = DB::table($table)
                ->where('permission_id', $oldPermissionId)
                ->get();

            foreach ($assignments as $assignment) {
                $values = (array) $assignment;
                $values['permission_id'] = $newPermissionId;
                DB::table($table)->insertOrIgnore($values);
            }
        }
    }
};
