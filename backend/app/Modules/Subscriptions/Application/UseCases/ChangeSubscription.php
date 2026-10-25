<?php

namespace App\Modules\Subscriptions\Application\UseCases;

use App\Modules\Subscriptions\Domain\Contracts\MercadoPagoGateway;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoRequestFailed;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChangeSubscription
{
    public function __construct(private MercadoPagoGateway $mercadoPago) {}

    public function execute(int $companyId, int $planId, string $billingPeriod): Subscription
    {
        return DB::transaction(function () use ($companyId, $planId, $billingPeriod): Subscription {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $subscription = Subscription::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['pending', 'trialing', 'active', 'past_due', 'grace'])
                ->lockForUpdate()
                ->first();

            if (! $subscription || $subscription->status === 'pending') {
                throw new ConflictHttpException(
                    'No hay un contrato activo que pueda cambiarse. Continuá el checkout pendiente.'
                );
            }

            if (! $subscription->provider_subscription_id) {
                throw new ConflictHttpException(
                    'El proveedor no tiene una suscripción actualizable para este contrato.'
                );
            }

            $plan = Plan::query()
                ->where('is_active', true)
                ->find($planId);
            if (! $plan) {
                throw new NotFoundHttpException('El plan no está disponible.');
            }

            $amount = $billingPeriod === 'annual'
                ? (string) $plan->annual_amount
                : (string) $plan->monthly_amount;
            $providerState = $this->mercadoPago->updatePreapproval(
                $subscription->provider_subscription_id,
                [
                    'reason' => $plan->name,
                    'auto_recurring' => [
                        'frequency' => $billingPeriod === 'annual' ? 12 : 1,
                        'frequency_type' => 'months',
                        'transaction_amount' => $amount,
                        'currency_id' => $plan->currency,
                    ],
                ]
            );

            if (
                $providerState['id'] !== $subscription->provider_subscription_id
                || trim($providerState['status']) === ''
            ) {
                throw new MercadoPagoRequestFailed(
                    new RuntimeException('Mercado Pago did not confirm the requested subscription update.')
                );
            }

            $previousTerms = $subscription->terms_snapshot;
            $history = $subscription->terms_history ?? [];
            $history[] = [
                'terms' => $previousTerms,
                'replaced_at' => now()->toIso8601String(),
            ];

            $subscription->forceFill([
                'plan_id' => $plan->id,
                'billing_period' => $billingPeriod,
                'terms_snapshot' => [
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
                ],
                'terms_history' => $history,
                'provider_status' => $providerState['status'],
            ])->save();

            return $subscription->refresh()->load([
                'paymentAttempts' => fn ($query) => $query->latest('attempt_number'),
            ]);
        });
    }
}
