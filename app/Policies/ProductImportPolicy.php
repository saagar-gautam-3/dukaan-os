<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductImport;
use App\Models\User;

class ProductImportPolicy extends TenantPolicy
{
    public function view(User $user, ProductImport $import): bool
    {
        return $this->checkTenantOwnership($import)
            && $user->hasPermissionInCurrentShop('products.manage')
            && $user->hasPermissionInCurrentShop('inventory.manage');
    }

    public function update(User $user, ProductImport $import): bool
    {
        return $this->view($user, $import);
    }
}
