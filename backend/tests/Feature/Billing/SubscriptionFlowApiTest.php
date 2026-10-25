<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Subscriptions\Application\Jobs\ProcessMercadoPagoEvent;
use App\Modules\Subscriptions\Application\UseCases\StartSubscription;
use App\Modules\Subscriptions\Domain\Contracts\MercadoPagoGateway;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoRequestFailed;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\ProviderEvent;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SubscriptionFlowApiTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-signature-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => str_repeat('a', 64),
            'jwt.ttl' => 900,
            'app.url' => 'http://localhost',
            'services.mercadopago.return_url' => 'https://app.example.test/billing/return',
            'services.mercadopago.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    public function test_company_owner_can_start_an_idempotent_server_priced_checkout(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $plan = $this->createPlan();
        $gateway = Mockery::mock(MercadoPagoGateway::class);
        $gateway->shouldReceive('findPreapprovalByReference')
            ->once()
            ->with(Mockery::on(fn (string $reference): bool => Str::isUuid($reference)))
            ->andReturn(null);
        $gateway->shouldReceive('createPendingPreapproval')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => (
                $payload['auto_recurring']['transaction_amount'] === '9900.00'
                && $payload['auto_recurring']['currency_id'] === 'ARS'
                && $payload['auto_recurring']['frequency'] === 1
                && $payload['status'] === 'pending'
            )))
            ->andReturn([
                'id' => 'mp-subscription-100',
                'init_point' => 'https://www.mercadopago.com.ar/subscriptions/checkout/test',
                'status' => 'pending',
            ]);
        $this->app->instance(MercadoPagoGateway::class, $gateway);

        $headers = ['Authorization' => 'Bearer '.$this->tokenFor($owner)];
        $payload = [
            'plan_id' => $plan->id,
            'billing_period' => 'monthly',
        ];

        $this->withHeaders($headers)
            ->postJson("/api/v1/companies/{$company->id}/subscription", $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('checkout_url', 'https://www.mercadopago.com.ar/subscriptions/checkout/test');

        $this->withHeaders($headers)
            ->postJson("/api/v1/companies/{$company->id}/subscription", $payload)
            ->assertOk()
            ->assertJsonPath('data.provider_subscription_id', 'mp-subscription-100');

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $subscription = Subscription::query()->firstOrFail();
        $this->assertSame('9900.00', $subscription->terms_snapshot['amount']);
        $this->assertSame(3, $subscription->terms_snapshot['limits']['users']);
    }

    public function test_starting_a_subscription_requires_the_company_owner(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);
        $plan = $this->createPlan();

        $this->withToken($this->tokenFor($member))
            ->postJson("/api/v1/companies/{$company->id}/subscription", [
                'plan_id' => $plan->id,
                'billing_period' => 'monthly',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_company_owner_can_change_the_existing_provider_subscription_with_server_priced_terms(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $currentPlan = $this->createPlan();
        $targetPlan = $this->createPlan('comercio-plus');
        $targetPlan->update([
            'name' => 'Comercio Plus',
            'monthly_amount' => '12500.00',
            'annual_amount' => '125000.00',
            'limits' => ['users' => 8, 'branches' => 2, 'cash_registers' => 2, 'products' => 1200],
            'entitlements' => ['pos' => true, 'sales_reports' => true],
        ]);
        $oldTerms = [
            'plan_id' => $currentPlan->id,
            'name' => 'Comercio',
            'currency' => 'ARS',
            'billing_period' => 'monthly',
            'amount' => '9900.00',
            'limits' => ['users' => 3],
        ];
        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'billing_user_id' => $owner->id,
            'plan_id' => $currentPlan->id,
            'terms_snapshot' => $oldTerms,
            'billing_period' => 'monthly',
            'status' => 'active',
            'provider' => 'mercadopago',
            'provider_status' => 'authorized',
            'provider_subscription_id' => 'mp-subscription-existing',
            'external_reference' => (string) Str::uuid(),
        ]);
        $subscription->paymentAttempts()->create([
            'attempt_number' => 1,
            'kind' => 'initial',
            'status' => 'paid',
            'amount' => '9900.00',
            'currency' => 'ARS',
            'provider_payment_id' => 'payment-original',
        ]);

        $gateway = Mockery::mock(MercadoPagoGateway::class);
        $gateway->shouldReceive('updatePreapproval')
            ->once()
            ->with('mp-subscription-existing', [
                'reason' => 'Comercio Plus',
                'auto_recurring' => [
                    'frequency' => 12,
                    'frequency_type' => 'months',
                    'transaction_amount' => '125000.00',
                    'currency_id' => 'ARS',
                ],
            ])
            ->andReturn(['id' => 'mp-subscription-existing', 'status' => 'authorized']);
        $this->app->instance(MercadoPagoGateway::class, $gateway);

        $this->withToken($this->tokenFor($owner))
            ->putJson("/api/v1/companies/{$company->id}/subscription", [
                'plan_id' => $targetPlan->id,
                'billing_period' => 'annual',
                'amount' => '0.01',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.plan_id', $targetPlan->id)
            ->assertJsonPath('data.billing_period', 'annual')
            ->assertJsonPath('data.terms_snapshot.amount', '125000.00')
            ->assertJsonPath('data.provider_subscription_id', 'mp-subscription-existing');

        $changed = $subscription->fresh();
        $this->assertSame($oldTerms, $changed->terms_history[0]['terms']);
        $this->assertSame('125000.00', $changed->terms_snapshot['amount']);
        $this->assertSame('annual', $changed->terms_snapshot['billing_period']);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $subscription->paymentAttempts()->value('id'),
            'status' => 'paid',
            'amount' => '9900.00',
            'provider_payment_id' => 'payment-original',
        ]);
    }

    public function test_only_the_company_owner_can_change_a_subscription_and_provider_is_not_called_for_members(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);
        $plan = $this->createPlan();
        $subscription = $this->createActiveSubscription($owner, $company, $plan);
        $gateway = Mockery::mock(MercadoPagoGateway::class);
        $gateway->shouldNotReceive('updatePreapproval');
        $this->app->instance(MercadoPagoGateway::class, $gateway);

        $this->withToken($this->tokenFor($member))
            ->putJson("/api/v1/companies/{$company->id}/subscription", [
                'plan_id' => $plan->id,
                'billing_period' => 'annual',
            ])
            ->assertForbidden();

        $this->assertSame('monthly', $subscription->fresh()->billing_period);
    }

    public function test_invalid_or_unavailable_change_choices_are_rejected_before_provider_call(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $plan = $this->createPlan();
        $subscription = $this->createActiveSubscription($owner, $company, $plan);
        $unavailable = $this->createPlan('unavailable');
        $unavailable->update(['is_active' => false]);
        $gateway = Mockery::mock(MercadoPagoGateway::class);
        $gateway->shouldNotReceive('updatePreapproval');
        $this->app->instance(MercadoPagoGateway::class, $gateway);
        $url = "/api/v1/companies/{$company->id}/subscription";
        $headers = ['Authorization' => 'Bearer '.$this->tokenFor($owner)];

        $this->withHeaders($headers)
            ->putJson($url, ['plan_id' => 999999, 'billing_period' => 'monthly'])
            ->assertUnprocessable();
        $this->withHeaders($headers)
            ->putJson($url, ['plan_id' => $plan->id, 'billing_period' => 'weekly'])
            ->assertUnprocessable();
        $this->withHeaders($headers)
            ->putJson($url, ['plan_id' => $unavailable->id, 'billing_period' => 'annual'])
            ->assertNotFound();

        $this->assertSame($plan->id, $subscription->fresh()->plan_id);
        $this->assertSame('monthly', $subscription->fresh()->billing_period);
    }

    public function test_provider_failure_leaves_existing_contract_terms_and_payment_history_unchanged(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $plan = $this->createPlan();
        $targetPlan = $this->createPlan('comercio-plus');
        $subscription = $this->createActiveSubscription($owner, $company, $plan);
        $subscription->forceFill([
            'terms_history' => [['terms' => ['name' => 'Primer contrato'], 'replaced_at' => '2026-01-01T00:00:00+00:00']],
        ])->save();
        $attempt = $subscription->paymentAttempts()->create([
            'attempt_number' => 1,
            'kind' => 'initial',
            'status' => 'paid',
            'amount' => '9900.00',
            'currency' => 'ARS',
            'provider_payment_id' => 'payment-original',
        ]);
        $beforeSnapshot = $subscription->fresh()->terms_snapshot;
        $beforeHistory = $subscription->fresh()->terms_history;
        $beforeAttempt = $attempt->fresh()->getAttributes();
        $gateway = Mockery::mock(MercadoPagoGateway::class);
        $gateway->shouldReceive('updatePreapproval')
            ->once()
            ->andThrow(new MercadoPagoRequestFailed(new RuntimeException('provider temporarily unavailable')));
        $this->app->instance(MercadoPagoGateway::class, $gateway);

        $this->withToken($this->tokenFor($owner))
            ->putJson("/api/v1/companies/{$company->id}/subscription", [
                'plan_id' => $targetPlan->id,
                'billing_period' => 'annual',
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Mercado Pago no pudo actualizar la suscripción. El contrato local no fue modificado.');

        $unchanged = $subscription->fresh();
        $this->assertSame($plan->id, $unchanged->plan_id);
        $this->assertSame('monthly', $unchanged->billing_period);
        $this->assertSame($beforeSnapshot, $unchanged->terms_snapshot);
        $this->assertSame($beforeHistory, $unchanged->terms_history);
        $this->assertSame($beforeAttempt, $attempt->fresh()->getAttributes());
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_retry_after_checkout_initialization_failure_keeps_contract_and_attempt_history(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $plan = $this->createPlan();
        $gateway = Mockery::mock(MercadoPagoGateway::class);
        $gateway->shouldReceive('findPreapprovalByReference')
            ->twice()
            ->andReturn(null, null);

        $checkoutCall = 0;
        $gateway->shouldReceive('createPendingPreapproval')
            ->twice()
            ->andReturnUsing(function () use (&$checkoutCall): array {
                $checkoutCall++;
                if ($checkoutCall === 1) {
                    throw new MercadoPagoRequestFailed(new RuntimeException('temporary provider failure'));
                }

                return [
                    'id' => 'mp-subscription-retry',
                    'init_point' => 'https://www.mercadopago.com.ar/subscriptions/checkout/retry',
                    'status' => 'pending',
                ];
            });
        $this->app->instance(MercadoPagoGateway::class, $gateway);

        $headers = ['Authorization' => 'Bearer '.$this->tokenFor($owner)];
        $payload = ['plan_id' => $plan->id, 'billing_period' => 'monthly'];
        $url = "/api/v1/companies/{$company->id}/subscription";

        $this->withHeaders($headers)
            ->postJson($url, $payload)
            ->assertStatus(502);

        $this->withHeaders($headers)
            ->postJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://www.mercadopago.com.ar/subscriptions/checkout/retry');

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertDatabaseHas('payment_attempts', [
            'attempt_number' => 1,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'attempt_number' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_subscription_webhook_requires_valid_signature_and_deduplicates_events(): void
    {
        Queue::fake();
        $dataId = 'mp-subscription-100';
        $requestId = 'request-abc';
        $eventId = 'event-200';
        $url = '/api/v1/payments/mercadopago/webhook?data.id='.$dataId;
        $headers = $this->signedHeaders($dataId, $requestId);
        $payload = [
            'id' => $eventId,
            'type' => 'subscription_preapproval',
            'action' => 'updated',
            'date_created' => now()->toIso8601String(),
            'data' => ['id' => $dataId],
        ];

        $this->postJson($url, $payload, $headers)
            ->assertOk()
            ->assertJsonPath('duplicate', false);
        Queue::assertPushed(ProcessMercadoPagoEvent::class, 1);

        $this->postJson($url, $payload, $headers)
            ->assertOk()
            ->assertJsonPath('duplicate', true);
        Queue::assertPushed(ProcessMercadoPagoEvent::class, 2);
        $this->assertDatabaseCount('provider_events', 1);

        $this->postJson($url, [
            ...$payload,
            'id' => 'event-invalid-signature',
        ], ['x-request-id' => $requestId, 'x-signature' => 'ts=1,v1=invalid'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('provider_events', 1);
    }

    public function test_only_provider_confirmed_approved_invoice_activates_subscription(): void
    {
        $subscription = $this->createPendingSubscription();
        $gateway = Mockery::mock(MercadoPagoGateway::class);
        $gateway->shouldReceive('getPreapproval')
            ->twice()
            ->with('mp-subscription-100')
            ->andReturn([
                'id' => 'mp-subscription-100',
                'status' => 'authorized',
                'next_payment_date' => now()->addMonth()->toIso8601String(),
            ], [
                'id' => 'mp-subscription-100',
                'status' => 'authorized',
                'next_payment_date' => now()->addMonth()->toIso8601String(),
            ]);

        $preapprovalEvent = $this->createProviderEvent(
            'event-preapproval',
            'subscription_preapproval',
            'mp-subscription-100',
            now()->subMinute()->toIso8601String()
        );
        (new ProcessMercadoPagoEvent($preapprovalEvent->id))->handle($gateway);
        $this->assertSame('pending', $subscription->fresh()->status);

        $gateway->shouldReceive('getAuthorizedPayment')
            ->once()
            ->with(555)
            ->andReturn([
                'id' => '555',
                'preapproval_id' => 'mp-subscription-100',
                'status' => 'processed',
                'amount' => '9900.00',
                'currency' => 'ARS',
                'payment_id' => 'mp-payment-900',
                'payment_status' => 'approved',
                'payment_status_detail' => 'accredited',
                'next_payment_date' => null,
            ]);
        $paymentEvent = $this->createProviderEvent(
            'event-invoice',
            'subscription_authorized_payment',
            '555',
            now()->toIso8601String()
        );

        (new ProcessMercadoPagoEvent($paymentEvent->id))->handle($gateway);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertDatabaseHas('payment_attempts', [
            'subscription_id' => $subscription->id,
            'provider_payment_id' => 'mp-payment-900',
            'status' => 'paid',
        ]);

        (new ProcessMercadoPagoEvent($paymentEvent->id))->handle($gateway);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    private function signedHeaders(string $dataId, string $requestId): array
    {
        $timestamp = (string) time();
        $manifest = "id:".strtolower($dataId).";request-id:{$requestId};ts:{$timestamp};";
        $signature = hash_hmac('sha256', $manifest, self::WEBHOOK_SECRET);

        return [
            'x-request-id' => $requestId,
            'x-signature' => "ts={$timestamp},v1={$signature}",
        ];
    }

    private function createProviderEvent(
        string $eventId,
        string $topic,
        string $resourceId,
        string $dateCreated
    ): ProviderEvent {
        return ProviderEvent::query()->create([
            'provider' => 'mercadopago',
            'provider_event_id' => $eventId,
            'topic' => $topic,
            'resource_id' => $resourceId,
            'payload' => ['date_created' => $dateCreated],
            'processing_status' => 'received',
        ]);
    }

    private function createPendingSubscription(): Subscription
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $plan = $this->createPlan();

        return Subscription::query()->create([
            'company_id' => $company->id,
            'billing_user_id' => $owner->id,
            'plan_id' => $plan->id,
            'terms_snapshot' => [
                'currency' => 'ARS',
                'amount' => '9900.00',
                'grace_days' => 5,
            ],
            'billing_period' => 'monthly',
            'status' => 'pending',
            'provider' => 'mercadopago',
            'provider_subscription_id' => 'mp-subscription-100',
            'external_reference' => (string) Str::uuid(),
        ]);
    }

    private function createPlan(string $code = 'comercio'): Plan
    {
        return Plan::query()->create([
            'code' => $code,
            'name' => 'Comercio',
            'description' => 'Plan comercial',
            'benefits' => ['POS'],
            'currency' => 'ARS',
            'monthly_amount' => '9900.00',
            'annual_amount' => '99000.00',
            'limits' => [
                'users' => 3,
                'branches' => 1,
                'cash_registers' => 1,
                'products' => 500,
            ],
            'entitlements' => ['pos' => true],
            'trial_days' => 0,
            'grace_days' => 5,
            'is_active' => true,
        ]);
    }

    private function createActiveSubscription(User $owner, Company $company, Plan $plan): Subscription
    {
        return Subscription::query()->create([
            'company_id' => $company->id,
            'billing_user_id' => $owner->id,
            'plan_id' => $plan->id,
            'terms_snapshot' => [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'currency' => $plan->currency,
                'billing_period' => 'monthly',
                'amount' => '9900.00',
            ],
            'terms_history' => [],
            'billing_period' => 'monthly',
            'status' => 'active',
            'provider' => 'mercadopago',
            'provider_status' => 'authorized',
            'provider_subscription_id' => 'mp-subscription-existing',
            'external_reference' => (string) Str::uuid(),
        ]);
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Comercio',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('secure-test-password'),
        ]);
    }

    private function createCompany(User $owner): Company
    {
        $company = Company::query()->create(['name' => 'Comercio de prueba']);
        $company->memberships()->attach($owner->id, ['role' => 'owner']);

        return $company;
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
