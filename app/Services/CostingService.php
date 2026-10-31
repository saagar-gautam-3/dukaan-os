<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Money;

class CostingService
{
    /**
     * Calculate moving weighted average cost.
     *
     * Formula:
     * total_cost = (current_qty * current_unit_cost) + (incoming_qty * incoming_unit_cost)
     * total_qty  = current_qty + incoming_qty
     * new_cost   = total_cost / total_qty
     *
     * If current_qty <= 0, incoming_unit_cost is adopted directly.
     */
    public function calculateWeightedAverage(
        string|int $currentQty,
        string|int $currentCost,
        string|int $incomingQty,
        string|int $incomingCost
    ): string {
        $curQty = (string) $currentQty;
        $curCost = (string) $currentCost;
        $incQty = (string) $incomingQty;
        $incCost = (string) $incomingCost;

        // If current stock is zero or negative, new cost is simply the incoming purchase cost
        if (Money::compare($curQty, '0', 3) <= 0) {
            return Money::round($incCost, 2);
        }

        // If incoming quantity is zero, retain current cost
        if (Money::compare($incQty, '0', 3) <= 0) {
            return Money::round($curCost, 2);
        }

        $currentValue = Money::multiply($curQty, $curCost, 6);
        $incomingValue = Money::multiply($incQty, $incCost, 6);
        $totalValue = Money::add($currentValue, $incomingValue, 6);
        $totalQuantity = Money::add($curQty, $incQty, 6);

        if (Money::compare($totalQuantity, '0', 6) <= 0) {
            return Money::round($incCost, 2);
        }

        $weightedCost = Money::divide($totalValue, $totalQuantity, 4);

        return Money::round($weightedCost, 2);
    }
}
