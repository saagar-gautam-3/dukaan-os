<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateShopForUser
{
    public function __construct(private readonly RbacService $rbacService) {}

    /**
     * @param  array{name: string, business_type?: string|null}  $data
     * @return array{shop: Shop, membership: ShopUser, roles: array<string, Role>}
     */
    public function execute(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug ?: 'shop';
            $counter = 1;
            while (Shop::withoutGlobalScopes()->where('slug', $slug)->exists()) {
                $slug = ($baseSlug ?: 'shop').'-'.$counter++;
            }

            $shop = Shop::create([
                'name' => $data['name'],
                'slug' => $slug,
                'business_type' => $data['business_type'] ?? 'kirana',
                'timezone' => 'Asia/Kathmandu',
                'settings' => ['currency' => 'NPR', 'pan_no' => null],
                'is_active' => true,
            ]);

            return TenantContext::runAs($shop, function () use ($shop, $user): array {
                $roles = $this->rbacService->createDefaultRolesForShop($shop);
                $membership = ShopUser::create([
                    'shop_id' => $shop->id,
                    'user_id' => $user->id,
                    'role_id' => $roles['Shop Owner']->id,
                    'status' => 'active',
                ]);

                $warehouse = new Warehouse;
                $warehouse->shop_id = $shop->id;
                $warehouse->name = 'Main Godown';
                $warehouse->code = 'MAIN';
                $warehouse->is_default = true;
                $warehouse->is_active = true;
                $warehouse->save();

                return compact('shop', 'membership', 'roles');
            });
        });
    }
}
