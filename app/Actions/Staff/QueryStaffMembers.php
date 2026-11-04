<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\Role;
use App\Models\ShopUser;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class QueryStaffMembers
{
    public function execute(int $perPage = 20): LengthAwarePaginator
    {
        return ShopUser::query()
            ->with(['user', 'role'])
            ->where('shop_id', TenantContext::currentShop()->id)
            ->latest('created_at')
            ->paginate($perPage);
    }

    /** @return Collection<int, Role> */
    public function availableRoles(): Collection
    {
        return Role::query()
            ->where('name', '!=', 'Shop Owner')
            ->orderBy('name')
            ->get();
    }
}
