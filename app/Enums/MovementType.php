<?php

declare(strict_types=1);

namespace App\Enums;

enum MovementType: string
{
    case PurchaseReceive = 'purchase_receive';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Opening = 'opening';
    case Reversal = 'reversal';

    public function isInflow(): bool
    {
        return in_array($this, [
            self::PurchaseReceive,
            self::SaleReturn,
            self::AdjustmentIn,
            self::TransferIn,
            self::Opening,
        ], true);
    }
}
