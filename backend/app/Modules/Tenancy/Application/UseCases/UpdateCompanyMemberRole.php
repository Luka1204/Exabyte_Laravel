<?php

namespace App\Modules\Tenancy\Application\UseCases;

use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCompanyMemberRole
{
    public function execute(Company $company, int $userId, string $role): void
    {
        DB::transaction(function () use ($company, $userId, $role): void {
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

            if ($membership->role === 'owner') {
                throw ValidationException::withMessages([
                    'role' => ['La membresía owner no se puede cambiar desde esta operación.'],
                ]);
            }

            DB::table('company_memberships')
                ->where('company_id', $company->getKey())
                ->where('user_id', $userId)
                ->update([
                    'role' => $role,
                    'updated_at' => now(),
                ]);
        }, attempts: 3);
    }
}
