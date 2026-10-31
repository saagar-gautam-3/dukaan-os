<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Collection;

class QueryStockAlerts
{
    /**
     * @return Collection<int, Product>
     */
    public function execute(): Collection
    {
        return Product::with(['unit', 'category', 'stockBalances'])
            ->where('is_active', true)
            ->where('low_stock_threshold', '>', 0)
            ->get()
            ->filter(function (Product $product) {
                $totalStock = $product->stockBalances->reduce(
                    fn (string $sum, $balance): string => Money::add($sum, (string) $balance->quantity, 3),
                    '0.000'
                );

                return Money::compare($totalStock, (string) $product->low_stock_threshold, 3) <= 0;
            });
    }
}
