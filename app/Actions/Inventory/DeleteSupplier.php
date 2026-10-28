<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class DeleteSupplier
{
    public function execute(Supplier $supplier): void
    {
        DB::transaction(function () use ($supplier) {
            $supplier->delete();
        });
    }
}
