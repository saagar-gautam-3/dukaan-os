<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Shop;
use App\Services\DocumentSequenceService;

class ResolveProductSku
{
    public function __construct(protected DocumentSequenceService $sequenceService) {}

    public function execute(Shop $shop, ?string $requestedSku = null): ?string
    {
        $auto = (bool) ($shop->settings['sku_auto_generate'] ?? true);

        if ($auto) {
            return $this->sequenceService->nextNumber($shop->id, 'SKU', '0000');
        }

        $sku = $requestedSku !== null ? trim($requestedSku) : '';

        return $sku === '' ? null : $sku;
    }
}
