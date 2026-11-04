<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\ShopUser;
use App\Support\AuditTrail;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RemoveStaffMember
{
    public function execute(ShopUser $membership, int $actorId): void
    {
        $shopId = (int) TenantContext::currentShop()->id;

        DB::transaction(function () use ($membership, $actorId, $shopId): void {
            if ((int) $membership->shop_id !== $shopId) {
                abort(404);
            }

            if ((int) $membership->user_id === $actorId || $membership->role?->name === 'Shop Owner') {
                throw new AuthorizationException;
            }

            AuditTrail::record($membership, $actorId, 'staff_removed', [
                'user_id' => (int) $membership->user_id,
                'role_id' => (int) $membership->role_id,
            ], null);

            $membership->delete();
        });
    }
}
