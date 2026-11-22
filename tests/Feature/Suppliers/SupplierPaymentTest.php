<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function setupShop(): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $user = User::factory()->create();
        \App\Models\ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        return [$shop, $user];
    }

    public function test_payment_reduces_balance(): void
    {
        [$shop, $user] = $this->setupShop();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        SupplierLedgerEntry::factory()->create([
            'shop_id' => $shop->id,
            'supplier_id' => $supplier->id,
            'entry_type' => \App\Enums\LedgerEntryType::PurchaseDebit->value,
            'debit' => '100.00',
            'credit' => '0.00',
        ]);

        $this->actingAs($user)->withSession(['current_shop_id' => $shop->id]);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '40.00',
            'payment_method' => 'cash',
            'idempotency_key' => 'key1',
        ])->assertRedirect(route('suppliers.dues'));

        $this->assertSame('60.00', $supplier->fresh()->ledgerBalance());
    }

    public function test_overpay_rejected(): void
    {
        [$shop, $user] = $this->setupShop();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        SupplierLedgerEntry::factory()->create([
            'shop_id' => $shop->id,
            'supplier_id' => $supplier->id,
            'entry_type' => \App\Enums\LedgerEntryType::PurchaseDebit->value,
            'debit' => '50.00',
            'credit' => '0.00',
        ]);

        $this->actingAs($user)->withSession(['current_shop_id' => $shop->id]);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '60.00',
            'payment_method' => 'cash',
        ])->assertSessionHas('error');
    }

    public function test_dues_page(): void
    {
        [$shop, $user] = $this->setupShop();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        SupplierLedgerEntry::factory()->create([
            'shop_id' => $shop->id,
            'supplier_id' => $supplier->id,
            'entry_type' => \App\Enums\LedgerEntryType::PurchaseDebit->value,
            'debit' => '50.00',
            'credit' => '0.00',
        ]);

        $this->actingAs($user)->withSession(['current_shop_id' => $shop->id]);
        $this->get(route('suppliers.dues'))->assertOk();
    }
}