<?php

namespace App\Modules\Tenancy\Application\UseCases;

use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveCompanyMember
{
    public function execute(Company $company, int $userId): void
    {
        DB::transaction(function () use ($company, $userId): void {
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
                    'member' => ['La membresía owner no se puede quitar desde esta operación.'],
                ]);
            }

            DB::table('company_memberships')
                ->where('company_id', $company->getKey())
                ->where('user_id', $userId)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }, attempts: 3);
    }
}
