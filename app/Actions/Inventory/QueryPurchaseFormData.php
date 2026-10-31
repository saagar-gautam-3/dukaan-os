<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;

final class QueryPurchaseFormData
{
    public function handle(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'products' => Product::where('is_active', true)->with('unit')->orderBy('name')->get(),
        ];
    }

    public function suppliers(): mixed
    {
        return Supplier::orderBy('name')->get();
    }
}
