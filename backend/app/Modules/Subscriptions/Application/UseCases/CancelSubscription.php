<?php

namespace App\Modules\Subscriptions\Application\UseCases;

use App\Modules\Subscriptions\Domain\Contracts\MercadoPagoGateway;
use App\Modules\Subscriptions\Domain\SubscriptionLifecycle;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PlatformAuditEvent;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelSubscription
{
    public function __construct(private MercadoPagoGateway $mercadoPago) {}

    public function execute(int $companyId, int $actorId): ?Subscription
    {
        $subscription = Subscription::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'trialing', 'active', 'past_due', 'grace'])
            ->latest('id')
            ->first();

        if (! $subscription) {
            return null;
        }

        if ($subscription->provider_subscription_id) {
            $providerState = $this->mercadoPago->cancelPreapproval(
                $subscription->provider_subscription_id
            );

            if ($providerState['status'] !== 'cancelled') {
                throw new ConflictHttpException(
                    'Mercado Pago todavía no confirmó la cancelación.'
                );
            }

            $subscription->provider_status = $providerState['status'];
        }

        DB::transaction(function () use ($subscription, $companyId, $actorId): void {
            $subscription = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();
            (new SubscriptionLifecycle())->transition($subscription, 'canceled');
            $subscription->cancel_at_period_end = false;
            $subscription->canceled_at = now();
            $subscription->save();

            PlatformAuditEvent::query()->create([
                'actor_user_id' => $actorId,
                'action' => 'subscription.canceled',
                'subject_type' => Subscription::class,
                'subject_id' => (string) $subscription->id,
                'details' => ['company_id' => $companyId, 'policy' => 'immediate'],
            ]);
        });

        return $subscription->refresh();
    }
}
