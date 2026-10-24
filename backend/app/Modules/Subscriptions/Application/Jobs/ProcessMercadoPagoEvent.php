<?php

namespace App\Modules\Subscriptions\Application\Jobs;

use App\Modules\Subscriptions\Domain\Contracts\MercadoPagoGateway;
use App\Modules\Subscriptions\Domain\SubscriptionLifecycle;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PaymentAttempt;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\ProviderEvent;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessMercadoPagoEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public int $providerEventId) {}

    public function handle(MercadoPagoGateway $mercadoPago): void
    {
        $event = DB::transaction(function (): ?ProviderEvent {
            $event = ProviderEvent::query()
                ->lockForUpdate()
                ->findOrFail($this->providerEventId);
            if (in_array($event->processing_status, ['processed', 'ignored', 'processing'], true)) {
                return null;
            }

            $event->processing_status = 'processing';
            $event->failure_code = null;
            $event->save();

            return $event;
        });
        if (! $event) {
            return;
        }

        try {
            match ($event->topic) {
                'subscription_preapproval' => $this->processPreapproval($event, $mercadoPago),
                'subscription_authorized_payment' => $this->processAuthorizedPayment($event, $mercadoPago),
                default => $event->processing_status = 'ignored',
            };

            if ($event->processing_status === 'processing') {
                $event->processing_status = 'processed';
            }
            $event->processed_at = now();
            $event->failure_code = null;
            $event->save();
        } catch (Throwable $exception) {
            $event->processing_status = 'failed';
            $event->failure_code = 'provider_processing_failed';
            $event->save();
            Log::warning('Mercado Pago event processing failed and will be retried.', [
                'provider_event_id' => $event->provider_event_id,
                'topic' => $event->topic,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function processPreapproval(
        ProviderEvent $event,
        MercadoPagoGateway $mercadoPago
    ): void {
        $providerState = $mercadoPago->getPreapproval($event->resource_id);
        $subscription = Subscription::query()
            ->where('provider_subscription_id', $providerState['id'])
            ->first();

        if (! $subscription) {
            $event->processing_status = 'ignored';

            return;
        }

        DB::transaction(function () use ($subscription, $providerState, $event): void {
            $subscription = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($this->isStale($subscription, $event)) {
                $event->processing_status = 'ignored';

                return;
            }

            $subscription->provider_status = $providerState['status'];
            $subscription->last_provider_event_at = $this->eventTime($event);

            if (
                $providerState['status'] === 'cancelled'
                && ! in_array($subscription->status, ['canceled', 'expired'], true)
            ) {
                (new SubscriptionLifecycle())->transition($subscription, 'canceled');
                $subscription->cancel_at_period_end = false;
                $subscription->canceled_at = now();
            }

            $subscription->save();
        });
    }

    private function processAuthorizedPayment(
        ProviderEvent $event,
        MercadoPagoGateway $mercadoPago
    ): void {
        if (! ctype_digit($event->resource_id)) {
            throw new RuntimeException('Mercado Pago authorized payment ID is invalid.');
        }

        $invoice = $mercadoPago->getAuthorizedPayment((int) $event->resource_id);
        $subscription = Subscription::query()
            ->where('provider_subscription_id', $invoice['preapproval_id'])
            ->first();

        if (! $subscription) {
            $event->processing_status = 'ignored';

            return;
        }

        if (! in_array(strtolower((string) $invoice['payment_status']), ['approved', 'rejected'], true)) {
            $event->processing_status = 'ignored';

            return;
        }

        $providerState = $mercadoPago->getPreapproval($invoice['preapproval_id']);
        DB::transaction(function () use (
            $subscription,
            $invoice,
            $providerState,
            $event
        ): void {
            $subscription = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($this->isStale($subscription, $event)) {
                $event->processing_status = 'ignored';

                return;
            }

            if (
                $invoice['currency'] !== $subscription->terms_snapshot['currency']
                || number_format((float) $invoice['amount'], 2, '.', '')
                    !== number_format((float) $subscription->terms_snapshot['amount'], 2, '.', '')
            ) {
                throw new RuntimeException('Mercado Pago invoice does not match the contracted terms.');
            }

            $paymentStatus = strtolower((string) $invoice['payment_status']);
            $attempt = PaymentAttempt::query()
                ->where('provider_invoice_id', $invoice['id'])
                ->first();

            if ($attempt?->status === 'paid') {
                $event->processing_status = 'ignored';

                return;
            }

            if (! $attempt) {
                $attempt = PaymentAttempt::query()->create([
                    'subscription_id' => $subscription->id,
                    'attempt_number' => ((int) $subscription->paymentAttempts()->max('attempt_number')) + 1,
                    'kind' => $subscription->status === 'pending' ? 'initial' : 'renewal',
                    'status' => $paymentStatus === 'approved' ? 'paid' : 'failed',
                    'amount' => $invoice['amount'],
                    'currency' => $invoice['currency'],
                    'provider_invoice_id' => $invoice['id'],
                    'provider_payment_id' => $invoice['payment_id'],
                    'provider_status' => $invoice['status'],
                    'external_reference' => $subscription->external_reference,
                    'provider_data' => [
                        'payment_status' => $paymentStatus,
                        'payment_status_detail' => $invoice['payment_status_detail'],
                    ],
                    'paid_at' => $paymentStatus === 'approved' ? now() : null,
                ]);
            } elseif ($paymentStatus === 'approved' && $attempt->status !== 'paid') {
                $attempt->status = 'paid';
                $attempt->provider_payment_id = $invoice['payment_id'];
                $attempt->provider_status = $invoice['status'];
                $attempt->provider_data = [
                    'payment_status' => $paymentStatus,
                    'payment_status_detail' => $invoice['payment_status_detail'],
                ];
                $attempt->paid_at = now();
                $attempt->save();
            }

            $subscription->last_provider_event_at = $this->eventTime($event);
            if ($paymentStatus === 'approved') {
                if (in_array($subscription->status, ['canceled', 'expired', 'suspended'], true)) {
                    $event->processing_status = 'ignored';

                    return;
                }

                (new SubscriptionLifecycle())->transition($subscription, 'active');
                $subscription->current_period_start = now();
                $subscription->current_period_end = $this->nextPaymentDate(
                    $providerState['next_payment_date'],
                    $subscription->billing_period
                );
                $subscription->grace_ends_at = null;
            } elseif ($paymentStatus === 'rejected') {
                $graceDays = (int) $subscription->terms_snapshot['grace_days'];
                (new SubscriptionLifecycle())->transition(
                    $subscription,
                    $graceDays > 0 ? 'grace' : 'suspended'
                );
                $subscription->grace_ends_at = $graceDays > 0
                    ? now()->addDays($graceDays)
                    : null;
            }

            $subscription->save();
        });
    }

    private function isStale(Subscription $subscription, ProviderEvent $event): bool
    {
        $eventTime = $this->eventTime($event);

        return $subscription->last_provider_event_at !== null
            && $eventTime->lessThanOrEqualTo($subscription->last_provider_event_at);
    }

    private function eventTime(ProviderEvent $event): CarbonImmutable
    {
        $dateCreated = $event->payload['date_created'] ?? null;

        return is_string($dateCreated)
            ? CarbonImmutable::parse($dateCreated)
            : CarbonImmutable::instance($event->created_at);
    }

    private function nextPaymentDate(?string $nextPaymentDate, string $billingPeriod): CarbonImmutable
    {
        if (is_string($nextPaymentDate)) {
            return CarbonImmutable::parse($nextPaymentDate);
        }

        return $billingPeriod === 'annual'
            ? now()->addYear()->toImmutable()
            : now()->addMonth()->toImmutable();
    }
}
