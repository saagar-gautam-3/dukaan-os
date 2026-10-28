<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;
use App\Tenancy\TenantContext;

class ShopPolicy extends TenantPolicy
{
    /**
     * Determine whether the user can manage the shop settings.
     */
    public function manage(User $user, Shop $shop): bool
    {
        if (! TenantContext::has() || (int) $shop->id !== (int) TenantContext::getShopId()) {
            return false;
        }

        return $this->checkPermission($user, 'shops.manage');
    }
}
