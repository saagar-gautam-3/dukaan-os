<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Customer;
use App\Tenancy\TenantContext;

class CreateCustomer
{
    /**
     * Create a new customer for the current shop.
     *
     * @param  array{
     *     name: string,
     *     phone?: string|null,
     *     email?: string|null,
     *     address?: string|null,
     *     pan_no?: string|null,
     *     credit_limit?: string|null,
     * }  $data
     */
    public function handle(array $data): Customer
    {
        $shop = TenantContext::shop();

        return Customer::create(array_merge($data, [
            'shop_id' => $shop->id,
        ]));
    }
}
