<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('The initial administrator can only be seeded locally or in tests.');
        }

        $password = env('ERP_INITIAL_ADMIN_PASSWORD');

        if (! is_string($password) || mb_strlen($password) < 16) {
            throw new RuntimeException(
                'Set ERP_INITIAL_ADMIN_PASSWORD to a unique password of at least 16 characters.'
            );
        }

        $name = (string) env('ERP_INITIAL_ADMIN_NAME', 'Exabyte Demo');
        $email = (string) env('ERP_INITIAL_ADMIN_EMAIL', 'demo@exabyte.local');
        $companyName = (string) env('ERP_INITIAL_COMPANY_NAME', 'Exabyte Demo');

        DB::transaction(function () use ($name, $email, $password, $companyName): void {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                ]
            );

            $company = Company::query()->firstOrCreate(['name' => $companyName]);
            $membership = DB::table('company_memberships')
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($membership) {
                DB::table('company_memberships')
                    ->where('company_id', $company->id)
                    ->where('user_id', $user->id)
                    ->update(['role' => 'owner', 'updated_at' => now()]);

                return;
            }

            DB::table('company_memberships')->insert([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
