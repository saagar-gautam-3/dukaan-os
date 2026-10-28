<?php

declare(strict_types=1);

namespace App\Services;

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
        string|float $currentQty,
        string|float $currentCost,
        string|float $incomingQty,
        string|float $incomingCost
    ): string {
        $curQty = (string) $currentQty;
        $curCost = (string) $currentCost;
        $incQty = (string) $incomingQty;
        $incCost = (string) $incomingCost;

        // If current stock is zero or negative, new cost is simply the incoming purchase cost
        if (bccomp($curQty, '0', 3) <= 0) {
            return number_format((float) $incCost, 2, '.', '');
        }

        // If incoming quantity is zero, retain current cost
        if (bccomp($incQty, '0', 3) <= 0) {
            return number_format((float) $curCost, 2, '.', '');
        }

        $currentValue = bcmul($curQty, $curCost, 6);
        $incomingValue = bcmul($incQty, $incCost, 6);
        $totalValue = bcadd($currentValue, $incomingValue, 6);
        $totalQuantity = bcadd($curQty, $incQty, 6);

        if (bccomp($totalQuantity, '0', 6) <= 0) {
            return number_format((float) $incCost, 2, '.', '');
        }

        $weightedCost = bcdiv($totalValue, $totalQuantity, 4);

        return number_format((float) $weightedCost, 2, '.', '');
    }
}
