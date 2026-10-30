<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Customer;

class ArchiveCustomer
{
    /**
     * Archive or delete a customer.
     * Customers with transaction history (sales, payments, or ledger entries)
     * are soft-deleted/archived to preserve ledger integrity and history.
     * Customers with no transaction history may be removed completely.
     *
     * @return array{archived: bool, message: string}
     */
    public function handle(Customer $customer): array
    {
        $hasHistory = $customer->sales()->exists()
            || $customer->payments()->exists()
            || $customer->ledgerEntries()->exists();

        if ($hasHistory) {
            $customer->delete(); // Soft-deletes because Customer uses SoftDeletes

            return [
                'archived' => true,
                'message' => __('sales.customer_archived', ['name' => $customer->name]),
            ];
        }

        $customer->forceDelete();

        return [
            'archived' => false,
            'message' => __('common.deleted_success'),
        ];
    }
}
