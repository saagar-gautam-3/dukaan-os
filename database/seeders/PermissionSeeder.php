<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\RbacService;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(RbacService $rbacService): void
    {
        $rbacService->syncSystemPermissions();
    }
}
