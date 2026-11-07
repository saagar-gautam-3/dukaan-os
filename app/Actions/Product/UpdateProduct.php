<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateProduct
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $threshold = $data['low_stock_threshold'] ?? $data['min_stock_alert'] ?? $product->low_stock_threshold;

            $shop = $product->shop;
            $auto = (bool) ($shop?->settings['sku_auto_generate'] ?? true);
            $sku = $auto ? $product->sku : (($data['sku'] ?? '') === '' ? null : $data['sku']);

            $product->update([
                'name' => $data['name'],
                'name_ne' => $data['name_ne'] ?? $product->name_ne,
                'sku' => $sku,
                'barcode' => $data['barcode'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'unit_id' => $data['unit_id'],
                'cost_price' => $data['cost_price'],
                'selling_price' => $data['selling_price'],
                'low_stock_threshold' => $threshold,
                'track_stock' => $data['track_stock'] ?? $product->track_stock,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $product->is_active,
            ]);

            return $product->fresh();
        });
    }
}
