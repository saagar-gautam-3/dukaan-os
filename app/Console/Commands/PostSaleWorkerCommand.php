<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Sales\CreateSale;
use App\Actions\Sales\PostSale;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class PostSaleWorkerCommand extends Command
{
    protected $signature = 'sales:post-worker
                            {shop_id : The tenant shop ID}
                            {product_id : The product ID}
                            {warehouse_id : The warehouse ID}
                            {quantity : The quantity to sell}
                            {user_id : The user ID posting}
                            {--delay-ms=0 : Optional delay in ms}';

    protected $description = 'Worker command used to test concurrent sale posting with lockForUpdate.';

    public function handle(CreateSale $createSale, PostSale $postSale): int
    {
        $shopId = (int) $this->argument('shop_id');
        $productId = (int) $this->argument('product_id');
        $warehouseId = (int) $this->argument('warehouse_id');
        $quantity = (string) $this->argument('quantity');
        $userId = (int) $this->argument('user_id');
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
            $this->error('Product or warehouse not found.');

            return 1;
        }

        try {
            // Create draft sale
            $sale = $createSale->handle([
                'warehouse_id' => $warehouse->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => (string) $product->selling_price,
                    ],
                ],
            ]);

            // Attempt to post sale
            $postSale->handle($sale, $userId, [
                'payment_method' => 'cash',
                'payment_amount' => $sale->grand_total,
            ]);

            $this->info('SUCCESS: Worker PID '.getmypid().' posted sale.');

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
