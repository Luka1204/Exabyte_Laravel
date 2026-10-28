<?php

namespace App\Modules\Subscriptions\Infrastructure\MercadoPago;

use App\Modules\Subscriptions\Domain\Contracts\MercadoPagoGateway;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoNotConfigured;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoRequestFailed;
use Illuminate\Support\Facades\Log;
use LogicException;
use MercadoPago\Client\Invoice\InvoiceClient;
use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPSearchRequest;

class MercadoPagoSdkGateway implements MercadoPagoGateway
{
    public function createPendingPreapproval(array $request): array
    {
        $preapproval = $this->request(
            'preapproval.create',
            fn () => (new PreApprovalClient)->create($request)
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
            'preapproval.update',
            fn () => (new PreApprovalClient)->update($id, $request)
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
            'preapproval.get',
            fn () => (new PreApprovalClient)->get($id)
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
            'invoice.get',
            fn () => (new InvoiceClient)->get($id)
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
            'preapproval.cancel',
            fn () => (new PreApprovalClient)->update($id, ['status' => 'cancelled'])
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
            'preapproval.search',
            fn () => (new PreApprovalClient)->search(
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
            throw new MercadoPagoNotConfigured;
        }

        MercadoPagoConfig::setAccessToken($accessToken);
    }

    private function request(string $operationName, callable $operation): mixed
    {
        $this->configureSdk();

        try {
            return $operation();
        } catch (\Exception $exception) {
            $context = [
                'exception' => $exception::class,
                'operation' => $operationName,
            ];
            if ($exception instanceof MPApiException) {
                $context['http_status'] = $exception->getStatusCode();
                $providerResponse = $exception->getApiResponse()->getContent();
                $context['provider_error_codes'] = $this->providerErrorCodes($providerResponse);
                $context += $this->providerErrorDiagnostics($providerResponse);
            } else {
                $context['transport_error_category'] = $this->transportErrorCategory($exception);
            }
            Log::warning('Mercado Pago API request failed.', $context);

            throw new MercadoPagoRequestFailed($exception);
        }
    }

    private function transportErrorCategory(\Exception $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            preg_match('/resolve host|could not resolve|getaddrinfo|name or service not known/i', $message) === 1 => 'dns',
            preg_match('/timed out|timeout/i', $message) === 1 => 'timeout',
            preg_match('/ssl|certificate|tls/i', $message) === 1 => 'tls',
            preg_match('/failed to connect|connection refused|network is unreachable|couldn.t connect/i', $message) === 1 => 'connection',
            default => 'transport_error',
        };
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return list<string>
     */
    private function providerErrorCodes(?array $response): array
    {
        if (! is_array($response)) {
            return [];
        }

        $codes = [];
        foreach (['error', 'code'] as $key) {
            $value = $response[$key] ?? null;
            if (is_string($value) && preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $value)) {
                $codes[] = $value;
            }
        }

        $causes = $response['cause'] ?? [];
        foreach (is_array($causes) ? $causes : [] as $cause) {
            $value = is_array($cause) ? ($cause['code'] ?? null) : null;
            if (is_string($value) || is_int($value)) {
                $codes[] = (string) $value;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return array{provider_response_fields: list<string>, provider_validation_category: string}
     */
    private function providerErrorDiagnostics(?array $response): array
    {
        if (! is_array($response)) {
            return [
                'provider_response_fields' => [],
                'provider_validation_category' => 'unparsed_response',
            ];
        }

        $allowedFields = ['error', 'message', 'status', 'cause', 'code', 'errors', 'description'];
        $responseFields = array_values(array_intersect($allowedFields, array_keys($response)));
        $text = $this->providerErrorText($response);

        $category = match (true) {
            preg_match('/payer_email|payer|buyer|user|email/i', $text) === 1 => 'payer',
            preg_match('/back_url|redirect|return url|url/i', $text) === 1 => 'return_url',
            preg_match('/auto_recurring|transaction_amount|currency|frequency|recurr/i', $text) === 1 => 'recurring_terms',
            preg_match('/external_reference|reference/i', $text) === 1 => 'external_reference',
            preg_match('/credential|access token|authentication|permission/i', $text) === 1 => 'credentials_or_permissions',
            preg_match('/status/i', $text) === 1 => 'status',
            default => 'provider_validation',
        };

        return [
            'provider_response_fields' => $responseFields,
            'provider_validation_category' => $category,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function providerErrorText(array $response): string
    {
        $text = [];
        foreach (['error', 'message', 'description'] as $field) {
            if (is_string($response[$field] ?? null)) {
                $text[] = $response[$field];
            }
        }

        $causes = $response['cause'] ?? [];
        foreach (is_array($causes) ? $causes : [] as $cause) {
            if (is_array($cause) && is_string($cause['description'] ?? null)) {
                $text[] = $cause['description'];
            }
        }

        return implode(' ', $text);
    }
}
