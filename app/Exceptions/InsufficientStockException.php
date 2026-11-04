<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class InsufficientStockException extends InvalidArgumentException
{
    public static function forProduct(int $productId, string $productName, string $available, string $requested): self
    {
        return new self(__('errors.insufficient_stock_exception', [
            'id' => $productId,
            'name' => $productName,
            'available' => $available,
            'requested' => $requested,
        ]));
    }
}
