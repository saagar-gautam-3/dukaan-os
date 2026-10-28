<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\CostingService;
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
        string|float $quantity,
        string|float $unitCost,
        MovementType $movementType = MovementType::PurchaseReceive,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        if (bccomp((string) $quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException('Received quantity must be greater than zero.');
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
            // Lock or create the StockBalance row
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

                // Re-lock
                $balance = StockBalance::where('id', $balance->id)->lockForUpdate()->first();
            }

            // Calculate moving weighted average cost if this is a purchase receive
            $newCost = (string) $product->cost_price;
            if ($movementType === MovementType::PurchaseReceive) {
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
            $newQuantity = bcadd((string) $balance->quantity, (string) $quantity, 3);
            $balance->quantity = $newQuantity;
            $balance->average_cost = $newCost;
            $balance->save();

            // Append stock movement
            $movement = new StockMovement;
            $movement->shop_id = $warehouse->shop_id ?? TenantContext::getShopId();
            $movement->warehouse_id = $warehouse->id;
            $movement->product_id = $product->id;
            $movement->movement_type = $movementType;
            $movement->quantity = number_format((float) $quantity, 3, '.', '');
            $movement->unit_cost = number_format((float) $unitCost, 2, '.', '');
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
