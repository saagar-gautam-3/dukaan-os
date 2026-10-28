<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Customer;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;

class SearchPosCustomers
{
    /**
     * @return Collection<int, Customer>
     */
    public function handle(string $query): Collection
    {
        $shop = TenantContext::shop();

        return Customer::where('shop_id', $shop->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'phone', 'credit_limit'])
            ->map(function (Customer $customer) {
                $customer->balance = $customer->ledgerBalance();
                $customer->credit_headroom = $customer->creditLimitHeadroom();

                return $customer;
            });
    }
}
