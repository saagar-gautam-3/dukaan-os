<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'sales.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->checkTenantAndPermission($user, $sale, 'sales.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'sales.create');
    }

    public function update(User $user, Sale $sale): bool
    {
        if ($sale->isPosted()) {
            return false; // posted sales are immutable
        }

        return $this->checkTenantAndPermission($user, $sale, 'sales.create');
    }

    public function delete(User $user, Sale $sale): bool
    {
        if ($sale->isPosted()) {
            return false;
        }

        return $this->checkTenantAndPermission($user, $sale, 'sales.create');
    }

    public function post(User $user, Sale $sale): bool
    {
        if ($sale->isPosted()) {
            return false;
        }

        return $this->checkTenantAndPermission($user, $sale, 'sales.create');
    }

    public function return(User $user, Sale $sale): bool
    {
        return $this->checkTenantAndPermission($user, $sale, 'sales.return');
    }
}
