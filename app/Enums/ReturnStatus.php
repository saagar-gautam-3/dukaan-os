<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
