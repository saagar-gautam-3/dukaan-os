<?php

declare(strict_types=1);

namespace App\Actions\Shops;

use App\Models\ShopUser;
use App\Models\User;

final class QueryActiveMemberships
{
    public function forLogin(User $user): mixed
    {
        return $user->activeShopUsers()
            ->whereHas('shop', fn ($query) => $query->where('is_active', true))
            ->get();
    }

    public function forUser(User $user): mixed
    {
        return $user->activeShopUsers()
            ->whereHas('shop', fn ($query) => $query->where('is_active', true))
            ->with(['shop', 'role'])->get();
    }

    public function find(User $user, int $shopId): ?ShopUser
    {
        return ShopUser::where('shop_id', $shopId)
            ->where('user_id', $user->id)->where('status', 'active')->first();
    }
}
