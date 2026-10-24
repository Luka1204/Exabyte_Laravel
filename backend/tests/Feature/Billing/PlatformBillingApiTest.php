<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PaymentAttempt;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformBillingApiTest extends TestCase
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

    public function test_platform_billing_data_is_only_available_to_platform_admins(): void
    {
        $company = Company::query()->create(['name' => 'Almacén']);
        $subscription = $this->createSubscription($company, 'active', '12000.00', 'annual');
        PaymentAttempt::query()->create([
            'subscription_id' => $subscription->id,
            'attempt_number' => 1,
            'kind' => 'initial',
            'status' => 'paid',
            'amount' => '12000.00',
            'currency' => 'ARS',
            'provider_payment_id' => 'provider-payment-1',
            'provider_status' => 'approved',
            'paid_at' => now(),
        ]);
        PaymentAttempt::query()->create([
            'subscription_id' => $subscription->id,
            'attempt_number' => 2,
            'kind' => 'renewal',
            'status' => 'failed',
            'amount' => '12000.00',
            'currency' => 'ARS',
        ]);

        $user = $this->createUser();
        $headers = ['Authorization' => 'Bearer '.$this->tokenFor($user)];

        $this->withHeaders($headers)
            ->getJson('/api/v1/platform/subscriptions')
            ->assertForbidden();
        $this->withHeaders($headers)
            ->getJson('/api/v1/platform/payments')
            ->assertForbidden();
        $this->withHeaders($headers)
            ->getJson('/api/v1/platform/metrics')
            ->assertForbidden();

        DB::table('platform_admins')->insert([
            'user_id' => $user->id,
            'granted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/platform/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.0.company.name', 'Almacén')
            ->assertJsonPath('data.0.payment_attempts.0.status', 'failed');

        $this->withHeaders($headers)
            ->getJson('/api/v1/platform/payments')
            ->assertOk()
            ->assertJsonMissingPath('data.0.provider_data');

        $this->withHeaders($headers)
            ->getJson('/api/v1/platform/metrics')
            ->assertOk()
            ->assertJsonPath('data.registered_companies', 1)
            ->assertJsonPath('data.companies_with_active_subscriptions', 1)
            ->assertJsonPath('data.active_subscriptions', 1)
            ->assertJsonPath('data.failed_renewals', 1)
            ->assertJsonPath('data.collected_revenue_ars', '12000.00')
            ->assertJsonPath('data.estimated_monthly_recurring_revenue_ars', '1000.00');
    }

    private function createSubscription(
        Company $company,
        string $status,
        string $amount,
        string $period
    ): Subscription {
        $plan = Plan::query()->create([
            'code' => Str::lower(Str::random(12)),
            'name' => 'Comercio',
            'benefits' => [],
            'currency' => 'ARS',
            'monthly_amount' => '1000.00',
            'annual_amount' => '12000.00',
            'limits' => ['users' => 1, 'branches' => 1, 'cash_registers' => 1],
            'entitlements' => ['pos' => true],
            'trial_days' => 0,
            'grace_days' => 0,
            'is_active' => true,
        ]);
        $owner = $this->createUser();

        return Subscription::query()->create([
            'company_id' => $company->id,
            'billing_user_id' => $owner->id,
            'plan_id' => $plan->id,
            'terms_snapshot' => [
                'amount' => $amount,
                'currency' => 'ARS',
                'limits' => ['users' => 1],
                'entitlements' => ['pos' => true],
            ],
            'billing_period' => $period,
            'status' => $status,
            'external_reference' => (string) Str::uuid(),
        ]);
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
