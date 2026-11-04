<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class UpdateSupplier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data) {
            $supplier->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'pan_vat' => $data['pan_vat'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            return $supplier;
        });
    }
}
