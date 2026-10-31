<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Customer;

final class QueryCustomerDetails
{
    public function handle(Customer $customer): array
    {
        $customer->load(['sales' => fn ($query) => $query->orderByDesc('created_at')->limit(20)]);

        return [
            'ledgerEntries' => $customer->ledgerEntries()->orderByDesc('created_at')->orderByDesc('id')->paginate(15, ['*'], 'ledger_page'),
            'openSales' => $customer->openSales()->get(),
            'balance' => $customer->ledgerBalance(),
            'headroom' => $customer->creditLimitHeadroom(),
            'reminderLogs' => $customer->reminderLogs()->orderByDesc('created_at')->limit(10)->get(),
        ];
    }
}
