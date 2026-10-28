<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\StockBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QueryStockBalances
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = StockBalance::with(['product.unit', 'product.category', 'warehouse']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $filter = $filters['filter'] ?? null;
        if ($filter === 'low_stock') {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('stock_balances.quantity', '<=', 'products.min_stock_alert')
                    ->where('stock_balances.quantity', '>', 0);
            });
        } elseif ($filter === 'out_of_stock') {
            $query->where('quantity', '<=', 0);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
