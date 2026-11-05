<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Shop;
use App\Services\DocumentSequenceService;
use Illuminate\Validation\ValidationException;

class ResolveProductSku
{
    public function __construct(protected DocumentSequenceService $sequenceService) {}

    public function execute(Shop $shop, ?string $requestedSku = null): ?string
    {
        $auto = (bool) ($shop->settings['sku_auto_generate'] ?? true);

        if ($auto) {
            $raw = $this->sequenceService->nextNumber($shop->id, 'SKU', '0000');
            // nextNumber returns PREFIX-FISCALYEAR-000000; keep only the numeric tail.
            $parts = explode('-', $raw);
            $num = end($parts);

            return 'SKU-'.str_pad((string) (int) $num, 6, '0', STR_PAD_LEFT);
        }

        $sku = $requestedSku !== null ? trim($requestedSku) : '';
        if ($sku === '') {
            throw ValidationException::withMessages([
                'sku' => [__('products.sku_required')],
            ]);
        }

        return $sku;
    }
}
