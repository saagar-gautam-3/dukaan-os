<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Product;
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
                $totalStock = (float) $product->stockBalances->sum('quantity');

                return $totalStock <= (float) $product->low_stock_threshold;
            });
    }
}
