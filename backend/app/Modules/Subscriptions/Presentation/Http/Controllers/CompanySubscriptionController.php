<?php

namespace App\Modules\Subscriptions\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoNotConfigured;
use App\Modules\Subscriptions\Domain\Exceptions\MercadoPagoRequestFailed;
use App\Modules\Subscriptions\Application\UseCases\CancelSubscription;
use App\Modules\Subscriptions\Application\UseCases\StartSubscription;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
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
        } catch (MercadoPagoNotConfigured) {
            return response()->json([
                'message' => 'La contratación online no está configurada todavía.',
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
}
