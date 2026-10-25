<?php

namespace App\Modules\Subscriptions\Infrastructure\MercadoPago;

use App\Modules\Subscriptions\Domain\Contracts\MercadoPagoGateway;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoNotConfigured;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoRequestFailed;
use LogicException;
use MercadoPago\Client\Invoice\InvoiceClient;
use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPSearchRequest;

class MercadoPagoSdkGateway implements MercadoPagoGateway
{
    public function createPendingPreapproval(array $request): array
    {
        $preapproval = $this->request(
            fn () => (new PreApprovalClient())->create($request)
        );

        if (! $preapproval->id || ! $preapproval->init_point || ! $preapproval->status) {
            throw new LogicException('Mercado Pago returned an incomplete subscription checkout response.');
        }

        return [
            'id' => $preapproval->id,
            'init_point' => $preapproval->init_point,
            'status' => $preapproval->status,
        ];
    }

    public function updatePreapproval(string $id, array $request): array
    {
        $preapproval = $this->request(
            fn () => (new PreApprovalClient())->update($id, $request)
        );

        if (! $preapproval->id || ! $preapproval->status) {
            throw new LogicException('Mercado Pago returned an incomplete subscription update response.');
        }

        return [
            'id' => (string) $preapproval->id,
            'status' => (string) $preapproval->status,
        ];
    }

    public function getPreapproval(string $id): array
    {
        $preapproval = $this->request(
            fn () => (new PreApprovalClient())->get($id)
        );

        if (! $preapproval->id || ! $preapproval->status) {
            throw new LogicException('Mercado Pago returned an incomplete subscription response.');
        }

        return [
            'id' => $preapproval->id,
            'status' => $preapproval->status,
            'next_payment_date' => $preapproval->next_payment_date,
        ];
    }

    public function getAuthorizedPayment(int $id): array
    {
        $invoice = $this->request(
            fn () => (new InvoiceClient())->get($id)
        );
        $payment = is_object($invoice->payment) ? $invoice->payment : null;

        if (! $invoice->id || ! $invoice->preapproval_id || ! $invoice->status) {
            throw new LogicException('Mercado Pago returned an incomplete authorized payment response.');
        }

        return [
            'id' => (string) $invoice->id,
            'preapproval_id' => $invoice->preapproval_id,
            'status' => $invoice->status,
            'amount' => number_format((float) $invoice->transaction_amount, 2, '.', ''),
            'currency' => (string) $invoice->currency_id,
            'payment_id' => $payment?->id ? (string) $payment->id : null,
            'payment_status' => $payment?->status,
            'payment_status_detail' => $payment?->status_detail,
            'next_payment_date' => null,
        ];
    }

    public function cancelPreapproval(string $id): array
    {
        $preapproval = $this->request(
            fn () => (new PreApprovalClient())->update($id, ['status' => 'cancelled'])
        );

        if (! $preapproval->id || ! $preapproval->status) {
            throw new LogicException('Mercado Pago returned an incomplete cancellation response.');
        }

        return [
            'id' => $preapproval->id,
            'status' => $preapproval->status,
        ];
    }

    public function findPreapprovalByReference(string $externalReference): ?array
    {
        $search = $this->request(
            fn () => (new PreApprovalClient())->search(
                new MPSearchRequest(10, 0, ['external_reference' => $externalReference])
            )
        );
        $results = is_array($search->results) ? $search->results : [];

        foreach ($results as $result) {
            if ($result->external_reference === $externalReference) {
                return [
                    'id' => (string) $result->id,
                    'init_point' => (string) $result->init_point,
                    'status' => (string) $result->status,
                ];
            }
        }

        return null;
    }

    private function configureSdk(): void
    {
        $accessToken = config('services.mercadopago.access_token');
        $environment = config('services.mercadopago.environment');

        if (
            ! is_string($accessToken)
            || trim($accessToken) === ''
            || ! in_array($environment, ['sandbox', 'production'], true)
        ) {
            throw new MercadoPagoNotConfigured();
        }

        MercadoPagoConfig::setAccessToken($accessToken);
    }

    private function request(callable $operation): mixed
    {
        $this->configureSdk();

        try {
            return $operation();
        } catch (\Exception $exception) {
            throw new MercadoPagoRequestFailed($exception);
        }
    }
}
