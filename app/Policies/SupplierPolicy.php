<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->checkTenantAndPermission($user, $supplier, 'suppliers.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'suppliers.manage');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->checkTenantAndPermission($user, $supplier, 'suppliers.manage');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->checkTenantAndPermission($user, $supplier, 'suppliers.manage');
    }
}
