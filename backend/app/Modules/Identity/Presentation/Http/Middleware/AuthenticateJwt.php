<?php

namespace App\Modules\Identity\Presentation\Http\Middleware;

use App\Modules\Identity\Infrastructure\Jwt\JwtTokenService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(
        private JwtTokenService $tokens
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        try {
            $session = $this->tokens->authenticate($token);
        } catch (AuthenticationException $exception) {
            return response()->json([
                'message' => 'Token inválido, vencido o revocado.',
            ], 401);
        }

        $request->setUserResolver(fn () => $session->user);
        $request->attributes->set('auth_session_family_id', $session->familyId);

        return $next($request);
    }
}
