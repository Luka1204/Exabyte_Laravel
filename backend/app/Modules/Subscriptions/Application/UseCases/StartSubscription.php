<?php

namespace App\Modules\Subscriptions\Application\UseCases;

use App\Models\User;
use App\Modules\Subscriptions\Domain\Contracts\MercadoPagoGateway;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoNotConfigured;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PaymentAttempt;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class StartSubscription
{
    public function __construct(private MercadoPagoGateway $mercadoPago) {}

    public function execute(
        int $companyId,
        User $billingUser,
        int $planId,
        string $billingPeriod
    ): Subscription {
        [$subscription, $attempt, $request] = DB::transaction(function () use (
            $companyId,
            $billingUser,
            $planId,
            $billingPeriod
        ): array {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $plan = Plan::query()
                ->where('is_active', true)
                ->find($planId);
            if (! $plan) {
                throw new NotFoundHttpException('El plan no está disponible.');
            }

            $existing = Subscription::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['pending', 'trialing', 'active', 'past_due', 'grace'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->status !== 'pending') {
                    throw new ConflictHttpException(
                        'La empresa ya tiene una suscripción activa. El cambio de plan todavía no está disponible.'
                    );
                }

                if (
                    $existing->plan_id !== $plan->id
                    || $existing->billing_period !== $billingPeriod
                    || $existing->billing_user_id !== $billingUser->id
                ) {
                    throw new ConflictHttpException(
                        'Ya existe una contratación pendiente para esta empresa. Continuá o cancelá ese proceso antes de cambiar el plan.'
                    );
                }

                $attempt = $existing->paymentAttempts()
                    ->latest('attempt_number')
                    ->first();
                if ($attempt?->status === 'failed') {
                    $terms = $existing->terms_snapshot;
                    $attempt = $existing->paymentAttempts()->create([
                        'attempt_number' => ((int) $existing->paymentAttempts()
                            ->max('attempt_number')) + 1,
                        'kind' => 'initial',
                        'status' => 'pending',
                        'amount' => $terms['amount'],
                        'currency' => $terms['currency'],
                        'external_reference' => $existing->external_reference,
                    ]);
                }

                return [$existing, $attempt, null];
            }

            $amount = $billingPeriod === 'annual'
                ? (string) $plan->annual_amount
                : (string) $plan->monthly_amount;
            $externalReference = (string) Str::uuid();
            $terms = [
                'plan_id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'currency' => $plan->currency,
                'billing_period' => $billingPeriod,
                'amount' => $amount,
                'benefits' => $plan->benefits,
                'limits' => $plan->limits,
                'entitlements' => $plan->entitlements,
                'trial_days' => $plan->trial_days,
                'grace_days' => $plan->grace_days,
                'terms_version' => $plan->terms_version,
                'captured_at' => now()->toIso8601String(),
            ];
            $subscription = Subscription::query()->create([
                'company_id' => $companyId,
                'billing_user_id' => $billingUser->id,
                'plan_id' => $plan->id,
                'terms_snapshot' => $terms,
                'billing_period' => $billingPeriod,
                'status' => 'pending',
                'provider' => 'mercadopago',
                'external_reference' => $externalReference,
            ]);
            $attempt = $subscription->paymentAttempts()->create([
                'attempt_number' => 1,
                'kind' => 'initial',
                'status' => 'pending',
                'amount' => $amount,
                'currency' => $plan->currency,
                'external_reference' => $externalReference,
            ]);

            $frequency = $billingPeriod === 'annual' ? 12 : 1;

            return [
                $subscription,
                $attempt,
                [
                    'reason' => $plan->name,
                    'external_reference' => $externalReference,
                    'payer_email' => $billingUser->email,
                    'auto_recurring' => [
                        'frequency' => $frequency,
                        'frequency_type' => 'months',
                        'transaction_amount' => $amount,
                        'currency_id' => $plan->currency,
                    ],
                    'back_url' => (string) config('services.mercadopago.return_url'),
                    'status' => 'pending',
                ],
            ];
        });

        if ($subscription->checkout_url && $subscription->provider_subscription_id) {
            return $subscription;
        }

        try {
            $returnUrl = config('services.mercadopago.return_url');
            if (! is_string($returnUrl) || filter_var($returnUrl, FILTER_VALIDATE_URL) === false) {
                throw new MercadoPagoNotConfigured();
            }

            $existingProviderSubscription = $this->mercadoPago->findPreapprovalByReference(
                $subscription->external_reference
            );
            $checkout = $existingProviderSubscription
                ?? $this->mercadoPago->createPendingPreapproval($request ?? $this->requestFromSnapshot($subscription));

            $subscription->provider_subscription_id = $checkout['id'];
            $subscription->provider_status = $checkout['status'];
            $subscription->checkout_url = $checkout['init_point'];
            $subscription->save();
            $attempt?->update(['provider_status' => $checkout['status']]);

            return $subscription->refresh();
        } catch (Throwable $exception) {
            $attempt?->update([
                'status' => 'failed',
                'provider_data' => ['checkout_request_failed' => true],
            ]);
            Log::warning('Mercado Pago subscription checkout could not be initialized.', [
                'subscription_id' => $subscription->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * @return array{
     *     reason: string,
     *     external_reference: string,
     *     payer_email: string,
     *     auto_recurring: array{frequency: int, frequency_type: string, transaction_amount: string, currency_id: string},
     *     back_url: string,
     *     status: string
     * }
     */
    private function requestFromSnapshot(Subscription $subscription): array
    {
        $terms = $subscription->terms_snapshot;

        return [
            'reason' => $terms['name'],
            'external_reference' => $subscription->external_reference,
            'payer_email' => $subscription->billingUser()->value('email'),
            'auto_recurring' => [
                'frequency' => $subscription->billing_period === 'annual' ? 12 : 1,
                'frequency_type' => 'months',
                'transaction_amount' => $terms['amount'],
                'currency_id' => $terms['currency'],
            ],
            'back_url' => (string) config('services.mercadopago.return_url'),
            'status' => 'pending',
        ];
    }
}
