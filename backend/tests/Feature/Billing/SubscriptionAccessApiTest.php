<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionAccessApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => str_repeat('a', 64),
            'jwt.ttl' => 900,
            'app.url' => 'http://localhost',
            'subscriptions.access_enforcement' => true,
        ]);
    }

    public function test_new_company_requires_a_live_subscription_and_entitlement(): void
    {
        [, $company, $headers] = $this->createOwnedCompany();

        $this->withHeaders($headers)
            ->getJson('/api/v1/categories')
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_required');

        $subscription = $this->createActiveSubscription($company, [
            'catalog' => true,
            'pos' => false,
        ], ['products' => 1]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/categories')
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/sales')
            ->assertForbidden()
            ->assertJsonPath('code', 'entitlement_not_included');

        $this->withHeaders($headers)
            ->postJson('/api/v1/products', [
                'name' => 'Producto inicial',
                'sku' => 'SKU-001',
                'price' => '10.00',
            ])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/v1/products', [
                'name' => 'Producto adicional',
                'sku' => 'SKU-002',
                'price' => '10.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('products');

        $subscription->status = 'grace';
        $subscription->current_period_end = now()->subDay();
        $subscription->grace_ends_at = now()->addDays(3);
        $subscription->save();
        $this->withHeaders($headers)
            ->getJson('/api/v1/categories')
            ->assertOk();

        $subscription->grace_ends_at = now()->subMinute();
        $subscription->save();
        $this->withHeaders($headers)
            ->getJson('/api/v1/categories')
            ->assertStatus(402);

        $this->assertSame('grace', $subscription->refresh()->status);
    }

    public function test_migrated_legacy_company_retains_access_without_a_contract(): void
    {
        [, $company, $headers] = $this->createOwnedCompany();
        DB::table('subscription_access_exemptions')->insert([
            'company_id' => $company->id,
            'reason' => 'legacy_company_before_subscription_enforcement',
            'expires_at' => null,
            'created_at' => now(),
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/categories')
            ->assertOk();
    }

    /**
     * @return array{User, Company, array<string, string>}
     */
    private function createOwnedCompany(): array
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('secure-test-password'),
        ]);
        $company = Company::query()->create(['name' => 'Comercio']);
        $company->memberships()->attach($owner->id, ['role' => 'owner']);

        return [
            $owner,
            $company,
            [
                'Authorization' => 'Bearer '.$this->tokenFor($owner),
                'X-Company-ID' => (string) $company->id,
            ],
        ];
    }

    /**
     * @param array<string, bool> $entitlements
     * @param array<string, int|null> $limits
     */
    private function createActiveSubscription(
        Company $company,
        array $entitlements,
        array $limits
    ): Subscription {
        $ownerId = $company->memberships()->firstOrFail()->id;
        $plan = Plan::query()->create([
            'code' => Str::lower(Str::random(12)),
            'name' => 'Comercio',
            'benefits' => [],
            'currency' => 'ARS',
            'monthly_amount' => '1000.00',
            'annual_amount' => '10000.00',
            'limits' => $limits,
            'entitlements' => $entitlements,
            'trial_days' => 0,
            'grace_days' => 0,
            'is_active' => true,
        ]);

        return Subscription::query()->create([
            'company_id' => $company->id,
            'billing_user_id' => $ownerId,
            'plan_id' => $plan->id,
            'terms_snapshot' => [
                'amount' => '1000.00',
                'currency' => 'ARS',
                'limits' => $limits,
                'entitlements' => $entitlements,
            ],
            'billing_period' => 'monthly',
            'status' => 'active',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'external_reference' => (string) Str::uuid(),
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
