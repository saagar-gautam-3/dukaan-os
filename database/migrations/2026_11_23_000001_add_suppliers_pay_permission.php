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
            ['name' => 'suppliers.pay'],
            [
                'guard_name' => 'web',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );

        $permissionId = DB::table('permissions')->where('name', 'suppliers.pay')->value('id');

        $roleNames = ['Shop Owner', 'Manager', 'Accountant'];

        $roleIds = DB::table('roles')
            ->whereNotNull('shop_id')
            ->whereIn('name', $roleNames)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }

        // Bump permissions version for affected users so sessions refresh.
        $userIds = DB::table('shop_user')
            ->whereIn('shop_id', function ($q) {
                $q->select('shop_id')->from('roles')->whereNotNull('shop_id')->whereIn('name', ['Shop Owner', 'Manager', 'Accountant']);
            })
            ->pluck('user_id');

        DB::table('users')->whereIn('id', $userIds)->increment('permissions_version');
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'suppliers.pay')->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
