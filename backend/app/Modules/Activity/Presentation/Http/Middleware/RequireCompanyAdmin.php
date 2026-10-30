<?php

namespace App\Modules\Activity\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompanyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->attributes->get('active_company_id');
        $isAdmin = $request->user()->hasCompanyPermission((int) $companyId, 'activity.view');

        if (! $isAdmin) {
            return response()->json([
                'message' => 'No tenés permisos para consultar la trazabilidad.',
            ], 403);
        }

        return $next($request);
    }
}
