<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockBalance;
use App\Models\User;

class StockPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'inventory.view');
    }

    public function view(User $user, StockBalance $balance): bool
    {
        return $this->checkTenantAndPermission($user, $balance, 'inventory.view');
    }

    public function adjust(User $user): bool
    {
        return $this->checkPermission($user, 'inventory.manage');
    }
}
