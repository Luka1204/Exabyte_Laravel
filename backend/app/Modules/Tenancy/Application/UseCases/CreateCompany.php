<?php

namespace App\Modules\Tenancy\Application\UseCases;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;

class CreateCompany
{
    public function execute(User $owner, string $name): Company
    {
        return DB::transaction(function () use ($owner, $name): Company {
            $company = Company::query()->create(['name' => trim($name)]);

            $company->memberships()->attach($owner->getKey(), [
                'role' => 'owner',
            ]);

            return $company;
        });
    }
}
