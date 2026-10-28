<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'products.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'products.manage');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->checkPermission($user, 'products.manage');
    }
}
