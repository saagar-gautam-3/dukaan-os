<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function isImmutable(): bool
    {
        return $this === self::Posted;
    }
}
