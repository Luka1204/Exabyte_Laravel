<?php

namespace App\Policies;

use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user, int $companyId): bool
    {
        return $user->companies()
            ->whereKey($companyId)
            ->exists();
    }

    public function create(User $user, int $companyId): bool
    {
        return $user->companies()
            ->whereKey($companyId)
            ->wherePivotIn('role', ['owner', 'manager'])
            ->exists();
    }
}
