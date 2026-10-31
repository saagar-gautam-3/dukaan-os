<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

final class QueryStockForms
{
    /** @return array{products: Collection, warehouses: Collection} */
    public function adjustment(): array
    {
        return [
            'products' => Product::where('is_active', true)->with('unit')->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ];
    }

    public function warehouses(): Collection
    {
        return Warehouse::orderBy('name')->get();
    }

    public function products(): Collection
    {
        return Product::where('is_active', true)->with('unit')->orderBy('name')->get();
    }
}
