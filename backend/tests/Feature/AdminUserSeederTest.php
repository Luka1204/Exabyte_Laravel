<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_user_is_created_as_company_owner_and_seeding_is_idempotent(): void
    {
        $this->setInitialAdminPassword('local-only-password-1234');

        try {
            $seeder = new AdminUserSeeder;
            $seeder->run();
            $seeder->run();

            $user = User::query()->where('email', 'demo@exabyte.local')->firstOrFail();
            $company = Company::query()->where('name', 'Exabyte Demo')->firstOrFail();

            $this->assertSame('Exabyte Demo', $user->name);
            $this->assertTrue(Hash::check('local-only-password-1234', $user->password));
            $this->assertDatabaseHas('company_memberships', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ]);
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('companies', 1);
            $this->postJson('/api/v1/auth/login', [
                'email' => 'demo@exabyte.local',
                'password' => 'local-only-password-1234',
            ])
                ->assertOk()
                ->assertJsonPath('user.id', $user->id)
                ->assertJsonStructure(['access_token', 'refresh_token']);
        } finally {
            $this->clearInitialAdminPassword();
        }
    }

    public function test_initial_user_seeder_rejects_a_missing_password(): void
    {
        $this->clearInitialAdminPassword();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Set ERP_INITIAL_ADMIN_PASSWORD');

        (new AdminUserSeeder)->run();
    }

    private function setInitialAdminPassword(string $password): void
    {
        putenv("ERP_INITIAL_ADMIN_PASSWORD={$password}");
        $_ENV['ERP_INITIAL_ADMIN_PASSWORD'] = $password;
        $_SERVER['ERP_INITIAL_ADMIN_PASSWORD'] = $password;
    }

    private function clearInitialAdminPassword(): void
    {
        putenv('ERP_INITIAL_ADMIN_PASSWORD');
        unset($_ENV['ERP_INITIAL_ADMIN_PASSWORD'], $_SERVER['ERP_INITIAL_ADMIN_PASSWORD']);
    }
}
