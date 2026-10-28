<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class ImmutableRecordException extends InvalidArgumentException
{
    public static function forSale(int $saleId, string $status, ?string $message = null): self
    {
        return new self($message ?? "Sale #{$saleId} is {$status} and cannot be cancelled. Record is immutable; create a return instead.");
    }

    public static function forPurchase(int $purchaseId, string $status, ?string $message = null): self
    {
        return new self($message ?? "Purchase #{$purchaseId} is {$status} and cannot be modified or deleted. Purchase is already posted and cannot be re-posted.");
    }
}
