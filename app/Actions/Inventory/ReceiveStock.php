<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\CostingService;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReceiveStock
{
    public function __construct(
        protected CostingService $costingService
    ) {}

    /**
     * Receive stock into a warehouse, atomically updating stock balance,
     * calculating moving weighted average cost, and writing a stock movement.
     *
     * @return array{movement: StockMovement, balance: StockBalance, new_cost: string}
     */
    public function execute(
        Product $product,
        Warehouse $warehouse,
        string|int $quantity,
        string|int $unitCost,
        MovementType $movementType = MovementType::PurchaseReceive,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        if (Money::compare((string) $quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException('Received quantity must be greater than zero.');
        }

        if ($movementType === MovementType::Opening && Money::compare((string) $unitCost, '0', 2) <= 0) {
            throw new InvalidArgumentException('Opening stock requires a unit cost greater than zero.');
        }

        return DB::transaction(function () use (
            $product,
            $warehouse,
            $quantity,
            $unitCost,
            $movementType,
            $referenceType,
            $referenceId,
            $notes,
            $userId
        ) {
            // Race-safe: insert a zero row if missing (ignored on unique conflict),
            // then lock it. This avoids two concurrent receives both inserting.
            DB::table('stock_balances')->insertOrIgnore([
                'shop_id' => $warehouse->shop_id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity' => '0.000',
                'average_cost' => '0.00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Lock the StockBalance row
            /** @var StockBalance $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Calculate moving weighted average cost if this is a purchase receive
            $newCost = (string) $product->cost_price;
            if ($movementType === MovementType::PurchaseReceive) {
                // Lock the product row to prevent concurrent cost-price races.
                // Both this lock and the StockBalance lock must be acquired
                // in the same order everywhere to avoid deadlocks.
                /** @var Product $product */
                $product = Product::withoutGlobalScopes()
                    ->where('id', $product->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $newCost = $this->costingService->calculateWeightedAverage(
                    $balance->quantity,
                    $product->cost_price,
                    $quantity,
                    $unitCost
                );

                $product->cost_price = $newCost;
                $product->save();
            }

            // Increment balance quantity and persist weighted average cost per warehouse
            $newQuantity = Money::add((string) $balance->quantity, (string) $quantity, 3);
            $balance->quantity = $newQuantity;
            $balance->average_cost = $newCost;
            $balance->save();

            // Append stock movement
            $movement = new StockMovement;
            $movement->shop_id = $warehouse->shop_id ?? TenantContext::getShopId();
            $movement->warehouse_id = $warehouse->id;
            $movement->product_id = $product->id;
            $movement->movement_type = $movementType;
            $movement->quantity = Money::round((string) $quantity, 3);
            $movement->unit_cost = Money::round((string) $unitCost, 2);
            $movement->reference_type = $referenceType;
            $movement->reference_id = $referenceId;
            $movement->notes = $notes;
            $movement->created_by = $userId ?? auth()->id();
            $movement->save();

            return [
                'movement' => $movement,
                'balance' => $balance,
                'new_cost' => $newCost,
            ];
        });
    }
}
