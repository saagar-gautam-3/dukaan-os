<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentPurchaseReceiveTest extends TestCase
{
    protected ?Shop $testShop = null;

    protected function tearDown(): void
    {
        TenantContext::reset();

        if ($this->testShop) {
            $this->testShop->delete();
        }

        parent::tearDown();
    }

    public function test_concurrent_purchase_receives_are_serialized_and_cost_is_consistent(): void
    {
        $shop = Shop::factory()->create();
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
            'cost_price' => '0.00',
        ]);

        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');

        $worker1Command = [
            $phpBinary,
            $artisanPath,
            'inventory:receive-worker',
            (string) $shop->id,
            (string) $product->id,
            (string) $warehouse->id,
            '5.000',
            '30.00',
        ];

        $worker2Command = [
            $phpBinary,
            $artisanPath,
            'inventory:receive-worker',
            (string) $shop->id,
            (string) $product->id,
            (string) $warehouse->id,
            '5.000',
            '40.00',
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
            usleep(10000);
        }

        $exitCode1 = $process1->getExitCode();
        $exitCode2 = $process2->getExitCode();

        $output1 = trim($process1->getOutput().$process1->getErrorOutput());
        $output2 = trim($process2->getOutput().$process2->getErrorOutput());

        $this->assertSame(0, $exitCode1, "Worker 1 failed: {$output1}");
        $this->assertSame(0, $exitCode2, "Worker 2 failed: {$output2}");

        $finalBalance = StockBalance::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity');
        $this->assertEquals('10.000', $finalBalance);

        $finalCost = Product::withoutGlobalScopes()->find($product->id)->cost_price;
        $this->assertEquals('35.00', (string) $finalCost);
    }
}
