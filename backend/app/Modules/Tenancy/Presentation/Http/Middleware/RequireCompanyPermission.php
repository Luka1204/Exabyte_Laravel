<?php

namespace App\Modules\Tenancy\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompanyPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        if (! $request->user()->hasCompanyPermission($companyId, $permission)) {
            return response()->json(['message' => 'No tenés permisos para realizar esta operación.'], 403);
        }
        return $next($request);
    }
}
