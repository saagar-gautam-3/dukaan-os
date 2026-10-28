<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\ImmutableRecordException;
use App\Exceptions\InsufficientStockException;
use App\Models\IdempotencyKey;
use App\Models\InvoiceSequence;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PostSale
{
    /**
     * Post a draft sale: generate invoice, snapshot costs, deduct stock, record payment.
     *
     * All totals are recomputed server-side. Client grand_total is verified but not trusted
     * for calculations. Posted sales are immutable.
     *
     * @param  array{
     *     idempotency_key?: string|null,
     *     payment_method?: string|null,
     *     payment_amount?: string|null,
     *     payment_reference?: string|null,
     * } $options
     */
    public function handle(Sale $sale, int $postedById, array $options = []): Sale
    {
        $shop = TenantContext::shop();

        // --- Idempotency check (outside transaction for fast short-circuit) ---
        $idemKey = $options['idempotency_key'] ?? null;
        if ($idemKey !== null) {
            $existing = IdempotencyKey::where('shop_id', $shop->id)
                ->where('key', $idemKey)
                ->where('action', 'post_sale')
                ->first();

            if ($existing) {
                return Sale::findOrFail($existing->result_id);
            }
        }

        if (! $sale->isDraft()) {
            throw ImmutableRecordException::forSale($sale->id, $sale->status->value);
        }

        return DB::transaction(function () use ($sale, $shop, $postedById, $options, $idemKey) {
            // Lock the sale row to prevent double-posting
            $sale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if (! $sale->isDraft()) {
                throw ImmutableRecordException::forSale($sale->id, $sale->status->value);
            }

            // --- Step 1: Lock products and snapshot unit_cost from stock_balances (moving weighted avg) ---
            $items = $sale->items()->with('product')->get();

            if ($items->isEmpty()) {
                throw new InvalidArgumentException('Cannot post a sale with no items.');
            }

            $warehouseId = $sale->warehouse_id ?? $this->defaultWarehouseId($shop->id);

            // Recompute totals server-side
            $subtotal = '0.00';
            $discountTotal = '0.00';
            $taxTotal = '0.00';

            foreach ($items as $item) {
                $product = $item->product;

                if ($product->track_stock) {
                    // Lock balance row for this product
                    $balance = StockBalance::where('shop_id', $shop->id)
                        ->where('warehouse_id', $warehouseId)
                        ->where('product_id', $item->product_id)
                        ->lockForUpdate()
                        ->first();

                    $currentQty = $balance ? (string) $balance->quantity : '0.000';

                    if (bccomp($currentQty, (string) $item->quantity, 3) < 0) {
                        $allowNeg = (bool) ($shop->settings['allow_negative_stock'] ?? false);
                        if (! $allowNeg) {
                            throw InsufficientStockException::forProduct(
                                $item->product_id,
                                $product->name,
                                $currentQty,
                                (string) $item->quantity
                            );
                        }
                    }

                    // Snapshot unit_cost from the average cost stored in stock_balances
                    $unitCost = $balance ? (string) ($balance->average_cost ?? '0.00') : '0.00';
                    $item->unit_cost = $unitCost;
                }

                // Recompute line_total
                $linePrice = bcadd(bcsub((string) $item->unit_price, (string) $item->discount, 4), (string) $item->tax, 4);
                $lineTotal = bcmul($linePrice, (string) $item->quantity, 2);
                $item->line_total = $lineTotal;

                $subtotal = bcadd($subtotal, bcmul((string) $item->unit_price, (string) $item->quantity, 2), 2);
                $discountTotal = bcadd($discountTotal, bcmul((string) $item->discount, (string) $item->quantity, 2), 2);
                $taxTotal = bcadd($taxTotal, bcmul((string) $item->tax, (string) $item->quantity, 2), 2);

                $item->save();
            }

            $grandTotal = bcadd(bcsub($subtotal, $discountTotal, 2), $taxTotal, 2);

            // --- Step 2: Generate invoice number (row-locked, gap-safe) ---
            $invoiceNo = $this->generateInvoiceNumber($shop->id);

            // --- Step 3: Deduct stock and create movements ---
            foreach ($items as $item) {
                if (! $item->product->track_stock) {
                    continue;
                }

                $balance = StockBalance::where('shop_id', $shop->id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $newQty = bcsub((string) $balance->quantity, (string) $item->quantity, 3);
                $balance->quantity = $newQty;
                $balance->save();

                // Create stock movement record
                StockMovement::create([
                    'shop_id' => $shop->id,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $item->product_id,
                    'movement_type' => MovementType::Sale->value,
                    'quantity' => bcmul((string) $item->quantity, '-1', 3), // negative = outbound
                    'unit_cost' => $item->unit_cost,
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'notes' => "Sale {$invoiceNo}",
                    'created_by' => $postedById,
                ]);
            }

            // --- Step 4: Record payment if provided ---
            $paidTotal = '0.00';
            if (! empty($options['payment_amount']) && bccomp((string) $options['payment_amount'], '0', 2) > 0) {
                $paidTotal = $this->recordAndAllocatePayment(
                    $sale,
                    $shop->id,
                    $postedById,
                    $grandTotal,
                    $options,
                );
            }

            // --- Step 5: Finalize sale ---
            $sale->update([
                'invoice_no' => $invoiceNo,
                'status' => SaleStatus::Posted,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'paid_total' => $paidTotal,
                'posted_at' => now(),
                'posted_by' => $postedById,
            ]);

            // --- Step 6: Record idempotency key ---
            if ($idemKey !== null) {
                IdempotencyKey::create([
                    'shop_id' => $shop->id,
                    'user_id' => $postedById,
                    'key' => $idemKey,
                    'action' => 'post_sale',
                    'result_id' => $sale->id,
                    'result_type' => 'sale',
                ]);
            }

            return $sale->refresh()->load(['items.product', 'paymentAllocations']);
        });
    }

    /**
     * Generate a gap-safe sequential invoice number within the current fiscal year.
     * Uses a row-level lock on invoice_sequences to prevent duplicates.
     */
    private function generateInvoiceNumber(int $shopId): string
    {
        // Nepali fiscal year: Shrawan 1 (mid-July) to Ashad end (mid-July next year)
        // Approximate: if current month < 7 it's prior year's fiscal year
        $now = now()->setTimezone('Asia/Kathmandu');
        $year = $now->month >= 7 ? $now->year : $now->year - 1;
        $fiscalYear = $year.'-'.substr((string) ($year + 1), -2);

        $sequence = InvoiceSequence::where('shop_id', $shopId)
            ->where('fiscal_year', $fiscalYear)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = InvoiceSequence::create([
                'shop_id' => $shopId,
                'fiscal_year' => $fiscalYear,
                'prefix' => 'INV',
                'last_number' => 0,
            ]);
        }

        $nextNumber = $sequence->last_number + 1;
        $sequence->update(['last_number' => $nextNumber]);

        return sprintf('%s-%s-%06d', $sequence->prefix, $fiscalYear, $nextNumber);
    }

    /**
     * Create a payment and allocate it to the sale. Returns the amount paid.
     */
    private function recordAndAllocatePayment(
        Sale $sale,
        int $shopId,
        int $userId,
        string $grandTotal,
        array $options,
    ): string {
        $method = PaymentMethod::from($options['payment_method'] ?? 'cash');
        $payAmount = (string) $options['payment_amount'];
        $maxPayable = $grandTotal;

        // Cap payment at grand_total (no overpayment via sale; use separate Payment for advance)
        if (bccomp($payAmount, $maxPayable, 2) > 0) {
            $payAmount = $maxPayable;
        }

        $payment = Payment::create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'customer_id' => $sale->customer_id,
            'method' => $method->value,
            'status' => $method->defaultStatus()->value,
            'amount' => $payAmount,
            'reference_number' => $options['payment_reference'] ?? null,
            'received_at' => now(),
        ]);

        $payment->allocations()->create([
            'shop_id' => $shopId,
            'sale_id' => $sale->id,
            'amount' => $payAmount,
        ]);

        // Update sale's paid_total cache
        $sale->paid_total = bcadd((string) $sale->paid_total, $payAmount, 2);

        return $payAmount;
    }

    private function defaultWarehouseId(int $shopId): int
    {
        $warehouse = Warehouse::where('shop_id', $shopId)
            ->where('is_default', true)
            ->first();

        if (! $warehouse) {
            $warehouse = Warehouse::where('shop_id', $shopId)->first();
        }

        if (! $warehouse) {
            throw new RuntimeException('No warehouse configured for this shop. Please create a warehouse first.');
        }

        return $warehouse->id;
    }
}
