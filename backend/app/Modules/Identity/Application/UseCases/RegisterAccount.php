<?php

namespace App\Modules\Identity\Application\UseCases;

use App\Models\User;
use App\Modules\Tenancy\Application\UseCases\CreateCompany;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;

class RegisterAccount
{
    public function __construct(private CreateCompany $createCompany) {}

    /**
     * @param array{name: string, email: string, password: string, company_name: string} $data
     * @return array{user: User, company: Company}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'],
            ]);
            $company = $this->createCompany->execute($user, $data['company_name']);

            return ['user' => $user, 'company' => $company];
        });
    }
}
