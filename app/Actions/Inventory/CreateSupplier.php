<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class CreateSupplier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $openingBalance = $data['opening_balance'] ?? 0.00;

            return Supplier::create([
                'name' => $data['name'],
                'contact_person' => $data['contact_person'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'pan_vat' => $data['pan_vat'] ?? null,
                'address' => $data['address'] ?? null,
                'opening_balance' => $openingBalance,
                'current_balance' => $openingBalance,
            ]);
        });
    }
}
