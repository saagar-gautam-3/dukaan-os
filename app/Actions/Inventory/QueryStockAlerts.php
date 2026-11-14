<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueryStockAlerts
{
    /**
     * @return Collection<int, Product>
     */
    public function execute(): Collection
    {
        $products = Product::with(['unit', 'category', 'stockBalances'])
            ->where('is_active', true)
            ->where('low_stock_threshold', '>', 0)
            ->get()
            ->filter(function (Product $product) {
                $totalStock = $product->stockBalances->reduce(
                    fn (string $sum, $balance): string => Money::add($sum, (string) $balance->quantity, 3),
                    '0.000'
                );

                return Money::compare($totalStock, (string) $product->low_stock_threshold, 3) <= 0;
            })
            ->values();

        foreach ($products as $product) {
            $product->setAttribute('current_stock', $product->stockBalances->reduce(
                fn (string $sum, $balance): string => Money::add($sum, (string) $balance->quantity, 3),
                '0.000'
            ));

            $product->setAttribute('suggested_qty', self::suggestedQuantity(
                (string) $product->low_stock_threshold,
                (string) $product->current_stock,
                (bool) ($product->unit?->is_fractional ?? true)
            ));
        }

        $suppliers = $this->preferredSuppliers($products);

        foreach ($products as $product) {
            $product->setRelation('preferred_supplier', $suppliers[$product->id] ?? null);
        }

        return $products;
    }

    /**
     * Suggested reorder quantity = reorder level x 2 - current quantity,
     * never below 1, rounded to the unit's storage rule (3 decimals for
     * fractional units, whole numbers otherwise).
     */
    public static function suggestedQuantity(string $reorderLevel, string $current, bool $isFractional): string
    {
        $scale = $isFractional ? 3 : 0;
        $raw = Money::subtract(Money::multiply($reorderLevel, '2', $scale), $current, $scale);
        $rounded = Money::round($raw, $scale);

        if (Money::compare($rounded, '1', $scale) < 0) {
            return Money::round('1', $scale);
        }

        return $rounded;
    }

    /**
     * Preferred supplier = supplier of the most recent POSTED purchase
     * containing the product (draft/cancelled purchases are ignored).
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, Supplier|null>
     */
    private function preferredSuppliers(Collection $products): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $productIds = $products->pluck('id')->all();

        $rows = DB::table('purchase_items as pi')
            ->join('purchases as pu', function ($join): void {
                $join->on('pu.id', '=', 'pi.purchase_id')->whereColumn('pu.shop_id', 'pi.shop_id');
            })
            ->where('pi.shop_id', $products->first()->shop_id)
            ->whereIn('pi.product_id', $productIds)
            ->where('pu.status', PurchaseStatus::Posted->value)
            ->whereNotNull('pu.supplier_id')
            ->select('pi.product_id', 'pu.supplier_id', 'pu.posted_at')
            ->orderByDesc('pu.posted_at')
            ->get()
            ->unique('product_id');

        $supplierIds = $rows->pluck('supplier_id')->unique()->values()->all();
        $suppliers = Supplier::whereIn('id', $supplierIds)
            ->where('shop_id', $products->first()->shop_id)
            ->get()
            ->keyBy('id');

        $map = [];
        foreach ($rows as $row) {
            // Rows are newest-first: the first row per product wins.
            $map[$row->product_id] ??= $suppliers[$row->supplier_id] ?? null;
        }

        return $map;
    }
}
