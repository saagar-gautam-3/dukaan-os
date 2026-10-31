<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\IdempotencyKey;
use App\Models\Sale;
use App\Tenancy\TenantContext;

final class FindIdempotentSale
{
    public function handle(string $key): ?Sale
    {
        $id = IdempotencyKey::where('shop_id', TenantContext::shop()->id)
            ->where('key', $key)->where('action', 'post_sale')->value('result_id');

        return $id === null ? null : Sale::where('shop_id', TenantContext::shop()->id)->findOrFail($id);
    }
}
