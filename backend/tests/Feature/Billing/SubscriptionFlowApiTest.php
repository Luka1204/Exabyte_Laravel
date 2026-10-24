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

    private function createPlan(): Plan
    {
        return Plan::query()->create([
            'code' => 'comercio',
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
