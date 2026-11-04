<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'staff.manage'],
            [
                'guard_name' => 'web',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );

        $permissionId = DB::table('permissions')->where('name', 'staff.manage')->value('id');
        $roleIds = DB::table('roles')
            ->whereNotNull('shop_id')
            ->where('name', 'Shop Owner')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'staff.manage')->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
