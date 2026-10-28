<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;

class CashSessionPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'cash.view');
    }

    public function view(User $user, CashSession $session): bool
    {
        return $this->checkTenantAndPermission($user, $session, 'cash.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'cash.manage');
    }

    public function close(User $user, CashSession $session): bool
    {
        return $this->checkTenantAndPermission($user, $session, 'cash.manage');
    }
}
