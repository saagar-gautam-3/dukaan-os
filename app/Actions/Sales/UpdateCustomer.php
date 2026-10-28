<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Customer;

class UpdateCustomer
{
    /**
     * Update an existing customer.
     *
     * @param  array{
     *     name?: string,
     *     phone?: string|null,
     *     email?: string|null,
     *     address?: string|null,
     *     pan_no?: string|null,
     *     credit_limit?: string|null,
     * }  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->fresh();
    }
}
