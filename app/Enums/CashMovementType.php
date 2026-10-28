<?php

declare(strict_types=1);

namespace App\Enums;

enum CashMovementType: string
{
    case Opening = 'opening';
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';
    case SaleCash = 'sale_cash';
    case ExpenseCash = 'expense_cash';
    case CollectionCash = 'collection_cash';

    public function isInflow(): bool
    {
        return match ($this) {
            self::Opening, self::CashIn, self::SaleCash, self::CollectionCash => true,
            self::CashOut, self::ExpenseCash => false,
        };
    }
}
