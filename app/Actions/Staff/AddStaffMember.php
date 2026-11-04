<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\Role;
use App\Models\ShopUser;
use App\Models\User;
use App\Support\AuditTrail;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AddStaffMember
{
    /**
     * @param  array{name: string, email: string, phone: string, role_id: int|string, password: string}  $data
     */
    public function execute(array $data, int $actorId): ShopUser
    {
        $shop = TenantContext::currentShop();

        $membership = DB::transaction(function () use ($data, $actorId, $shop): ShopUser {
            $role = Role::query()
                ->whereKey($data['role_id'])
                ->where('shop_id', $shop->id)
                ->where('name', '!=', 'Shop Owner')
                ->first();

            if ($role === null) {
                throw ValidationException::withMessages([
                    'role_id' => __('staff.invalid_role'),
                ]);
            }

            // User's generic Auditable event records phone/email fields. The explicit membership audit below
            // intentionally records only user/role IDs and avoids duplicating personal or password data.
            $user = User::withoutEvents(fn (): User => User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]));

            $membership = ShopUser::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'status' => 'active',
            ]);

            AuditTrail::record($membership, $actorId, 'staff_added', null, [
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);

            return $membership->load(['user', 'role']);
        });

        try {
            $membership->user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);
        }

        return $membership;
    }
}
