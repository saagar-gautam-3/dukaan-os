<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Inventory\AdjustStock;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class DecrementStockWorkerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:decrement-worker
                            {shop_id : The tenant shop ID}
                            {product_id : The product ID}
                            {warehouse_id : The warehouse ID}
                            {quantity : The quantity to deduct}
                            {--delay-ms=0 : Optional delay in milliseconds to simulate lock contention}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Worker command used to test concurrent stock decrements with lockForUpdate.';

    /**
     * Execute the console command.
     */
    public function handle(AdjustStock $adjustStock): int
    {
        $shopId = (int) $this->argument('shop_id');
        $productId = (int) $this->argument('product_id');
        $warehouseId = (int) $this->argument('warehouse_id');
        $quantity = (float) $this->argument('quantity');
        $delayMs = (int) $this->option('delay-ms');

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        $shop = Shop::find($shopId);
        if (! $shop) {
            $this->error("Shop {$shopId} not found.");

            return 1;
        }

        TenantContext::setShop($shop);

        $product = Product::find($productId);
        $warehouse = Warehouse::find($warehouseId);

        if (! $product || ! $warehouse) {
            $this->error('Product or Warehouse not found.');

            return 1;
        }

        try {
            $adjustStock->execute(
                warehouse: $warehouse,
                items: [
                    [
                        'product_id' => $product->id,
                        'type' => 'out',
                        'quantity' => $quantity,
                        'unit_cost' => $product->cost_price,
                    ],
                ],
                reason: 'Concurrency test race',
                userId: null
            );

            $this->info('SUCCESS: Worker PID '.getmypid()." deducted {$quantity} units.");

            return 0;
        } catch (InvalidArgumentException $e) {
            $this->warn('INSUFFICIENT_STOCK: Worker PID '.getmypid().' blocked: '.$e->getMessage());

            return 2;
        } catch (Throwable $e) {
            $this->error('ERROR: Worker PID '.getmypid().' failed: '.$e->getMessage());

            return 1;
        }
    }
}
