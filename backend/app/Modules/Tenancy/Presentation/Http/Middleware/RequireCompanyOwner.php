<?php

namespace App\Modules\Tenancy\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompanyOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->route('company');

        if (! is_string($companyId) || ! ctype_digit($companyId)) {
            return response()->json([
                'message' => 'Empresa no disponible.',
            ], 404);
        }

        $memberships = $request->user()->companies()->whereKey((int) $companyId);

        if (! $memberships->exists()) {
            return response()->json([
                'message' => 'Empresa no disponible.',
            ], 404);
        }

        if (! $memberships->wherePivot('role', 'owner')->exists()) {
            return response()->json([
                'message' => 'No tenés permisos para administrar miembros.',
            ], 403);
        }

        return $next($request);
    }
}
