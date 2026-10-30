<?php

namespace App\Policies;

use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user, int $companyId): bool
    {
        return $user->hasCompanyPermission($companyId, 'inventory.view');
    }

    public function create(User $user, int $companyId): bool
    {
        return $user->hasCompanyPermission($companyId, 'inventory.manage');
    }
}
