<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidReturnException extends InvalidArgumentException
{
    public static function exceedsQuantity(string $requested, string $maxReturnable, int $itemId): self
    {
        return new self("Return quantity {$requested} exceeds returnable quantity {$maxReturnable} for item #{$itemId}.");
    }
}
