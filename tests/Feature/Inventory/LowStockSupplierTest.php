<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LowStockSupplierTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_preferred_supplier_is_the_latest_posted_purchase(): void
    {
        [$shop, $user, $warehouse] = $this->setupShopAndUser('Inventory Staff');

        $unit = Unit::factory()->create(['is_fractional' => true]);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'unit_id' => $unit->id,
            'low_stock_threshold' => '5.000',
            'track_stock' => true,
        ]);

        StockBalance::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '2.000',
        ]);

        $oldSupplier = $this->supplier($shop, 'Old Supplier', '9800000099');
        $midSupplier = $this->supplier($shop, 'Mid Supplier', null);
        $newSupplier = $this->supplier($shop, 'New Supplier', '9860000000');

        $day = now('Asia/Kathmandu')->toDateString();
        $this->purchase($shop, $warehouse, $oldSupplier, $product, 'posted', $day.' 08:00:00');
        // Draft with a later timestamp must win nothing.
        $this->purchase($shop, $warehouse, $midSupplier, $product, 'draft', $day.' 09:00:00');
        $this->purchase($shop, $warehouse, $newSupplier, $product, 'posted', $day.' 10:00:00');

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('stock.alerts'));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('New Supplier', $html);
        $this->assertStringContainsString('9860000000', $html);
        $this->assertStringNotContainsString('Old Supplier 9800000099', $html);
        // Suggested qty 8.000 for the item.
        $this->assertStringContainsString('8.000', $html);
        // wa.me order link for New Supplier's phone.
        $this->assertStringContainsString('wa.me/9779860000000', $html);
    }

    public function test_item_without_supplier_and_product_above_level_are_handled(): void
    {
        [$shop, $user, $warehouse] = $this->setupShopAndUser('Inventory Staff');

        $unit = Unit::factory()->create(['is_fractional' => false]);
        $orphan = Product::factory()->create([
            'shop_id' => $shop->id,
            'unit_id' => $unit->id,
            'low_stock_threshold' => '5.000',
            'track_stock' => true,
            'name' => 'Orphan Product',
        ]);

        StockBalance::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $orphan->id,
            'quantity' => '1.000',
        ]);

        $above = Product::factory()->create([
            'shop_id' => $shop->id,
            'unit_id' => $unit->id,
            'low_stock_threshold' => '5.000',
            'track_stock' => true,
            'name' => 'Above Level Product',
        ]);

        StockBalance::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $above->id,
            'quantity' => '9.000',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('stock.alerts'));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Orphan Product', $html);
        $this->assertStringNotContainsString('Above Level Product', $html);
        $this->assertStringNotContainsString('wa.me/', $html);
    }

    public function test_supplier_with_invalid_phone_gets_no_order_link(): void
    {
        [$shop, $user, $warehouse] = $this->setupShopAndUser('Inventory Staff');

        $unit = Unit::factory()->create(['is_fractional' => true]);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'unit_id' => $unit->id,
            'low_stock_threshold' => '5.000',
            'track_stock' => true,
        ]);

        StockBalance::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
        ]);

        $supplier = $this->supplier($shop, 'Bad Phone Supplier', '123');
        $day = now('Asia/Kathmandu')->toDateString();
        $this->purchase($shop, $warehouse, $supplier, $product, 'posted', $day.' 10:00:00');

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('stock.alerts'));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Bad Phone Supplier', $html);
        $this->assertStringNotContainsString('wa.me/', $html);
    }

    private function setupShopAndUser(string $role): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);

        $user = User::factory()->create(['locale' => 'en']);
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles[$role]->id,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);

        return [$shop, $user, $warehouse];
    }

    private function supplier(Shop $shop, string $name, ?string $phone): Supplier
    {
        return TenantContext::runAs($shop, fn (): Supplier => Supplier::factory()->create([
            'shop_id' => $shop->id,
            'name' => $name,
            'phone' => $phone,
        ]));
    }

    private function purchase(Shop $shop, Warehouse $warehouse, Supplier $supplier, Product $product, string $status, string $postedAtLocal): Purchase
    {
        return TenantContext::runAs($shop, function () use ($shop, $warehouse, $supplier, $product, $status, $postedAtLocal): Purchase {
            $purchase = Purchase::factory()->create([
                'shop_id' => $shop->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'status' => $status === 'draft' ? PurchaseStatus::Draft : PurchaseStatus::Posted,
                'purchase_date' => Carbon::parse($postedAtLocal, 'Asia/Kathmandu')->toDateString(),
                'posted_at' => $status === 'posted' ? Carbon::parse($postedAtLocal, 'Asia/Kathmandu')->utc() : null,
            ]);

            PurchaseItem::factory()->create([
                'shop_id' => $shop->id,
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
            ]);

            return $purchase;
        });
    }
}
