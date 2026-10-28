<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\AdjustmentStatus;
use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Tenancy\Exceptions\MissingTenantContextException;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_tenant_isolation_on_all_step_02_models(): void
    {
        // 1. Setup Shop A and Shop B
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        // Populate Shop A
        TenantContext::setShop($shopA);
        $categoryA = Category::factory()->create(['shop_id' => $shopA->id]);
        $warehouseA = Warehouse::factory()->create(['shop_id' => $shopA->id]);
        $supplierA = Supplier::factory()->create(['shop_id' => $shopA->id]);
        $productA = Product::factory()->create([
            'shop_id' => $shopA->id,
            'category_id' => $categoryA->id,
        ]);
        $variantA = ProductVariant::factory()->create([
            'shop_id' => $shopA->id,
            'product_id' => $productA->id,
        ]);
        $balanceA = StockBalance::factory()->create([
            'shop_id' => $shopA->id,
            'warehouse_id' => $warehouseA->id,
            'product_id' => $productA->id,
        ]);
        $movementA = StockMovement::factory()->create([
            'shop_id' => $shopA->id,
            'warehouse_id' => $warehouseA->id,
            'product_id' => $productA->id,
            'movement_type' => MovementType::Opening,
        ]);
        $adjustmentA = StockAdjustment::factory()->create([
            'shop_id' => $shopA->id,
            'warehouse_id' => $warehouseA->id,
            'status' => AdjustmentStatus::Draft,
        ]);
        $adjustmentItemA = StockAdjustmentItem::factory()->create([
            'shop_id' => $shopA->id,
            'adjustment_id' => $adjustmentA->id,
            'product_id' => $productA->id,
        ]);
        $purchaseA = Purchase::factory()->create([
            'shop_id' => $shopA->id,
            'warehouse_id' => $warehouseA->id,
            'supplier_id' => $supplierA->id,
            'status' => PurchaseStatus::Draft,
        ]);
        $purchaseItemA = PurchaseItem::factory()->create([
            'shop_id' => $shopA->id,
            'purchase_id' => $purchaseA->id,
            'product_id' => $productA->id,
        ]);

        // Populate Shop B
        TenantContext::setShop($shopB);
        $categoryB = Category::factory()->create(['shop_id' => $shopB->id]);
        $warehouseB = Warehouse::factory()->create(['shop_id' => $shopB->id]);
        $supplierB = Supplier::factory()->create(['shop_id' => $shopB->id]);
        $productB = Product::factory()->create([
            'shop_id' => $shopB->id,
            'category_id' => $categoryB->id,
        ]);
        $variantB = ProductVariant::factory()->create([
            'shop_id' => $shopB->id,
            'product_id' => $productB->id,
        ]);
        $balanceB = StockBalance::factory()->create([
            'shop_id' => $shopB->id,
            'warehouse_id' => $warehouseB->id,
            'product_id' => $productB->id,
        ]);
        $movementB = StockMovement::factory()->create([
            'shop_id' => $shopB->id,
            'warehouse_id' => $warehouseB->id,
            'product_id' => $productB->id,
            'movement_type' => MovementType::Opening,
        ]);
        $adjustmentB = StockAdjustment::factory()->create([
            'shop_id' => $shopB->id,
            'warehouse_id' => $warehouseB->id,
            'status' => AdjustmentStatus::Draft,
        ]);
        $adjustmentItemB = StockAdjustmentItem::factory()->create([
            'shop_id' => $shopB->id,
            'adjustment_id' => $adjustmentB->id,
            'product_id' => $productB->id,
        ]);
        $purchaseB = Purchase::factory()->create([
            'shop_id' => $shopB->id,
            'warehouse_id' => $warehouseB->id,
            'supplier_id' => $supplierB->id,
            'status' => PurchaseStatus::Draft,
        ]);
        $purchaseItemB = PurchaseItem::factory()->create([
            'shop_id' => $shopB->id,
            'purchase_id' => $purchaseB->id,
            'product_id' => $productB->id,
        ]);

        // 2. Query as Shop A: must see only Shop A records
        TenantContext::setShop($shopA);

        $this->assertEquals(1, Category::count());
        $this->assertEquals($categoryA->id, Category::first()->id);
        $this->assertNull(Category::find($categoryB->id));

        $this->assertEquals(1, Warehouse::count());
        $this->assertEquals($warehouseA->id, Warehouse::first()->id);
        $this->assertNull(Warehouse::find($warehouseB->id));

        $this->assertEquals(1, Supplier::count());
        $this->assertEquals($supplierA->id, Supplier::first()->id);
        $this->assertNull(Supplier::find($supplierB->id));

        $this->assertEquals(1, Product::count());
        $this->assertEquals($productA->id, Product::first()->id);
        $this->assertNull(Product::find($productB->id));

        $this->assertEquals(1, ProductVariant::count());
        $this->assertEquals($variantA->id, ProductVariant::first()->id);
        $this->assertNull(ProductVariant::find($variantB->id));

        $this->assertEquals(1, StockBalance::count());
        $this->assertEquals($balanceA->id, StockBalance::first()->id);
        $this->assertNull(StockBalance::find($balanceB->id));

        $this->assertEquals(1, StockMovement::count());
        $this->assertEquals($movementA->id, StockMovement::first()->id);
        $this->assertNull(StockMovement::find($movementB->id));

        $this->assertEquals(1, StockAdjustment::count());
        $this->assertEquals($adjustmentA->id, StockAdjustment::first()->id);
        $this->assertNull(StockAdjustment::find($adjustmentB->id));

        $this->assertEquals(1, StockAdjustmentItem::count());
        $this->assertEquals($adjustmentItemA->id, StockAdjustmentItem::first()->id);
        $this->assertNull(StockAdjustmentItem::find($adjustmentItemB->id));

        $this->assertEquals(1, Purchase::count());
        $this->assertEquals($purchaseA->id, Purchase::first()->id);
        $this->assertNull(Purchase::find($purchaseB->id));

        $this->assertEquals(1, PurchaseItem::count());
        $this->assertEquals($purchaseItemA->id, PurchaseItem::first()->id);
        $this->assertNull(PurchaseItem::find($purchaseItemB->id));
    }

    public function test_composite_foreign_key_blocks_cross_tenant_child_association(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        // Create warehouse and product in Shop B
        TenantContext::setShop($shopB);
        $warehouseB = Warehouse::factory()->create(['shop_id' => $shopB->id]);
        $productB = Product::factory()->create(['shop_id' => $shopB->id]);

        // Attempting to create a StockBalance for Shop A pointing to Shop B's warehouse
        // should fail at DB constraint level because (shop_id, warehouse_id) must exist in warehouses
        $this->expectException(QueryException::class);

        TenantContext::setShop($shopA);

        DB::table('stock_balances')->insert([
            'shop_id' => $shopA->id,
            'warehouse_id' => $warehouseB->id,
            'product_id' => $productB->id,
            'quantity' => 10.000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_creating_tenant_record_without_active_context_throws_exception(): void
    {
        TenantContext::reset();

        $this->expectException(MissingTenantContextException::class);

        Product::create([
            'name' => 'Orphan Product',
            'sku' => 'ORPHAN-001',
            'unit_id' => 1,
            'cost_price' => '10.00',
            'selling_price' => '15.00',
        ]);
    }
}
