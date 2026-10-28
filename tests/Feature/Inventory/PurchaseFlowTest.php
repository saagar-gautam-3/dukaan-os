<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\PostPurchase;
use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_draft_purchase_can_be_created_with_items(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $purchase = Purchase::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Draft,
            'subtotal' => '1000.00',
            'grand_total' => '1000.00',
        ]);

        $item = PurchaseItem::factory()->create([
            'shop_id' => $shop->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => '10.000',
            'unit_cost' => '100.00',
            'line_total' => '1000.00',
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'shop_id' => $shop->id,
            'status' => PurchaseStatus::Draft->value,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'id' => $item->id,
            'purchase_id' => $purchase->id,
            'quantity' => '10.000',
        ]);

        $this->assertFalse($purchase->isPosted());
    }

    public function test_posting_purchase_receives_stock_and_updates_weighted_average_cost(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);

        // Product initially has 10 units @ 100.00
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'cost_price' => '100.00',
        ]);

        StockBalance::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '10.000',
        ]);

        // Purchase receives 10 units @ 140.00
        // New moving weighted average cost should be: (10*100 + 10*140) / 20 = 2400 / 20 = 120.00
        $purchase = Purchase::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'purchase_no' => 'PO-2026-0001',
            'status' => PurchaseStatus::Draft,
            'subtotal' => '1400.00',
            'grand_total' => '1400.00',
        ]);

        PurchaseItem::factory()->create([
            'shop_id' => $shop->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => '10.000',
            'unit_cost' => '140.00',
            'line_total' => '1400.00',
        ]);

        $action = app(PostPurchase::class);
        $postedPurchase = $action->execute($purchase);

        $this->assertTrue($postedPurchase->isPosted());
        $this->assertNotNull($postedPurchase->posted_at);

        // Check stock balance is updated to 20.000
        $balance = StockBalance::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertNotNull($balance);
        $this->assertEquals('20.000', $balance->quantity);

        // Check product cost price is updated to 120.00
        $product->refresh();
        $this->assertEquals('120.00', $product->cost_price);

        // Check stock movement is recorded with proper reference
        $movement = StockMovement::where('product_id', $product->id)
            ->where('movement_type', MovementType::PurchaseReceive->value)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals('10.000', $movement->quantity);
        $this->assertEquals('140.00', $movement->unit_cost);
        $this->assertEquals(Purchase::class, $movement->reference_type);
        $this->assertEquals($purchase->id, $movement->reference_id);
    }

    public function test_posted_purchases_are_immutable(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $purchase = Purchase::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Draft,
        ]);

        PurchaseItem::factory()->create([
            'shop_id' => $shop->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => '5.000',
            'unit_cost' => '50.00',
            'line_total' => '250.00',
        ]);

        $action = app(PostPurchase::class);
        $action->execute($purchase);

        // Attempting to update a posted purchase must throw an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Posted purchases are immutable and cannot be modified.');

        $purchase->notes = 'Updated notes after post';
        $purchase->save();
    }

    public function test_posted_purchases_cannot_be_deleted(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $purchase = Purchase::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Draft,
        ]);

        PurchaseItem::factory()->create([
            'shop_id' => $shop->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => '5.000',
            'unit_cost' => '50.00',
            'line_total' => '250.00',
        ]);

        $action = app(PostPurchase::class);
        $action->execute($purchase);

        // Attempting to delete a posted purchase must throw an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Posted purchases cannot be deleted.');

        $purchase->delete();
    }

    public function test_cannot_post_already_posted_purchase(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $purchase = Purchase::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Draft,
        ]);

        PurchaseItem::factory()->create([
            'shop_id' => $shop->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => '5.000',
            'unit_cost' => '50.00',
            'line_total' => '250.00',
        ]);

        $action = app(PostPurchase::class);
        $action->execute($purchase);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Purchase is already posted and cannot be re-posted.');

        $action->execute($purchase);
    }
}
