<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Product;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    public function __construct(protected ResolveProductSku $resolveSku) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $threshold = $data['low_stock_threshold'] ?? $data['min_stock_alert'] ?? 0.000;

            $shop = TenantContext::currentShop();
            $sku = $this->resolveSku->execute($shop, $data['sku'] ?? null);

            return Product::create([
                'name' => $data['name'],
                'name_ne' => $data['name_ne'] ?? null,
                'sku' => $sku,
                'barcode' => $data['barcode'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'unit_id' => $data['unit_id'],
                'cost_price' => $data['cost_price'],
                'selling_price' => $data['selling_price'],
                'low_stock_threshold' => $threshold,
                'track_stock' => $data['track_stock'] ?? true,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        });
    }
}
