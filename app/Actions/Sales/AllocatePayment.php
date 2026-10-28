<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Actions\Udhar\AddCustomerLedgerEntry;
use App\Enums\LedgerEntryType;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AllocatePayment
{
    public function __construct(
        private readonly AddCustomerLedgerEntry $addLedgerEntry,
    ) {}

    /**
     * Allocate (part of) a payment to a specific posted sale.
     * Supports partial allocation and multi-sale allocation.
     */
    public function handle(Payment $payment, int $saleId, string $amount): PaymentAllocation
    {
        $shop = TenantContext::shop();

        $sale = Sale::where('shop_id', $shop->id)->where('id', $saleId)->firstOrFail();

        if (! $sale->isPosted()) {
            throw new InvalidArgumentException("Sale #{$saleId} must be posted before payment can be allocated.");
        }

        return DB::transaction(function () use ($payment, $sale, $amount) {
            // Validate unallocated amount on the payment
            $unallocated = $payment->unallocatedAmount();
            if (bccomp($amount, $unallocated, 2) > 0) {
                throw new InvalidArgumentException(
                    "Cannot allocate {$amount}; payment only has {$unallocated} unallocated."
                );
            }

            // Validate remaining due on the sale
            $saleDue = $sale->dueAmount();
            if (bccomp($amount, $saleDue, 2) > 0) {
                throw new InvalidArgumentException(
                    "Cannot allocate {$amount}; sale only has {$saleDue} due."
                );
            }

            $allocation = PaymentAllocation::create([
                'payment_id' => $payment->id,
                'sale_id' => $sale->id,
                'amount' => $amount,
            ]);

            // Update cached paid_total on sale
            $newPaidTotal = bcadd((string) $sale->paid_total, $amount, 2);
            $sale->update(['paid_total' => $newPaidTotal]);

            // Write ledger credit if sale has a customer
            if ($sale->customer_id !== null) {
                $customer = $sale->customer;
                if ($customer) {
                    $payNo = $payment->payment_no ?: (string) $payment->id;
                    $this->addLedgerEntry->handle($customer, [
                        'entry_type' => LedgerEntryType::PaymentCredit,
                        'credit' => $amount,
                        'reference_type' => 'sale',
                        'reference_id' => $sale->id,
                        'notes' => "Allocation from payment #{$payNo} to invoice {$sale->invoice_no}",
                        'created_by' => $payment->user_id,
                    ]);
                }
            }

            return $allocation;
        });
    }
}
