<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Sales\AllocatePayment;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\PostSale;
use App\Actions\Sales\RecordPayment;
use App\Actions\Sales\ReturnSale;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SalePostFlowTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $user;

    private Warehouse $warehouse;

    private Unit $unit;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create([
            'settings' => ['allow_negative_stock' => false],
        ]);
        TenantContext::setShop($this->shop);

        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($this->shop);

        $this->user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'shop_id' => $this->shop->id,
            'is_default' => true,
        ]);

        $this->unit = Unit::firstOrCreate(['symbol' => 'kg'], [
            'name' => 'Kilogram',
            'is_fractional' => true,
        ]);

        $this->category = Category::factory()->create(['shop_id' => $this->shop->id]);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function createProductWithStock(string $name, string $costPrice, string $sellingPrice, string $stockQty): Product
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => $name,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'track_stock' => true,
        ]);

        app(ReceiveStock::class)->execute(
            product: $product,
            warehouse: $this->warehouse,
            quantity: $stockQty,
            unitCost: $costPrice,
            notes: 'Initial stocking'
        );

        return $product;
    }

    public function test_postsale_is_atomic_and_rolls_back_if_stock_insufficient(): void
    {
        $productInStock = $this->createProductWithStock('Malingo Rice 25kg', '2000.00', '2500.00', '10.0');
        $productLowStock = $this->createProductWithStock('Ghee 1L', '1000.00', '1250.00', '1.0'); // only 1 in stock

        $createSale = app(CreateSale::class);
        $postSale = app(PostSale::class);

        $draft = $createSale->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $productInStock->id,
                    'quantity' => '5.000',
                    'unit_price' => '2500.00',
                ],
                [
                    'product_id' => $productLowStock->id,
                    'quantity' => '3.000', // exceeds available stock of 1.0
                    'unit_price' => '1250.00',
                ],
            ],
        ]);

        $this->assertEquals(SaleStatus::Draft, $draft->status);

        // Attempting to post must fail with InvalidArgumentException
        $exceptionCaught = false;
        try {
            $postSale->handle($draft, $this->user->id, [
                'payment_method' => 'cash',
                'payment_amount' => '16250.00',
            ]);
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
            $this->assertSame(__('errors.insufficient_stock_exception', [
                'id' => $productLowStock->id,
                'name' => $productLowStock->name,
                'available' => '1.000',
                'requested' => '3.000',
            ]), $e->getMessage());
        }

        $this->assertTrue($exceptionCaught);

        // Ensure transaction rolled back completely:
        // 1. Sale is still Draft, with no invoice number
        $freshSale = Sale::find($draft->id);
        $this->assertEquals(SaleStatus::Draft, $freshSale->status);
        $this->assertNull($freshSale->invoice_no);

        // 2. Product 1 stock is still exactly 10.000 (not deducted)
        $stock1 = StockBalance::where('product_id', $productInStock->id)->value('quantity');
        $this->assertEquals('10.000', $stock1);

        // 3. Product 2 stock is still exactly 1.000
        $stock2 = StockBalance::where('product_id', $productLowStock->id)->value('quantity');
        $this->assertEquals('1.000', $stock2);

        $this->assertDatabaseMissing('audit_logs', [
            'auditable_type' => $draft->getMorphClass(),
            'auditable_id' => $draft->id,
        ]);
    }

    public function test_postsale_is_idempotent_with_same_idempotency_key(): void
    {
        $product = $this->createProductWithStock('Mustard Oil 1L', '200.00', '260.00', '20.0');
        $createSale = app(CreateSale::class);
        $postSale = app(PostSale::class);

        $draft = $createSale->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '4.000',
                    'unit_price' => '260.00',
                ],
            ],
        ]);

        $idemKey = 'unique_sale_key_12345';

        // First post
        $sale1 = $postSale->handle($draft, $this->user->id, [
            'idempotency_key' => $idemKey,
            'payment_method' => 'cash',
            'payment_amount' => '1040.00',
        ]);

        $this->assertEquals(SaleStatus::Posted, $sale1->status);
        $this->assertNotNull($sale1->invoice_no);

        $audit = AuditLog::query()
            ->where('auditable_type', $sale1->getMorphClass())
            ->where('auditable_id', $sale1->id)
            ->where('event', 'posted')
            ->firstOrFail();
        $this->assertSame($this->shop->id, $audit->shop_id);
        $this->assertSame($this->user->id, $audit->user_id);
        $this->assertSame('draft', $audit->old_values['sale']['status']);
        $this->assertSame('posted', $audit->new_values['sale']['status']);
        $this->assertCount(1, $audit->new_values['items']);
        $this->assertCount(1, $audit->new_values['payments']);

        // Remaining stock should be 16.000 (20 - 4)
        $stockAfterFirst = StockBalance::where('product_id', $product->id)->value('quantity');
        $this->assertEquals('16.000', $stockAfterFirst);

        // Re-post with same idempotency key
        $sale2 = $postSale->handle($sale1, $this->user->id, [
            'idempotency_key' => $idemKey,
            'payment_method' => 'cash',
            'payment_amount' => '1040.00',
        ]);

        // Must return the exact same sale
        $this->assertEquals($sale1->id, $sale2->id);
        $this->assertEquals($sale1->invoice_no, $sale2->invoice_no);

        // Stock must NOT be deducted again
        $stockAfterSecond = StockBalance::where('product_id', $product->id)->value('quantity');
        $this->assertEquals('16.000', $stockAfterSecond);
    }

    public function test_unit_cost_snapshot_stored_and_immune_to_later_cost_changes(): void
    {
        // 1. Initial product with moving average cost of 100.00
        $product = $this->createProductWithStock('Sugar 1kg', '100.00', '130.00', '50.0');

        $createSale = app(CreateSale::class);
        $postSale = app(PostSale::class);

        // 2. Sell 10 units
        $sale = $createSale->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '10.000',
                    'unit_price' => '130.00',
                ],
            ],
        ]);

        $postedSale = $postSale->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '1300.00',
        ]);

        // Verify snapshot on sale item
        $saleItem = $postedSale->items()->first();
        $this->assertEquals('100.00', $saleItem->unit_cost);

        // 3. Receive new inventory at a significantly higher price: 200.00
        app(ReceiveStock::class)->execute(
            product: $product,
            warehouse: $this->warehouse,
            quantity: '50.0',
            unitCost: '200.00',
            notes: 'High cost shipment'
        );

        // Update product's cost_price attribute directly too
        $product->update(['cost_price' => '200.00']);

        // 4. Reload historical sale item — its unit_cost snapshot must remain 100.00
        $saleItem->refresh();
        $this->assertEquals('100.00', $saleItem->unit_cost);
    }

    public function test_partial_payment_and_multi_method_payments_allocate_correctly(): void
    {
        $product = $this->createProductWithStock('Basmati Rice', '120.00', '150.00', '30.0');
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        $createSale = app(CreateSale::class);
        $postSale = app(PostSale::class);
        $recordPay = app(RecordPayment::class);
        $allocPay = app(AllocatePayment::class);

        // Sale grand total: 10 * 150 = 1500.00
        $sale = $createSale->handle([
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '10.000',
                    'unit_price' => '150.00',
                ],
            ],
        ]);

        // Post with partial payment of 500.00 in cash
        $posted = $postSale->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '500.00',
        ]);

        $this->assertEquals('1500.00', $posted->grand_total);
        $this->assertEquals('500.00', $posted->paid_total);
        $this->assertEquals('1000.00', $posted->dueAmount());

        // Later, customer pays remaining 1000.00 via QR/eSewa
        $qrPayment = $recordPay->handle($this->user->id, [
            'method' => PaymentMethod::Qr->value,
            'amount' => '1000.00',
            'customer_id' => $customer->id,
            'reference_number' => 'ESEWA-TXN-9988',
            'allocate_to_sale_id' => $posted->id,
        ]);

        $posted->refresh();

        $this->assertEquals('1500.00', $posted->paid_total);
        $this->assertEquals('0.00', $posted->dueAmount());
        $this->assertCount(2, $posted->paymentAllocations);
    }

    public function test_returns_restore_stock_at_original_unit_cost_snapshot(): void
    {
        $product = $this->createProductWithStock('Chana Dal 1kg', '110.00', '140.00', '25.0');

        $createSale = app(CreateSale::class);
        $postSale = app(PostSale::class);
        $returnSale = app(ReturnSale::class);

        $sale = $createSale->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '10.000',
                    'unit_price' => '140.00',
                ],
            ],
        ]);

        $posted = $postSale->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '1400.00',
        ]);

        // Stock after sale: 25 - 10 = 15.000
        $this->assertEquals('15.000', StockBalance::where('product_id', $product->id)->value('quantity'));

        $saleItem = $posted->items->first();

        // Customer returns 4 units
        $saleReturn = $returnSale->handle($posted, $this->user->id, [
            [
                'sale_item_id' => $saleItem->id,
                'quantity' => '4.000',
            ],
        ], 'Customer bought excess');

        $this->assertNotNull($saleReturn);
        $this->assertEquals('560.00', $saleReturn->total); // 4 * 140

        $audit = AuditLog::query()
            ->where('auditable_type', $saleReturn->getMorphClass())
            ->where('auditable_id', $saleReturn->id)
            ->where('event', 'returned')
            ->firstOrFail();
        $this->assertSame($this->shop->id, $audit->shop_id);
        $this->assertSame($this->user->id, $audit->user_id);
        $this->assertSame($posted->id, $audit->old_values['sale_id']);
        $this->assertSame('560.00', $audit->new_values['total']);

        // Restored stock: 15 + 4 = 19.000
        $stockAfterReturn = StockBalance::where('product_id', $product->id)->value('quantity');
        $this->assertEquals('19.000', $stockAfterReturn);

        // Stock movement recorded with original unit cost
        $movement = StockMovement::where('product_id', $product->id)
            ->where('movement_type', 'sale_return')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('4.000', $movement->quantity);
        $this->assertEquals('110.00', $movement->unit_cost);
    }

    public function test_cannot_return_more_than_sold_quantity(): void
    {
        $product = $this->createProductWithStock('Soap', '40.00', '50.00', '10.0');
        $createSale = app(CreateSale::class);
        $postSale = app(PostSale::class);
        $returnSale = app(ReturnSale::class);

        $sale = $createSale->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '3.000',
                    'unit_price' => '50.00',
                ],
            ],
        ]);

        $posted = $postSale->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '150.00',
        ]);

        $saleItem = $posted->items->first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('errors.return_exceeds_quantity', [
            'requested' => '5.000',
            'maximum' => '3.000',
            'id' => $saleItem->id,
        ]));

        // Attempting to return 5 units when only 3 were purchased
        $returnSale->handle($posted, $this->user->id, [
            [
                'sale_item_id' => $saleItem->id,
                'quantity' => '5.000',
            ],
        ]);
    }

    public function test_draft_sale_cancellation_leaves_stock_untouched(): void
    {
        $product = $this->createProductWithStock('Tea 500g', '250.00', '320.00', '15.0');
        $createSale = app(CreateSale::class);
        $cancelSale = app(CancelSale::class);

        $sale = $createSale->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '5.000',
                    'unit_price' => '320.00',
                ],
            ],
        ]);

        // Cancel draft
        $cancelled = $cancelSale->handle($sale, $this->user->id);
        $this->assertEquals(SaleStatus::Cancelled, $cancelled->status);

        // Stock was never touched: still 15.000
        $this->assertEquals('15.000', StockBalance::where('product_id', $product->id)->value('quantity'));
    }

    public function test_posted_sale_cannot_be_cancelled(): void
    {
        $product = $this->createProductWithStock('Coffee 200g', '400.00', '550.00', '10.0');
        $createSale = app(CreateSale::class);
        $postSale = app(PostSale::class);
        $cancelSale = app(CancelSale::class);

        $sale = $createSale->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2.000',
                    'unit_price' => '550.00',
                ],
            ],
        ]);

        $posted = $postSale->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '1100.00',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('sales.immutable_exception', [
            'id' => $posted->id,
            'status' => __('sales.status_posted'),
        ]));

        $cancelSale->handle($posted, $this->user->id);
    }

    public function test_cross_tenant_sale_access_is_blocked_with_404(): void
    {
        $productA = $this->createProductWithStock('Shop A Flour', '60.00', '80.00', '20.0');
        $createSale = app(CreateSale::class);
        $postSale = app(PostSale::class);

        $saleA = $createSale->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => '2.000',
                    'unit_price' => '80.00',
                ],
            ],
        ]);
        $postSale->handle($saleA, $this->user->id, ['payment_amount' => '160.00']);

        // Create Shop B and User B
        $shopB = Shop::factory()->create();
        $userB = User::factory()->create();
        $rolesB = TenantContext::runAs($shopB, function () use ($shopB) {
            return app(RbacService::class)->createDefaultRolesForShop($shopB);
        });

        ShopUser::factory()->create([
            'shop_id' => $shopB->id,
            'user_id' => $userB->id,
            'role_id' => $rolesB['Shop Owner']->id,
            'status' => 'active',
        ]);

        // User B attempts to access Shop A's sale
        $response = $this->actingAs($userB)
            ->withSession(['current_shop_id' => $shopB->id])
            ->get("/sales/{$saleA->id}");

        $response->assertStatus(404);
    }
}
