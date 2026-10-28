<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentStatus;
use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdjustStock
{
    /**
     * Perform stock adjustments atomically with row-locking and negative stock validation.
     *
     * @param  array<int, array{product_id: int, type: string, quantity: string|float, unit_cost?: string|float|null}>  $items
     * @return array{adjustment: StockAdjustment, movements: array<int, StockMovement>}
     */
    public function execute(
        Warehouse $warehouse,
        array $items,
        string $reason,
        ?int $userId = null
    ): array {
        if (empty($items)) {
            throw new InvalidArgumentException('Stock adjustment must contain at least one item.');
        }

        return DB::transaction(function () use ($warehouse, $items, $reason, $userId) {
            $shop = $warehouse->shop ?? (TenantContext::has() ? TenantContext::currentShop() : null);
            $allowNegativeStock = (bool) ($shop?->settings['allow_negative_stock'] ?? false);

            $adjustmentNo = 'ADJ-'.strtoupper(Str::random(8));

            $adjustment = new StockAdjustment;
            $adjustment->shop_id = $warehouse->shop_id ?? TenantContext::getShopId();
            $adjustment->warehouse_id = $warehouse->id;
            $adjustment->adjustment_no = $adjustmentNo;
            $adjustment->reason = $reason;
            $adjustment->status = AdjustmentStatus::Posted;
            $adjustment->posted_at = now();
            $adjustment->created_by = $userId ?? auth()->id();
            $adjustment->save();

            $movements = [];

            foreach ($items as $itemData) {
                /** @var Product $product */
                $product = Product::findOrFail($itemData['product_id']);
                $type = strtolower($itemData['type']); // 'in' or 'out'
                $qty = (string) $itemData['quantity'];
                $unitCost = (string) ($itemData['unit_cost'] ?? $product->cost_price);

                if (bccomp($qty, '0', 3) <= 0) {
                    throw new InvalidArgumentException("Quantity for product [{$product->name}] must be positive.");
                }

                /** @var StockBalance $balance */
                $balance = StockBalance::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if ($balance === null) {
                    $balance = new StockBalance;
                    $balance->warehouse_id = $warehouse->id;
                    $balance->product_id = $product->id;
                    $balance->quantity = '0.000';
                    $balance->save();

                    $balance = StockBalance::where('id', $balance->id)->lockForUpdate()->first();
                }

                if ($type === 'out') {
                    $newBalance = bcsub((string) $balance->quantity, $qty, 3);
                    if (! $allowNegativeStock && bccomp($newBalance, '0', 3) < 0) {
                        throw new InvalidArgumentException("Insufficient stock for product [{$product->name}]. Negative stock is not permitted.");
                    }
                    $movementType = MovementType::AdjustmentOut;
                    $signedQuantity = '-'.number_format((float) $qty, 3, '.', '');
                } elseif ($type === 'in') {
                    $newBalance = bcadd((string) $balance->quantity, $qty, 3);
                    $movementType = MovementType::AdjustmentIn;
                    $signedQuantity = number_format((float) $qty, 3, '.', '');
                } else {
                    throw new InvalidArgumentException("Invalid adjustment type [{$type}]. Expected 'in' or 'out'.");
                }

                $balance->quantity = $newBalance;
                $balance->save();

                // Create Adjustment Item
                $adjustmentItem = new StockAdjustmentItem;
                $adjustmentItem->shop_id = $adjustment->shop_id;
                $adjustmentItem->adjustment_id = $adjustment->id;
                $adjustmentItem->product_id = $product->id;
                $adjustmentItem->type = $type;
                $adjustmentItem->quantity = number_format((float) $qty, 3, '.', '');
                $adjustmentItem->unit_cost = number_format((float) $unitCost, 2, '.', '');
                $adjustmentItem->save();

                // Create Stock Movement
                $movement = new StockMovement;
                $movement->shop_id = $adjustment->shop_id;
                $movement->warehouse_id = $warehouse->id;
                $movement->product_id = $product->id;
                $movement->movement_type = $movementType;
                $movement->quantity = $signedQuantity;
                $movement->unit_cost = number_format((float) $unitCost, 2, '.', '');
                $movement->reference_type = StockAdjustment::class;
                $movement->reference_id = $adjustment->id;
                $movement->notes = "Adjustment #{$adjustmentNo}: {$reason}";
                $movement->created_by = $userId ?? auth()->id();
                $movement->save();

                $movements[] = $movement;
            }

            return [
                'adjustment' => $adjustment,
                'movements' => $movements,
            ];
        });
    }
}
