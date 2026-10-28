<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Qr = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank Transfer',
            self::Qr => 'QR / eSewa / Khalti',
        };
    }

    public function defaultStatus(): PaymentStatus
    {
        return match ($this) {
            self::Qr => PaymentStatus::Pending,
            default => PaymentStatus::Verified,
        };
    }
}
