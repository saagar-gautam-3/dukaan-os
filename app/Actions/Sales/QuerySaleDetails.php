<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Sale;

final class QuerySaleDetails
{
    public function show(Sale $sale): Sale
    {
        return $sale->load(['items.product.unit', 'customer', 'paymentAllocations.payment', 'returns.items']);
    }

    public function returnForm(Sale $sale): Sale
    {
        return $sale->load(['items.product.unit']);
    }

    public function invoice(Sale $sale): Sale
    {
        return $sale->load(['items.product.unit', 'customer', 'shop']);
    }
}
