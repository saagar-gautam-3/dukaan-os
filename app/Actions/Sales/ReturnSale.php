<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Actions\Udhar\AddCustomerLedgerEntry;
use App\Enums\LedgerEntryType;
use App\Enums\MovementType;
use App\Enums\ReturnStatus;
use App\Enums\SaleStatus;
use App\Exceptions\InvalidReturnException;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ReturnSale
{
    public function __construct(
        private readonly AddCustomerLedgerEntry $addLedgerEntry,
    ) {}

    /**
     * Create a return against a posted sale. Restores stock at original unit_cost.
     *
     * @param  array<array{sale_item_id: int, quantity: string}>  $returnItems
     */
    public function handle(Sale $sale, int $processedById, array $returnItems, string $reason = ''): SaleReturn
    {
        if ($sale->status !== SaleStatus::Posted) {
            throw new InvalidArgumentException('Only posted sales can be returned.');
        }

        if (empty($returnItems)) {
            throw new InvalidArgumentException('At least one item must be specified for return.');
        }

        $shop = TenantContext::shop();

        return DB::transaction(function () use ($sale, $processedById, $returnItems, $reason, $shop) {
            $sale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            // Load original items for validation
            $originalItems = $sale->items()->get()->keyBy('id');

            $returnTotal = '0.00';
            $itemsToCreate = [];
            $warehouseId = $sale->warehouse_id ?? $this->defaultWarehouseId($shop->id);

            foreach ($returnItems as $returnItem) {
                $saleItemId = $returnItem['sale_item_id'];
                $returnQty = (string) $returnItem['quantity'];

                $originalItem = $originalItems->get($saleItemId);

                if (! $originalItem) {
                    throw new InvalidArgumentException("Sale item #{$saleItemId} not found on this sale.");
                }

                // Validate return quantity doesn't exceed original
                $alreadyReturned = $originalItem->saleReturnItems()->sum('quantity');
                $maxReturnable = bcsub((string) $originalItem->quantity, (string) $alreadyReturned, 3);

                if (bccomp($returnQty, $maxReturnable, 3) > 0) {
                    throw InvalidReturnException::exceedsQuantity($returnQty, $maxReturnable, $saleItemId);
                }

                $unitCost = (string) $originalItem->unit_cost; // COGS snapshot from original sale
                $refundAmount = bcmul((string) $originalItem->unit_price, $returnQty, 2);

                $returnTotal = bcadd($returnTotal, $refundAmount, 2);

                $itemsToCreate[] = [
                    'sale_item_id' => $saleItemId,
                    'quantity' => $returnQty,
                    'unit_cost' => $unitCost,
                    'refund_amount' => $refundAmount,
                    'shop_id' => $shop->id,
                ];

                // Restore stock if product tracks stock
                if ($originalItem->product->track_stock) {
                    $balance = StockBalance::where('shop_id', $shop->id)
                        ->where('warehouse_id', $warehouseId)
                        ->where('product_id', $originalItem->product_id)
                        ->lockForUpdate()
                        ->first();

                    if ($balance) {
                        $balance->quantity = bcadd((string) $balance->quantity, $returnQty, 3);
                        $balance->save();
                    }

                    // Write reversal stock movement
                    StockMovement::create([
                        'shop_id' => $shop->id,
                        'warehouse_id' => $warehouseId,
                        'product_id' => $originalItem->product_id,
                        'movement_type' => MovementType::SaleReturn->value,
                        'quantity' => $returnQty, // positive = inbound
                        'unit_cost' => $unitCost,
                        'reference_type' => 'sale',
                        'reference_id' => $sale->id,
                        'notes' => "Return for sale {$sale->invoice_no}",
                        'created_by' => $processedById,
                    ]);
                }
            }

            // Generate return number
            $returnNo = $this->generateReturnNumber($shop->id);

            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'return_no' => $returnNo,
                'status' => ReturnStatus::Completed,
                'total' => $returnTotal,
                'reason' => $reason,
                'processed_at' => now(),
                'processed_by' => $processedById,
                'shop_id' => $shop->id,
            ]);

            // Create return items
            foreach ($itemsToCreate as &$item) {
                $item['sale_return_id'] = $saleReturn->id;
            }

            $saleReturn->items()->createMany($itemsToCreate);

            // Write ledger credit for the return amount if sale has a customer
            if ($sale->customer_id !== null && bccomp($returnTotal, '0', 2) > 0) {
                $customer = $sale->customer;
                if ($customer) {
                    $this->addLedgerEntry->handle($customer, [
                        'entry_type' => LedgerEntryType::ReturnCredit,
                        'credit' => $returnTotal,
                        'reference_type' => 'sale_return',
                        'reference_id' => $saleReturn->id,
                        'notes' => "Return {$saleReturn->return_no} for sale {$sale->invoice_no}",
                        'created_by' => $processedById,
                    ]);
                }
            }

            return $saleReturn->load('items.saleItem');
        });
    }

    private function generateReturnNumber(int $shopId): string
    {
        $count = SaleReturn::where('shop_id', $shopId)->count();

        return sprintf('RET-%06d', $count + 1);
    }

    private function defaultWarehouseId(int $shopId): int
    {
        $warehouse = Warehouse::where('shop_id', $shopId)->where('is_default', true)->first()
            ?? Warehouse::where('shop_id', $shopId)->first();

        if (! $warehouse) {
            throw new RuntimeException('No warehouse configured for this shop.');
        }

        return $warehouse->id;
    }
}
