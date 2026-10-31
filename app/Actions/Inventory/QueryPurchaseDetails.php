<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Purchase;

final class QueryPurchaseDetails
{
    public function handle(Purchase $purchase): Purchase
    {
        return $purchase->load(['supplier', 'warehouse', 'createdBy', 'items.product.unit']);
    }
}
