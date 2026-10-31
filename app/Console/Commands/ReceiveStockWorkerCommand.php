<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Inventory\ReceiveStock;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

class ReceiveStockWorkerCommand extends Command
{
    protected $signature = 'inventory:receive-worker
                            {shop_id : The tenant shop ID}
                            {product_id : The product ID}
                            {warehouse_id : The warehouse ID}
                            {quantity : The quantity to receive}
                            {unit_cost : The unit cost}';

    protected $description = 'Worker command used to test concurrent purchase receives with lockForUpdate.';

    public function handle(ReceiveStock $receiveStock): int
    {
        $shopId = (int) $this->argument('shop_id');
        $productId = (int) $this->argument('product_id');
        $warehouseId = (int) $this->argument('warehouse_id');
        $quantity = (string) $this->argument('quantity');
        $unitCost = (string) $this->argument('unit_cost');

        $shop = Shop::find($shopId);
        if (! $shop) {
            $this->error("Shop {$shopId} not found.");

            return 1;
        }

        return TenantContext::runAs($shop, function () use ($productId, $warehouseId, $quantity, $unitCost, $receiveStock) {
            $product = Product::find($productId);
            $warehouse = Warehouse::find($warehouseId);

            if (! $product || ! $warehouse) {
                $this->error('Product or Warehouse not found.');

                return 1;
            }

            try {
                $receiveStock->execute(
                    product: $product,
                    warehouse: $warehouse,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    notes: 'Concurrent receive test'
                );

                $this->info('SUCCESS: Worker PID '.getmypid()." received {$quantity} units.");

                return 0;
            } catch (Throwable $e) {
                $this->error('ERROR: Worker PID '.getmypid().' failed: '.$e->getMessage());

                return 1;
            }
        });
    }
}
