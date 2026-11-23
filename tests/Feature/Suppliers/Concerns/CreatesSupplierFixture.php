<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers\Concerns;

use App\Enums\LedgerEntryType;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;

trait CreatesSupplierFixture
{
    /**
     * @param  list<string>  $roleNames
     * @return array{0: Shop, 1: array<string, Role>, 2: array<string, User>}
     */
    protected function createShopWithRoles(array $roleNames = ['Shop Owner']): array
    {
        $shop = Shop::factory()->create(['timezone' => 'Asia/Kathmandu']);
        TenantContext::setShop($shop);

        $allRoles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $roles = [];
        $users = [];

        foreach ($roleNames as $roleName) {
            $roles[$roleName] = $allRoles[$roleName];
            $user = User::factory()->create();
            ShopUser::factory()->create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'role_id' => $allRoles[$roleName]->id,
                'status' => 'active',
            ]);
            $users[$roleName] = $user;
        }

        return [$shop, $roles, $users];
    }

    protected function actingAsShopMember(User $user, Shop $shop): void
    {
        $this->actingAs($user)->withSession(['current_shop_id' => $shop->id]);
    }

    protected function addPurchaseDebit(Supplier $supplier, string $amount, ?string $createdAt = null): SupplierLedgerEntry
    {
        return SupplierLedgerEntry::factory()->create([
            'shop_id' => $supplier->shop_id,
            'supplier_id' => $supplier->id,
            'entry_type' => LedgerEntryType::PurchaseDebit->value,
            'debit' => $amount,
            'credit' => '0.00',
            'created_at' => $createdAt,
        ]);
    }

    protected function tearDownCreatesSupplierFixture(): void
    {
        TenantContext::reset();
    }
}
