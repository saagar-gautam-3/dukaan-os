<?php

declare(strict_types=1);

namespace App\Actions\Cash;

use App\Models\CashSession;

final class QueryCashSessions
{
    public function index(): mixed
    {
        return CashSession::with(['openedBy', 'closedBy'])->orderByDesc('opened_at')->paginate(20);
    }
}
