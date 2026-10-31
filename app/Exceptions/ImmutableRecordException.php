<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class ImmutableRecordException extends InvalidArgumentException
{
    public static function forSale(int $saleId, string $status, ?string $message = null): self
    {
        return new self($message ?? __('sales.immutable_exception', [
            'id' => $saleId,
            'status' => __("sales.status_{$status}"),
        ]));
    }

    public static function forPurchase(int $purchaseId, string $status, ?string $message = null): self
    {
        return new self($message ?? __('purchases.immutable_exception', [
            'id' => $purchaseId,
            'status' => __("purchases.status_{$status}"),
        ]));
    }
}
