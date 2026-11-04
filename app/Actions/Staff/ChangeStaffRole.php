<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\Role;
use App\Models\ShopUser;
use App\Support\AuditTrail;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeStaffRole
{
    public function execute(ShopUser $membership, int $roleId, int $actorId): ShopUser
    {
        $shop = TenantContext::currentShop();

        return DB::transaction(function () use ($membership, $roleId, $actorId, $shop): ShopUser {
            $this->assertManageableMember($membership, $actorId, (int) $shop->id);

            $role = Role::query()
                ->whereKey($roleId)
                ->where('shop_id', $shop->id)
                ->where('name', '!=', 'Shop Owner')
                ->first();

            if ($role === null) {
                throw ValidationException::withMessages([
                    'role_id' => __('staff.invalid_role'),
                ]);
            }

            $oldRoleId = (int) $membership->role_id;
            $membership->role_id = $role->id;
            $membership->save();

            AuditTrail::record($membership, $actorId, 'staff_role_changed', [
                'role_id' => $oldRoleId,
            ], [
                'role_id' => (int) $role->id,
            ]);

            return $membership->load(['user', 'role']);
        });
    }

    private function assertManageableMember(ShopUser $membership, int $actorId, int $shopId): void
    {
        if ((int) $membership->shop_id !== $shopId) {
            abort(404);
        }

        if ((int) $membership->user_id === $actorId || $membership->role?->name === 'Shop Owner') {
            throw new AuthorizationException;
        }
    }
}
