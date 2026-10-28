<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->checkTenantAndPermission($user, $product, 'products.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'products.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->checkTenantAndPermission($user, $product, 'products.manage');
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->checkTenantAndPermission($user, $product, 'products.manage');
    }
}
