<?php

namespace App\Policies;

use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user, int $companyId): bool
    {
        return $user->hasCompanyPermission($companyId, 'catalog.view');
    }

    public function create(User $user, int $companyId): bool
    {
        return $user->hasCompanyPermission($companyId, 'catalog.manage');
    }

    public function update(User $user, int $companyId): bool
    {
        return $this->create($user, $companyId);
    }
}
