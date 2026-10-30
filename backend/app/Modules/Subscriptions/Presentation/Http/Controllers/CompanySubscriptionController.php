<?php

namespace App\Modules\Subscriptions\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Application\UseCases\CancelSubscription;
use App\Modules\Subscriptions\Application\UseCases\ChangeSubscription;
use App\Modules\Subscriptions\Application\UseCases\ReconcileSubscription;
use App\Modules\Subscriptions\Application\UseCases\StartSubscription;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoNotConfigured;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoRequestFailed;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use App\Modules\Subscriptions\Presentation\Requests\ChangeSubscriptionRequest;
use App\Modules\Subscriptions\Presentation\Requests\StartSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanySubscriptionController extends Controller
{
    public function show(Request $request, int $company): JsonResponse
    {
        $subscription = Subscription::query()
            ->with(['paymentAttempts' => fn ($query) => $query->latest('attempt_number')])
            ->where('company_id', $company)
            ->latest('id')
            ->first();

        return response()->json(['data' => $subscription]);
    }

    public function reconcile(Request $request, int $company, ReconcileSubscription $useCase): JsonResponse
    {
        $data = $request->validate([
            'preapproval_id' => ['required', 'string', 'max:120'],
        ]);

        try {
            $subscription = $useCase->execute($company, $data['preapproval_id']);
        } catch (MercadoPagoNotConfigured $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (MercadoPagoRequestFailed) {
            return response()->json([
                'message' => 'No se pudo consultar el estado del pago en Mercado Pago.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json(['data' => $subscription]);
    }

    public function start(
        StartSubscriptionRequest $request,
        int $company,
        StartSubscription $useCase
    ): JsonResponse {
        $data = $request->validated();
        try {
            $subscription = $useCase->execute(
                $company,
                $request->user(),
                (int) $data['plan_id'],
                $data['billing_period']
            );
        } catch (MercadoPagoNotConfigured $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (MercadoPagoRequestFailed) {
            return response()->json([
                'message' => 'Mercado Pago no pudo iniciar el checkout. Podés intentarlo nuevamente.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => $subscription,
            'checkout_url' => $subscription->checkout_url,
        ], $subscription->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function cancel(
        Request $request,
        int $company,
        CancelSubscription $useCase
    ): JsonResponse {
        $subscription = $useCase->execute(
            $company,
            (int) $request->user()->getKey()
        );

        return response()->json(['data' => $subscription]);
    }

    public function change(
        ChangeSubscriptionRequest $request,
        int $company,
        ChangeSubscription $useCase
    ): JsonResponse {
        $data = $request->validated();

        try {
            $subscription = $useCase->execute(
                $company,
                (int) $data['plan_id'],
                $data['billing_period']
            );
        } catch (MercadoPagoNotConfigured $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (MercadoPagoRequestFailed) {
            return response()->json([
                'message' => 'Mercado Pago no pudo actualizar la suscripción. El contrato local no fue modificado.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json(['data' => $subscription]);
    }
}
