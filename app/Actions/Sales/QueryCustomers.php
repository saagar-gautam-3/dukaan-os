<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Customer;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QueryCustomers
{
    public function handle(?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        $shop = TenantContext::shop();
        $query = Customer::where('shop_id', $shop->id)->orderBy('name');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->withCount('sales')->paginate($perPage)->withQueryString();
    }
}
