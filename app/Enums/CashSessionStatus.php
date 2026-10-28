<?php

declare(strict_types=1);

namespace App\Enums;

enum CashSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('cash.status_open'),
            self::Closed => __('cash.status_closed'),
        };
    }
}
