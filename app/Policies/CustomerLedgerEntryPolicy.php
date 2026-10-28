<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerLedgerEntry;
use App\Models\User;

class CustomerLedgerEntryPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'udhar.view') || $this->checkPermission($user, 'customers.view');
    }

    public function view(User $user, CustomerLedgerEntry $entry): bool
    {
        return $this->checkTenantAndPermission($user, $entry, 'udhar.view')
            || $this->checkTenantAndPermission($user, $entry, 'customers.view');
    }
}
