<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PlatformAuditEvent;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => str_repeat('a', 64),
            'jwt.ttl' => 900,
            'app.url' => 'http://localhost',
        ]);
    }

    public function test_public_plan_list_only_contains_active_plans(): void
    {
        $this->createPlan('base', true);
        $this->createPlan('archived', false);

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'base')
            ->assertJsonMissingPath('data.0.is_active');
    }

    public function test_company_admin_cannot_access_platform_plan_management(): void
    {
        $user = $this->createUser();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/platform/plans')
            ->assertForbidden();
    }

    public function test_platform_admin_can_create_plans_and_update_contract_terms(): void
    {
        $admin = $this->createUser();
        \DB::table('platform_admins')->insert([
            'user_id' => $admin->id,
            'granted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = ['Authorization' => 'Bearer '.$this->tokenFor($admin)];
        $this->withHeaders($headers)
            ->postJson('/api/v1/platform/plans', $this->planData())
            ->assertCreated()
            ->assertJsonPath('data.code', 'comercio')
            ->assertJsonPath('data.terms_version', 1);

        $plan = \App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan::query()
            ->where('code', 'comercio')
            ->firstOrFail();
        $updated = $this->planData();
        $updated['monthly_amount'] = '12900.00';

        $this->withHeaders($headers)
            ->putJson("/api/v1/platform/plans/{$plan->id}", $updated)
            ->assertOk()
            ->assertJsonPath('data.monthly_amount', '12900.00')
            ->assertJsonPath('data.terms_version', 2);

        $this->assertSame(2, PlatformAuditEvent::query()->count());
    }

    public function test_plan_update_rejects_non_peso_currencies_and_invalid_limits(): void
    {
        $admin = $this->createUser();
        \DB::table('platform_admins')->insert([
            'user_id' => $admin->id,
            'granted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = $this->planData();
        $data['currency'] = 'USD';
        $data['limits']['users'] = 0;

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/platform/plans', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency', 'limits.users']);
    }

    public function test_platform_admin_can_be_granted_only_to_an_existing_account_from_cli(): void
    {
        $user = $this->createUser();

        $this->artisan('platform-admin:set', ['email' => $user->email])
            ->assertSuccessful();

        $this->assertDatabaseHas('platform_admins', ['user_id' => $user->id]);
        $this->artisan('platform-admin:set', [
            'email' => $user->email,
            '--revoke' => true,
        ])->assertSuccessful();
        $this->assertDatabaseMissing('platform_admins', ['user_id' => $user->id]);
    }

    private function createPlan(string $code, bool $active): void
    {
        \App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan::query()->create([
            ...$this->planData(),
            'code' => $code,
            'is_active' => $active,
        ]);
    }

    private function planData(): array
    {
        return [
            'code' => 'comercio',
            'name' => 'Comercio',
            'description' => 'Plan para comercios pequeños',
            'benefits' => ['POS', 'Inventario'],
            'currency' => 'ARS',
            'monthly_amount' => '9900.00',
            'annual_amount' => '99000.00',
            'limits' => [
                'users' => 3,
                'branches' => 1,
                'cash_registers' => 1,
                'products' => 500,
            ],
            'entitlements' => [
                'pos' => true,
                'reports' => true,
            ],
            'trial_days' => 0,
            'grace_days' => 5,
            'is_active' => true,
        ];
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Plataforma',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('secure-test-password'),
        ]);
    }

    private function tokenFor(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss' => config('app.url'),
            'sub' => (string) $user->id,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 900,
            'jti' => (string) Str::uuid(),
            'ver' => (int) $user->token_version,
        ], config('jwt.secret'), 'HS256');
    }
}
