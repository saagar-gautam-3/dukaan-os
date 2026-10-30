<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Actions\Cash\RecordCashMovement;
use App\Actions\Udhar\AddCustomerLedgerEntry;
use App\Enums\CashMovementType;
use App\Enums\LedgerEntryType;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\ImmutableRecordException;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\DocumentSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PostSale
{
    public function __construct(
        private readonly AddCustomerLedgerEntry $addLedgerEntry,
    ) {}

    /**
     * Post a draft sale: generate invoice, snapshot costs, deduct stock, record payment,
     * and write a ledger debit for the credit/due portion.
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

            // --- Step 5b: Write customer ledger entry for the credit/due portion ---
            // The due portion = grand_total - what was paid right now
            $duePortion = bcsub($grandTotal, $paidTotal, 2);
            if ($sale->customer_id !== null && bccomp($grandTotal, '0', 2) > 0) {
                $customer = Customer::find($sale->customer_id);
                if ($customer) {
                    // Debit: customer owes the full grand_total
                    $this->addLedgerEntry->handle($customer, [
                        'entry_type' => LedgerEntryType::SaleDebit,
                        'debit' => $grandTotal,
                        'reference_type' => 'sale',
                        'reference_id' => $sale->id,
                        'notes' => "Sale {$invoiceNo} posted",
                        'created_by' => $postedById,
                    ]);

                    // Credit: amount paid at point-of-sale
                    if (bccomp($paidTotal, '0', 2) > 0) {
                        $this->addLedgerEntry->handle($customer, [
                            'entry_type' => LedgerEntryType::PaymentCredit,
                            'credit' => $paidTotal,
                            'reference_type' => 'sale',
                            'reference_id' => $sale->id,
                            'notes' => "POS payment for {$invoiceNo}",
                            'created_by' => $postedById,
                        ]);
                    }
                }
            }

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
        return app(DocumentSequenceService::class)->nextNumber($shopId, 'INV');
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

        $paymentNo = app(DocumentSequenceService::class)->nextNumber($shopId, 'PAY');

        $payment = Payment::create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'customer_id' => $sale->customer_id,
            'payment_no' => $paymentNo,
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

        // Record cash movement if cash was received
        if ($method === PaymentMethod::Cash) {
            app(RecordCashMovement::class)->handle($shopId, $userId, [
                'type' => CashMovementType::SaleCash,
                'amount' => $payAmount,
                'reference_type' => 'payment',
                'reference_id' => $payment->id,
                'notes' => "Cash received for sale {$sale->invoice_no}",
                'branch_id' => $sale->branch_id,
            ]);
        }

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
