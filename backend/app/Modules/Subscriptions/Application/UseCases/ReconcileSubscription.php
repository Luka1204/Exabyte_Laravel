<?php

namespace App\Modules\Subscriptions\Application\UseCases;

use App\Modules\Subscriptions\Application\Jobs\ProcessMercadoPagoEvent;
use App\Modules\Subscriptions\Domain\Contracts\MercadoPagoGateway;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\ProviderEvent;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReconcileSubscription
{
    public function __construct(private MercadoPagoGateway $mercadoPago) {}

    public function execute(int $companyId, string $preapprovalId): Subscription
    {
        $subscription = Subscription::query()
            ->where('company_id', $companyId)
            ->where('provider', 'mercadopago')
            ->where('provider_subscription_id', $preapprovalId)
            ->first();

        if (! $subscription) {
            throw new NotFoundHttpException('No se encontró la suscripción de Mercado Pago para esta empresa.');
        }

        $providerState = $this->mercadoPago->getPreapproval($preapprovalId);
        if ($providerState['id'] !== $preapprovalId) {
            throw new NotFoundHttpException('La suscripción de Mercado Pago no coincide con el checkout.');
        }

        $subscription->provider_status = $providerState['status'];
        $subscription->save();

        if (strtolower($providerState['status']) === 'authorized') {
            foreach ($this->mercadoPago->findApprovedAuthorizedPaymentIds($preapprovalId) as $invoiceId) {
                $eventKey = 'return-reconcile-invoice-'.$invoiceId;
                $event = ProviderEvent::query()->firstOrCreate(
                    [
                        'provider' => 'mercadopago',
                        'provider_event_id' => $eventKey,
                    ],
                    [
                        'topic' => 'subscription_authorized_payment',
                        'resource_id' => (string) $invoiceId,
                        'payload' => ['date_created' => now()->toIso8601String()],
                        'processing_status' => 'received',
                    ]
                );

                if (in_array($event->processing_status, ['received', 'failed'], true)) {
                    (new ProcessMercadoPagoEvent((int) $event->getKey()))
                        ->handle($this->mercadoPago);
                }
            }
        }

        return $subscription->refresh()
            ->load(['paymentAttempts' => fn ($query) => $query->latest('attempt_number')]);
    }
}
