<?php

namespace App\Policies;

use App\Models\User;

class CashManagementPolicy
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
}
