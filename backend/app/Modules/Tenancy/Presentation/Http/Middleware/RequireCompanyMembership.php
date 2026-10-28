<?php

namespace App\Modules\Tenancy\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompanyMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->header('X-Company-ID');

        if (! is_string($companyId) || ! ctype_digit($companyId)) {
            return response()->json([
                'message' => 'Seleccioná una empresa para continuar.',
            ], 400);
        }

        $isMember = $request->user()
            ->companies()
            ->whereKey((int) $companyId)
            ->where('companies.is_active', true)
            ->wherePivot('is_active', true)
            ->exists();

        if (! $isMember) {
            return response()->json([
                'message' => 'Empresa no disponible.',
            ], 404);
        }

        $request->attributes->set('active_company_id', (int) $companyId);

        return $next($request);
    }
}
