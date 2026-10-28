<?php

declare(strict_types=1);

namespace App\Actions\Udhar;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Collection flow: receive money from a customer, allocate to oldest-due invoices first.
 * Leftover becomes an unallocated advance (credit) entry in the ledger.
 *
 * This action CREATES a Payment record, writes PaymentAllocations, updates sale paid_total,
 * and appends CustomerLedgerEntry records — all in one transaction.
 */
class CollectPayment
{
    public function __construct(
        private readonly AddCustomerLedgerEntry $addLedgerEntry,
    ) {}

    /**
     * @param  array{
     *     amount: string,
     *     method: string,
     *     reference_number?: string|null,
     *     received_at?: string|null,
     *     notes?: string|null,
     *     allocations?: array<int, string>,   // [sale_id => amount_to_allocate] (user-editable, optional)
     * }  $data
     * @return array{payment: Payment, allocated: array<int, string>, advance: string}
     */
    public function handle(Customer $customer, int $userId, array $data): array
    {
        $shop = TenantContext::shop();
        $amount = (string) $data['amount'];

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Collection amount must be greater than zero.');
        }

        return DB::transaction(function () use ($customer, $userId, $shop, $amount, $data) {
            $method = PaymentMethod::from($data['method']);

            // Generate payment number
            $paymentNo = 'PAY-'.sprintf('%06d', Payment::withoutGlobalScopes()->where('shop_id', $shop->id)->count() + 1);

            $payment = Payment::create([
                'shop_id' => $shop->id,
                'user_id' => $userId,
                'customer_id' => $customer->id,
                'payment_no' => $paymentNo,
                'method' => $method->value,
                'status' => PaymentStatus::Verified->value,
                'amount' => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $remaining = $amount;
            $allocated = [];

            // Determine allocation order
            if (! empty($data['allocations'])) {
                // User-specified allocations
                $allocationMap = $data['allocations'];
                $saleIds = array_keys($allocationMap);

                $sales = Sale::where('shop_id', $shop->id)
                    ->where('customer_id', $customer->id)
                    ->where('status', SaleStatus::Posted->value)
                    ->whereIn('id', $saleIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($allocationMap as $saleId => $allocationAmount) {
                    $allocationAmount = (string) $allocationAmount;
                    if (bccomp($allocationAmount, '0', 2) <= 0) {
                        continue;
                    }

                    /** @var Sale|null $sale */
                    $sale = $sales->get($saleId);
                    if (! $sale) {
                        continue;
                    }

                    $due = $sale->dueAmount();
                    $toAllocate = bccomp($allocationAmount, $due, 2) > 0 ? $due : $allocationAmount;
                    $toAllocate = bccomp($toAllocate, $remaining, 2) > 0 ? $remaining : $toAllocate;

                    if (bccomp($toAllocate, '0', 2) <= 0) {
                        continue;
                    }

                    $payment->allocations()->create([
                        'shop_id' => $shop->id,
                        'sale_id' => $sale->id,
                        'amount' => $toAllocate,
                    ]);

                    $sale->update(['paid_total' => bcadd((string) $sale->paid_total, $toAllocate, 2)]);

                    // Ledger: credit entry for this payment allocation against the sale
                    $this->addLedgerEntry->handle($customer, [
                        'entry_type' => LedgerEntryType::PaymentCredit,
                        'credit' => $toAllocate,
                        'reference_type' => 'sale',
                        'reference_id' => $sale->id,
                        'notes' => "Payment {$paymentNo} allocated to invoice {$sale->invoice_no}",
                        'created_by' => $userId,
                    ]);

                    $allocated[$saleId] = $toAllocate;
                    $remaining = bcsub($remaining, $toAllocate, 2);

                    if (bccomp($remaining, '0', 2) <= 0) {
                        break;
                    }
                }
            } else {
                // Auto: allocate oldest-due first
                $openSales = Sale::where('shop_id', $shop->id)
                    ->where('customer_id', $customer->id)
                    ->where('status', SaleStatus::Posted->value)
                    ->whereRaw('grand_total > paid_total')
                    ->orderBy('posted_at')
                    ->lockForUpdate()
                    ->get();

                foreach ($openSales as $sale) {
                    if (bccomp($remaining, '0', 2) <= 0) {
                        break;
                    }

                    $due = $sale->dueAmount();
                    $toAllocate = bccomp($remaining, $due, 2) >= 0 ? $due : $remaining;

                    $payment->allocations()->create([
                        'shop_id' => $shop->id,
                        'sale_id' => $sale->id,
                        'amount' => $toAllocate,
                    ]);

                    $sale->update(['paid_total' => bcadd((string) $sale->paid_total, $toAllocate, 2)]);

                    $this->addLedgerEntry->handle($customer, [
                        'entry_type' => LedgerEntryType::PaymentCredit,
                        'credit' => $toAllocate,
                        'reference_type' => 'sale',
                        'reference_id' => $sale->id,
                        'notes' => "Payment {$paymentNo} allocated to invoice {$sale->invoice_no}",
                        'created_by' => $userId,
                    ]);

                    $allocated[$sale->id] = $toAllocate;
                    $remaining = bcsub($remaining, $toAllocate, 2);
                }
            }

            // Leftover = advance credit
            $advance = $remaining;
            if (bccomp($advance, '0', 2) > 0) {
                $this->addLedgerEntry->handle($customer, [
                    'entry_type' => LedgerEntryType::AdvanceCredit,
                    'credit' => $advance,
                    'reference_type' => 'payment',
                    'reference_id' => $payment->id,
                    'notes' => "Advance payment (unallocated) {$paymentNo}",
                    'created_by' => $userId,
                ]);
            }

            return [
                'payment' => $payment->refresh()->load('allocations'),
                'allocated' => $allocated,
                'advance' => $advance,
            ];
        });
    }
}
