<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class TenantPolicy
{
    use HandlesAuthorization;

    /**
     * Check if the user has permission and the record belongs to the active tenant.
     */
    protected function checkTenantAndPermission(User $user, mixed $model, string $permission): bool
    {
        return $this->checkTenantOwnership($model) && $this->checkPermission($user, $permission);
    }

    /**
     * Verify that the model belongs to the current tenant context.
     */
    protected function checkTenantOwnership(mixed $model): bool
    {
        if (! TenantContext::has()) {
            return false;
        }

        if (! isset($model->shop_id)) {
            return false;
        }

        return (int) $model->shop_id === (int) TenantContext::getShopId();
    }

    /**
     * Verify that the user has the given permission in the current shop.
     */
    protected function checkPermission(User $user, string $permission): bool
    {
        return $user->hasPermissionInCurrentShop($permission);
    }
}
