<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\ReceiveStock;
use App\Actions\Inventory\ReverseStockMovement;
use App\Enums\MovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_movement_and_balance_atomicity_on_receive_stock(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'cost_price' => '100.00',
        ]);

        $receiveAction = app(ReceiveStock::class);

        // Receive 20 units @ 120.00
        $result = $receiveAction->execute(
            product: $product,
            warehouse: $warehouse,
            quantity: 20.000,
            unitCost: 120.00,
            notes: 'Test PO delivery'
        );

        // Balance must be 20.000
        $this->assertEquals('20.000', $result['balance']->quantity);
        $this->assertDatabaseHas('stock_balances', [
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '20.000',
        ]);

        // Stock movement must be created
        $this->assertDatabaseHas('stock_movements', [
            'id' => $result['movement']->id,
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => MovementType::PurchaseReceive->value,
            'quantity' => '20.000',
            'unit_cost' => '120.00',
        ]);

        // Product cost price updated to 120.00 (was 0 stock previously)
        $product->refresh();
        $this->assertEquals('120.00', $product->cost_price);
    }

    public function test_movement_and_balance_rollback_leaves_both_unchanged(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $receiveAction = app(ReceiveStock::class);

        try {
            DB::transaction(function () use ($receiveAction, $product, $warehouse) {
                $receiveAction->execute(
                    product: $product,
                    warehouse: $warehouse,
                    quantity: 15.000,
                    unitCost: 80.00
                );

                throw new Exception('Simulated fatal transaction failure');
            });
            $this->fail('Exception was expected');
        } catch (Exception $e) {
            $this->assertSame('Simulated fatal transaction failure', $e->getMessage());
        }

        // Atomicity invariant: Neither balance nor movement exists
        $this->assertDatabaseMissing('stock_balances', [
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_adjust_stock_in_increases_balance_and_creates_movement(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $adjustAction = app(AdjustStock::class);

        $result = $adjustAction->execute(
            warehouse: $warehouse,
            items: [
                [
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => '25.000',
                    'unit_cost' => '100.00',
                ],
            ],
            reason: 'Found extra inventory during stock take'
        );

        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $result['adjustment']->id,
            'reason' => 'Found extra inventory during stock take',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('stock_balances', [
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '25.000',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'shop_id' => $shop->id,
            'movement_type' => MovementType::AdjustmentIn->value,
            'quantity' => '25.000',
        ]);
    }

    public function test_negative_stock_is_blocked_by_default(): void
    {
        $shop = Shop::factory()->create([
            'settings' => ['allow_negative_stock' => false],
        ]);
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        // Initial balance is 5.000
        StockBalance::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '5.000',
        ]);

        $adjustAction = app(AdjustStock::class);

        // Attempt to adjust out 10.000 (which would leave balance at -5.000)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock for product');

        $adjustAction->execute(
            warehouse: $warehouse,
            items: [
                [
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => '10.000',
                ],
            ],
            reason: 'Damaged stock deduction'
        );

        // Stock balance must remain unchanged at 5.000
        $this->assertDatabaseHas('stock_balances', [
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '5.000',
        ]);
    }

    public function test_negative_stock_allowed_if_shop_setting_enabled(): void
    {
        $shop = Shop::factory()->create([
            'settings' => ['allow_negative_stock' => true],
        ]);
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        StockBalance::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '5.000',
        ]);

        $adjustAction = app(AdjustStock::class);

        $adjustAction->execute(
            warehouse: $warehouse,
            items: [
                [
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => '10.000',
                ],
            ],
            reason: 'Oversold inventory'
        );

        $this->assertDatabaseHas('stock_balances', [
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '-5.000',
        ]);
    }

    public function test_reversal_movement_reverses_balance_and_records_reversal_movement(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        // Receive stock first (+20.000)
        $receiveResult = app(ReceiveStock::class)->execute(
            product: $product,
            warehouse: $warehouse,
            quantity: 20.000,
            unitCost: 100.00
        );

        $originalMovement = $receiveResult['movement'];

        // Reverse the receive movement
        $reversalAction = app(ReverseStockMovement::class);
        $reversal = $reversalAction->execute($originalMovement, reason: 'Wrong shipment delivered');

        $this->assertEquals(MovementType::Reversal, $reversal->movement_type);
        $this->assertEquals('-20.000', $reversal->quantity);
        $this->assertSame($originalMovement->id, $reversal->reference_id);

        // Balance is restored to 0.000
        $this->assertDatabaseHas('stock_balances', [
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => '0.000',
        ]);
    }

    public function test_reversal_cannot_reverse_a_reversal(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $receiveResult = app(ReceiveStock::class)->execute(
            product: $product,
            warehouse: $warehouse,
            quantity: 10.000,
            unitCost: 50.00
        );

        $reversalAction = app(ReverseStockMovement::class);
        $reversal = $reversalAction->execute($receiveResult['movement']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot reverse an existing reversal movement.');

        $reversalAction->execute($reversal);
    }
}
