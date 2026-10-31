<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Warehouse;
use App\Tenancy\TenantContext;

final class ResolvePosWarehouse
{
    public function handle(): ?Warehouse
    {
        $shopId = TenantContext::shop()->id;

        return Warehouse::where('shop_id', $shopId)->where('is_default', true)->first()
            ?? Warehouse::where('shop_id', $shopId)->first();
    }
}
