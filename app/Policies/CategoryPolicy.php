<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'products.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'products.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $this->checkTenantAndPermission($user, $category, 'products.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->checkTenantAndPermission($user, $category, 'products.manage');
    }
}
