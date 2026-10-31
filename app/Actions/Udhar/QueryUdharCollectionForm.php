<?php

declare(strict_types=1);

namespace App\Actions\Udhar;

use App\Models\Customer;

final class QueryUdharCollectionForm
{
    public function handle(Customer $customer): array
    {
        return [
            'balance' => $customer->ledgerBalance(),
            'openSales' => $customer->openSales()->get(),
        ];
    }
}
