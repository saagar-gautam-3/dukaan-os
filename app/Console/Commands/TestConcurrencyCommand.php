<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Inventory\ReceiveStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TestConcurrencyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:test-concurrency';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrate and verify that lockForUpdate prevents overselling under concurrent parallel worker execution.';

    /**
     * Execute the console command.
     */
    public function handle(ReceiveStock $receiveStock): int
    {
        $this->info('===========================================================');
        $this->info('Starting Real Concurrency Test on MySQL with 2 Parallel Workers');
        $this->info('===========================================================');

        // 1. Create a clean test shop, warehouse, product with initial balance = 5.000
        $shop = Shop::create([
            'name' => 'Concurrency Test Kirana',
            'slug' => 'concurrency-kirana-'.uniqid(),
            'status' => 'active',
            'business_type' => 'kirana',
            'currency' => 'NPR',
            'timezone' => 'Asia/Kathmandu',
            'settings' => ['allow_negative_stock' => false],
        ]);

        return TenantContext::runAs($shop, function () use ($shop, $receiveStock) {

            $warehouse = Warehouse::create([
                'shop_id' => $shop->id,
                'name' => 'Concurrency Godown',
                'code' => 'CG-1',
                'is_default' => true,
            ]);

            $unit = Unit::firstOrCreate(['symbol' => 'pc'], [
                'name' => 'Piece',
                'is_fractional' => false,
            ]);

            $category = Category::create([
                'shop_id' => $shop->id,
                'name' => 'General',
                'slug' => 'general-'.uniqid(),
            ]);

            $product = Product::create([
                'shop_id' => $shop->id,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'name' => 'Wai Wai Noodles Concurrency Pack',
                'sku' => 'WW-CONCUR-'.uniqid(),
                'cost_price' => '20.00',
                'selling_price' => '25.00',
                'min_stock_alert' => '2.000',
            ]);

            // Receive initial stock of 5.000
            $receiveStock->execute(
                product: $product,
                warehouse: $warehouse,
                quantity: 5.000,
                unitCost: 20.00,
                notes: 'Initial stock setup for concurrency test'
            );

            $balanceBefore = StockBalance::where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->value('quantity');

            $this->info("Initial stock balance: {$balanceBefore} units.");
            $this->info('Launching TWO concurrent background workers simultaneously racing to deduct 5.000 units each...');

            $phpBinary = PHP_BINARY;
            $artisanPath = base_path('artisan');

            $worker1Command = [
                $phpBinary,
                $artisanPath,
                'inventory:decrement-worker',
                (string) $shop->id,
                (string) $product->id,
                (string) $warehouse->id,
                '5.000',
            ];

            $worker2Command = [
                $phpBinary,
                $artisanPath,
                'inventory:decrement-worker',
                (string) $shop->id,
                (string) $product->id,
                (string) $warehouse->id,
                '5.000',
            ];

            $p1 = new Process($worker1Command);
            $p2 = new Process($worker2Command);

            // Start both processes asynchronously in parallel
            $p1->start();
            $p2->start();

            // Wait for both to finish
            while ($p1->isRunning() || $p2->isRunning()) {
                usleep(10000); // 10ms
            }

            $exitCode1 = $p1->getExitCode();
            $exitCode2 = $p2->getExitCode();

            $output1 = trim($p1->getOutput().$p1->getErrorOutput());
            $output2 = trim($p2->getOutput().$p2->getErrorOutput());

            $this->line("Worker 1 output (Exit Code: {$exitCode1}): {$output1}");
            $this->line("Worker 2 output (Exit Code: {$exitCode2}): {$output2}");

            // Re-read stock balance from DB
            $balanceAfter = StockBalance::where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->value('quantity');

            $outMovementsCount = StockMovement::where('product_id', $product->id)
                ->where('movement_type', 'adjustment_out')
                ->count();

            $this->info("Final stock balance in DB: {$balanceAfter} units.");
            $this->info("Adjustment out movements recorded: {$outMovementsCount}");

            // Assertions
            $exitCodes = [$exitCode1, $exitCode2];
            sort($exitCodes);

            $passed = ($exitCodes === [0, 2])
                && (Money::compare((string) $balanceAfter, '0.000', 3) === 0)
                && ($outMovementsCount === 1);

            if ($passed) {
                $this->info('===========================================================');
                $this->info('SUCCESS: Concurrency Test Passed!');
                $this->info('Exactly one worker succeeded (exit code 0), and exactly one worker was blocked by lockForUpdate (exit code 2).');
                $this->info('Stock balance is 0.000. Overselling was strictly prevented!');
                $this->info('===========================================================');

                return 0;
            }

            $this->error('===========================================================');
            $this->error('FAILURE: Concurrency test did not behave as expected.');
            $this->error("Exit codes: [{$exitCode1}, {$exitCode2}]. Balance: {$balanceAfter}. Out movements: {$outMovementsCount}");
            $this->error('===========================================================');

            return 1;
        });
    }
}
