<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReverseStockMovement
{
    /**
     * Reverse a stock movement by recording an opposite reversal movement and updating the balance.
     */
    public function execute(
        StockMovement $originalMovement,
        ?string $reason = null,
        ?int $userId = null
    ): StockMovement {
        if ($originalMovement->movement_type === MovementType::Reversal) {
            throw new InvalidArgumentException('Cannot reverse an existing reversal movement.');
        }

        return DB::transaction(function () use ($originalMovement, $reason, $userId) {
            $shop = TenantContext::has() ? TenantContext::currentShop() : null;
            $allowNegativeStock = (bool) ($shop?->settings['allow_negative_stock'] ?? false);

            /** @var StockBalance $balance */
            $balance = StockBalance::query()
                ->where('warehouse_id', $originalMovement->warehouse_id)
                ->where('product_id', $originalMovement->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Reverse the quantity: if original was +10, reverse is -10; if original was -5, reverse is +5
            $originalQty = (string) $originalMovement->quantity;
            $reversalQty = Money::multiply($originalQty, '-1', 3);

            $newBalance = Money::add((string) $balance->quantity, $reversalQty, 3);
            if (! $allowNegativeStock && Money::compare($newBalance, '0', 3) < 0) {
                throw new InvalidArgumentException('Reversal would result in negative stock, which is not permitted.');
            }

            $balance->quantity = $newBalance;
            $balance->save();

            $reversal = new StockMovement;
            $reversal->shop_id = $originalMovement->shop_id;
            $reversal->warehouse_id = $originalMovement->warehouse_id;
            $reversal->product_id = $originalMovement->product_id;
            $reversal->movement_type = MovementType::Reversal;
            $reversal->quantity = $reversalQty;
            $reversal->unit_cost = $originalMovement->unit_cost;
            $reversal->reference_type = StockMovement::class;
            $reversal->reference_id = $originalMovement->id;
            $reversal->notes = 'Reversal of movement #'.$originalMovement->id.($reason ? ': '.$reason : '');
            $reversal->created_by = $userId ?? auth()->id();
            $reversal->save();

            return $reversal;
        });
    }
}
