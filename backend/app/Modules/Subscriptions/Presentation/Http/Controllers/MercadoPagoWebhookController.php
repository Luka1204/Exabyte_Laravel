<?php

namespace App\Modules\Subscriptions\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Application\Jobs\ProcessMercadoPagoEvent;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\ProviderEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator;
use Symfony\Component\HttpFoundation\Response;

class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $eventId = $request->input('id');
        $topic = $request->input('type') ?? $request->query('type');
        $resourceId = $request->input('data.id')
            ?? $request->query->get('data.id');

        if (
            (! is_string($eventId) && ! is_int($eventId))
            || (! is_string($topic) || trim($topic) === '')
            || (! is_string($resourceId) && ! is_int($resourceId))
        ) {
            return response()->json(['message' => 'Notificación inválida.'], 422);
        }

        $secret = config('services.mercadopago.webhook_secret');
        if (! is_string($secret) || trim($secret) === '') {
            Log::error('Mercado Pago webhook received without a configured signature secret.');

            return response()->json(['message' => 'Webhook no configurado.'], 503);
        }

        try {
            WebhookSignatureValidator::validate(
                $request->header('x-signature'),
                $request->header('x-request-id'),
                strtolower((string) $resourceId),
                $secret,
                300
            );
        } catch (InvalidWebhookSignatureException $exception) {
            Log::warning('Mercado Pago webhook signature validation failed.', [
                'request_id' => $request->header('x-request-id'),
                'reason' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Firma inválida.'], 401);
        }

        $eventKey = trim((string) $eventId);
        $resourceKey = trim((string) $resourceId);
        $requestId = $request->header('x-request-id');
        $payload = [
            'id' => $eventKey,
            'type' => trim($topic),
            'action' => $request->input('action'),
            'date_created' => $request->input('date_created'),
            'data' => ['id' => $resourceKey],
        ];

        $inserted = DB::table('provider_events')->insertOrIgnore([
            'provider' => 'mercadopago',
            'provider_event_id' => $eventKey,
            'topic' => trim($topic),
            'resource_id' => $resourceKey,
            'request_id' => is_string($requestId) ? Str::limit($requestId, 120, '') : null,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'processing_status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = ProviderEvent::query()
            ->where('provider', 'mercadopago')
            ->where('provider_event_id', $eventKey)
            ->firstOrFail();

        if (in_array($event->processing_status, ['received', 'failed'], true)) {
            ProcessMercadoPagoEvent::dispatch($event->id);
        }

        return new JsonResponse([
            'received' => true,
            'duplicate' => $inserted === 0,
        ], Response::HTTP_OK);
    }
}
