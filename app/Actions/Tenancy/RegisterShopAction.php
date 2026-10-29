<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterShopAction
{
    public function __construct(
        protected RbacService $rbacService
    ) {}

    /**
     * Execute shop and owner registration in a single database transaction.
     *
     * @param  array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     password: string,
     *     shop_name: string,
     *     business_type?: string,
     *     timezone?: string
     * }  $data
     * @return array{user: User, shop: Shop, membership: ShopUser, roles: array<string, Role>}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'] ?? 'ne',
                'password' => Hash::make($data['password']),
            ]);

            $baseSlug = Str::slug($data['shop_name']);
            $slug = $baseSlug ?: 'shop';
            $counter = 1;
            while (Shop::withoutGlobalScopes()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $shop = Shop::create([
                'name' => $data['shop_name'],
                'slug' => $slug,
                'business_type' => $data['business_type'] ?? 'kirana',
                'timezone' => $data['timezone'] ?? 'Asia/Kathmandu',
                'settings' => [
                    'currency' => 'NPR',
                    'pan_no' => null,
                ],
                'is_active' => true,
            ]);

            // Create system permissions and default roles for this shop
            $roles = $this->rbacService->createDefaultRolesForShop($shop);
            $ownerRole = $roles['Shop Owner'];

            // Attach user as active Shop Owner
            $membership = ShopUser::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'role_id' => $ownerRole->id,
                'status' => 'active',
            ]);

            // Create default warehouse for the new shop
            $warehouse = new Warehouse;
            $warehouse->shop_id = $shop->id;
            $warehouse->name = 'Main Godown';
            $warehouse->code = 'MAIN';
            $warehouse->is_default = true;
            $warehouse->is_active = true;
            $warehouse->save();

            return [
                'user' => $user,
                'shop' => $shop,
                'membership' => $membership,
                'roles' => $roles,
                'warehouse' => $warehouse,
            ];
        });
    }
}
