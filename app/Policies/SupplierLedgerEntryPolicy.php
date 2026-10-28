<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierLedgerEntry;
use App\Models\User;

class SupplierLedgerEntryPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'suppliers.view');
    }

    public function view(User $user, SupplierLedgerEntry $entry): bool
    {
        return $this->checkTenantAndPermission($user, $entry, 'suppliers.view');
    }
}
