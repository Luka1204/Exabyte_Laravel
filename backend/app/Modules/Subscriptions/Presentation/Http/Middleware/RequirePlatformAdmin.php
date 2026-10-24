<?php

namespace App\Modules\Subscriptions\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequirePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $isAdmin = DB::table('platform_admins')
            ->where('user_id', $request->user()->getKey())
            ->exists();

        if (! $isAdmin) {
            return response()->json([
                'message' => 'No tenés permisos para administrar la plataforma.',
            ], 403);
        }

        return $next($request);
    }
}
