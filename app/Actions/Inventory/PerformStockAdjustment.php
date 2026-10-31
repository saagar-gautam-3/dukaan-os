<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Product;
use App\Models\Warehouse;

final class PerformStockAdjustment
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    public function handle(array $data, int $userId): void
    {
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $product = Product::findOrFail($data['product_id']);

        $this->adjustStock->execute(
            warehouse: $warehouse,
            items: [[
                'product_id' => $product->id,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'] ?? $product->cost_price,
            ]],
            reason: $data['reason'],
            userId: $userId,
        );
    }
}
