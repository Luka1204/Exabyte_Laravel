<?php

namespace Tests\Unit;

use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoNotConfigured;
use App\Modules\Subscriptions\Infrastructure\MercadoPago\MercadoPagoSdkGateway;
use MercadoPago\MercadoPagoConfig;
use Tests\TestCase;

class MercadoPagoConfigurationTest extends TestCase
{
    public function test_sandbox_accepts_an_app_usr_access_token_from_test_credentials(): void
    {
        config([
            'services.mercadopago.access_token' => 'APP_USR-test-token-placeholder',
            'services.mercadopago.environment' => 'sandbox',
        ]);

        $gateway = new MercadoPagoSdkGateway;
        $configureSdk = new \ReflectionMethod($gateway, 'configureSdk');
        $configureSdk->invoke($gateway);

        $this->assertSame('APP_USR-test-token-placeholder', MercadoPagoConfig::getAccessToken());
    }

    public function test_unknown_environment_is_rejected_before_provider_request(): void
    {
        config([
            'services.mercadopago.access_token' => 'APP_USR-test-token-placeholder',
            'services.mercadopago.environment' => 'testing',
        ]);

        $this->expectException(MercadoPagoNotConfigured::class);

        $gateway = new MercadoPagoSdkGateway;
        $configureSdk = new \ReflectionMethod($gateway, 'configureSdk');
        $configureSdk->invoke($gateway);
    }

    public function test_transport_error_logs_only_a_safe_category(): void
    {
        $gateway = new MercadoPagoSdkGateway;
        $classify = new \ReflectionMethod($gateway, 'transportErrorCategory');

        $this->assertSame(
            'dns',
            $classify->invoke($gateway, new \Exception('Could not resolve host: private-host.example'))
        );
        $this->assertSame(
            'tls',
            $classify->invoke($gateway, new \Exception('SSL certificate details'))
        );
        $this->assertSame(
            'transport_error',
            $classify->invoke($gateway, new \Exception('Sensitive details not in the allowlist'))
        );
    }

    public function test_provider_error_diagnostics_classify_without_logging_error_text(): void
    {
        $gateway = new MercadoPagoSdkGateway;
        $diagnose = new \ReflectionMethod($gateway, 'providerErrorDiagnostics');
        $diagnostics = $diagnose->invoke($gateway, [
            'message' => 'Invalid payer_email for private.person@example.test',
            'cause' => [
                ['code' => 1234, 'description' => 'Invalid payer_email'],
            ],
        ]);

        $this->assertSame(['message', 'cause'], $diagnostics['provider_response_fields']);
        $this->assertSame('payer', $diagnostics['provider_validation_category']);
        $this->assertStringNotContainsString('private.person@example.test', json_encode($diagnostics));
    }
}
