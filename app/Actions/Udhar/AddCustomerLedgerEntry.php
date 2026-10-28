<?php

declare(strict_types=1);

namespace App\Actions\Udhar;

use App\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Tenancy\TenantContext;

/**
 * Append a single entry to the customer ledger.
 * NEVER call update/delete on ledger entries.
 */
class AddCustomerLedgerEntry
{
    /**
     * @param  array{
     *     entry_type: LedgerEntryType,
     *     debit?: string,
     *     credit?: string,
     *     reference_type?: string|null,
     *     reference_id?: int|null,
     *     notes?: string|null,
     *     created_by?: int|null,
     * }  $data
     */
    public function handle(Customer $customer, array $data): CustomerLedgerEntry
    {
        $shop = TenantContext::shop();

        return CustomerLedgerEntry::create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'entry_type' => $data['entry_type']->value,
            'debit' => $data['debit'] ?? '0.00',
            'credit' => $data['credit'] ?? '0.00',
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }
}
