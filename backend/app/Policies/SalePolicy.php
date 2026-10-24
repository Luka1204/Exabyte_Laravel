<?php

namespace App\Policies;

use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user, int $companyId): bool
    {
        return $user->companies()
            ->whereKey($companyId)
            ->exists();
    }

    public function operate(User $user, int $companyId): bool
    {
        return $this->viewAny($user, $companyId);
    }

    public function void(User $user, int $companyId): bool
    {
        return $user->companies()
            ->whereKey($companyId)
            ->wherePivotIn('role', ['owner', 'manager'])
            ->exists();
    }

    public function createReturn(User $user, int $companyId): bool
    {
        return $this->void($user, $companyId);
    }
}
