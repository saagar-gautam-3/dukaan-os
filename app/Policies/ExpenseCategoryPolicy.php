<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'expenses.view');
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $this->checkTenantAndPermission($user, $category, 'expenses.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'expenses.manage');
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $this->checkTenantAndPermission($user, $category, 'expenses.manage');
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $this->checkTenantAndPermission($user, $category, 'expenses.manage');
    }
}
