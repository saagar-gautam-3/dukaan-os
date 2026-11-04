<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QueryStockMovements
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(Product $product, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = StockMovement::where('product_id', $product->id)
            ->with(['warehouse', 'createdBy'])
            ->latest();

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
