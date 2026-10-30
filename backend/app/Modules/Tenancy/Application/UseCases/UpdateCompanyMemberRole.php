<?php

namespace App\Modules\Tenancy\Application\UseCases;

use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCompanyMemberRole
{
    public function execute(
        Company $company,
        int $userId,
        ?string $role,
        ?bool $isActive = null,
        ?array $permissions = null
    ): void {
        DB::transaction(function () use ($company, $userId, $role, $isActive, $permissions): void {
            $membership = DB::table('company_memberships')
                ->where('company_id', $company->getKey())
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $membership) {
                throw (new ModelNotFoundException)->setModel(
                    'App\\Models\\User',
                    [$userId]
                );
            }

            if (
                $membership->role === 'owner'
                && ($role !== null || $isActive === false || $permissions !== null)
            ) {
                throw ValidationException::withMessages([
                    'member' => ['La membresía owner no se puede cambiar ni desactivar desde esta operación.'],
                ]);
            }

            $changes = ['updated_at' => now()];
            if ($role !== null) {
                $changes['role'] = $role;
            }
            if ($isActive !== null) {
                $changes['is_active'] = $isActive;
            }
            if ($permissions !== null) {
                $changes['permissions'] = json_encode($permissions);
            }

            DB::table('company_memberships')
                ->where('company_id', $company->getKey())
                ->where('user_id', $userId)
                ->update($changes);
        }, attempts: 3);
    }
}
