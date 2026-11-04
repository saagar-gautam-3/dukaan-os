<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShopUser;
use App\Models\User;

class ShopUserPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'staff.manage');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'staff.manage');
    }

    public function view(User $user, ShopUser $shopUser): bool
    {
        return $this->checkTenantAndPermission($user, $shopUser, 'staff.manage');
    }

    public function update(User $user, ShopUser $shopUser): bool
    {
        return $this->canManageMember($user, $shopUser);
    }

    public function delete(User $user, ShopUser $shopUser): bool
    {
        return $this->canManageMember($user, $shopUser);
    }

    private function canManageMember(User $user, ShopUser $shopUser): bool
    {
        if (! $this->checkTenantAndPermission($user, $shopUser, 'staff.manage')) {
            return false;
        }

        if ((int) $shopUser->user_id === (int) $user->id) {
            return false;
        }

        return $shopUser->role?->name !== 'Shop Owner';
    }
}
