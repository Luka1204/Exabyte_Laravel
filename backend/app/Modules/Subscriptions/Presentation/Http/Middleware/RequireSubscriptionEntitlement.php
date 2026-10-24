<?php

namespace App\Modules\Subscriptions\Presentation\Http\Middleware;

use App\Modules\Subscriptions\Application\Services\SubscriptionAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSubscriptionEntitlement
{
    public function handle(
        Request $request,
        Closure $next,
        string $entitlement
    ): Response {
        $companyId = $request->attributes->get('active_company_id');
        if (! is_int($companyId)) {
            return response()->json([
                'message' => 'No se pudo determinar la empresa activa.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $denial = app(SubscriptionAccess::class)->featureDenial($companyId, $entitlement);
        if ($denial !== null) {
            return response()->json([
                'message' => $denial['message'],
                'code' => $denial['code'],
                'required_entitlement' => $entitlement,
                'plans_url' => '/plans',
            ], $denial['status']);
        }

        return $next($request);
    }
}
