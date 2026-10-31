<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Product;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

class SearchPosProducts
{
    /**
     * @return Collection<int, array{id: int, name: string, sku: string, price: string, track_stock: bool, unit: ?string, stock: string}>
     */
    public function handle(string $query): Collection
    {
        $shop = TenantContext::shop();

        return Product::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('name_ne', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->with(['stockBalances', 'unit'])
            ->limit(20)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'name_ne' => $p->name_ne,
                'sku' => $p->sku,
                'price' => (string) $p->selling_price,
                'track_stock' => $p->track_stock,
                'unit' => $p->unit?->symbol,
                'stock' => $p->stockBalances->reduce(
                    fn (string $sum, $balance): string => Money::add($sum, (string) $balance->quantity, 3),
                    '0.000'
                ),
            ]);
    }
}
