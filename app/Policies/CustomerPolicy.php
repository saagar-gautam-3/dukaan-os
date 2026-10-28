<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->checkTenantAndPermission($user, $customer, 'customers.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->checkTenantAndPermission($user, $customer, 'customers.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->checkTenantAndPermission($user, $customer, 'customers.manage');
    }
}
