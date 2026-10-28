<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;

class StorePurchase
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(array $validated, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($validated, $userId) {
            $subtotal = 0.00;
            $discountTotal = 0.00;

            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity'];
                $cost = (float) $itemData['unit_cost'];
                $discount = (float) ($itemData['discount'] ?? 0);

                $subtotal += ($qty * $cost);
                $discountTotal += $discount;
            }

            $grandTotal = $subtotal - $discountTotal;

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'purchase_no' => $validated['purchase_no'],
                'purchase_date' => $validated['purchase_date'],
                'status' => PurchaseStatus::Draft,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => 0.00,
                'grand_total' => $grandTotal,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $userId ?? auth()->id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity'];
                $cost = (float) $itemData['unit_cost'];
                $discount = (float) ($itemData['discount'] ?? 0);
                $lineTotal = ($qty * $cost) - $discount;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ]);
            }

            return $purchase;
        });
    }
}
