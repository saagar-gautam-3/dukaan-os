<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Verification',
            self::Verified => 'Verified',
            self::Failed => 'Failed',
        };
    }
}
