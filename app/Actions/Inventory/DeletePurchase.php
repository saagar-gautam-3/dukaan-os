<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Exceptions\ImmutableRecordException;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

class DeletePurchase
{
    public function execute(Purchase $purchase): void
    {
        if ($purchase->isPosted()) {
            throw ImmutableRecordException::forPurchase($purchase->id, $purchase->status->value);
        }

        DB::transaction(function () use ($purchase) {
            $purchase->items()->delete();
            $purchase->delete();
        });
    }
}
