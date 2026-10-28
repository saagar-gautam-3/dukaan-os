<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'purchases.view');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $this->checkTenantAndPermission($user, $purchase, 'purchases.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'purchases.manage');
    }

    public function update(User $user, Purchase $purchase): bool
    {
        if ($purchase->isPosted()) {
            return false;
        }

        return $this->checkTenantAndPermission($user, $purchase, 'purchases.manage');
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        if ($purchase->isPosted()) {
            return false;
        }

        return $this->checkTenantAndPermission($user, $purchase, 'purchases.manage');
    }

    public function post(User $user, Purchase $purchase): bool
    {
        if ($purchase->isPosted()) {
            return false;
        }

        return $this->checkTenantAndPermission($user, $purchase, 'purchases.manage');
    }
}
