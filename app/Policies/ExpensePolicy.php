<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->checkTenantAndPermission($user, $expense, 'expenses.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'expenses.create') || $this->checkPermission($user, 'expenses.manage');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->checkTenantAndPermission($user, $expense, 'expenses.manage');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->checkTenantAndPermission($user, $expense, 'expenses.delete')
            || $this->checkTenantAndPermission($user, $expense, 'expenses.manage');
    }
}
