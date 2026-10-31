<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Actions\Inventory\ReceiveStock;
use App\Models\Category;
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
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentSaleTest extends TestCase
{
    protected ?Shop $testShop = null;

    protected function tearDown(): void
    {
        TenantContext::reset();

        if ($this->testShop) {
            // Delete the shop first — the DB-level FK cascade (cascadeOnDelete)
            // removes all child tenant rows (sale_items, sales, stock_movements,
            // stock_balances, products, warehouses, etc.) at the database layer,
            // bypassing Eloquent model events and the immutability triggers.
            $this->testShop->delete();
        }

        parent::tearDown();
    }

    public function test_concurrent_sales_cannot_oversell_and_are_serialized_via_pessimistic_lock(): void
    {
        $shop = Shop::factory()->create([
            'settings' => ['allow_negative_stock' => false],
        ]);
        $this->testShop = $shop;
        TenantContext::setShop($shop);

        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], [
            'name' => 'Piece',
            'is_fractional' => false,
        ]);
        $category = Category::factory()->create(['shop_id' => $shop->id]);

        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'cost_price' => '100.00',
            'selling_price' => '150.00',
            'track_stock' => true,
        ]);

        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        // Stock exactly 5.000 units
        app(ReceiveStock::class)->execute(
            product: $product,
            warehouse: $warehouse,
            quantity: 5.000,
            unitCost: 100.00,
            notes: 'Concurrency test initial stock'
        );

        $initialStock = StockBalance::where('product_id', $product->id)->value('quantity');
        $this->assertEquals('5.000', $initialStock);

        // Spawn two independent PHP processes both trying to post sale of 5.000 units
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');

        $worker1Command = [
            $phpBinary,
            $artisanPath,
            'sales:post-worker',
            (string) $shop->id,
            (string) $product->id,
            (string) $warehouse->id,
            '5.000',
            (string) $user->id,
        ];

        $worker2Command = [
            $phpBinary,
            $artisanPath,
            'sales:post-worker',
            (string) $shop->id,
            (string) $product->id,
            (string) $warehouse->id,
            '5.000',
            (string) $user->id,
        ];

        $env = [
            'APP_ENV' => 'testing',
            'DB_DATABASE' => config('database.connections.mysql.database'),
        ];

        $process1 = new Process($worker1Command, null, $env);
        $process2 = new Process($worker2Command, null, $env);

        $process1->start();
        $process2->start();

        while ($process1->isRunning() || $process2->isRunning()) {
            usleep(10000); // 10ms
        }

        $exitCode1 = $process1->getExitCode();
        $exitCode2 = $process2->getExitCode();

        $output1 = trim($process1->getOutput().$process1->getErrorOutput());
        $output2 = trim($process2->getOutput().$process2->getErrorOutput());

        $exitCodes = [$exitCode1, $exitCode2];
        sort($exitCodes);

        // Exactly one process must succeed (exit 0) and one must be rejected for insufficient stock (exit 2)
        $this->assertSame([0, 2], $exitCodes, "Expected [0, 2] but got [{$exitCode1}, {$exitCode2}]. P1: {$output1} | P2: {$output2}");

        // Remaining balance must be exactly 0.000 (never negative)
        $finalBalance = StockBalance::where('product_id', $product->id)->value('quantity');
        $this->assertEquals('0.000', $finalBalance);

        // Exactly one sale movement recorded
        $saleMovements = StockMovement::where('product_id', $product->id)
            ->where('movement_type', 'sale')
            ->count();
        $this->assertSame(1, $saleMovements);
    }
}
