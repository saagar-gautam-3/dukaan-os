<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isImmutable(): bool
    {
        return $this === self::Posted || $this === self::Cancelled;
    }
}
