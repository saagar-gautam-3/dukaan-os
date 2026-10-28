<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerEntryType: string
{
    // Customer ledger entry types
    case SaleDebit = 'sale_debit';           // credit sale creates debt
    case PaymentCredit = 'payment_credit';   // payment reduces debt
    case ReturnCredit = 'return_credit';     // return reduces debt
    case AdvanceCredit = 'advance_credit';   // advance payment (unallocated)
    case ManualDebit = 'manual_debit';
    case ManualCredit = 'manual_credit';

    // Supplier ledger entry types
    case PurchaseDebit = 'purchase_debit';   // purchase creates payable
    case SupplierPaymentCredit = 'supplier_payment_credit'; // payment to supplier

    public function label(): string
    {
        return match ($this) {
            self::SaleDebit => 'Sale (Credit)',
            self::PaymentCredit => 'Payment Received',
            self::ReturnCredit => 'Sale Return',
            self::AdvanceCredit => 'Advance Payment',
            self::ManualDebit => 'Manual Debit',
            self::ManualCredit => 'Manual Credit',
            self::PurchaseDebit => 'Purchase',
            self::SupplierPaymentCredit => 'Payment to Supplier',
        };
    }
}
