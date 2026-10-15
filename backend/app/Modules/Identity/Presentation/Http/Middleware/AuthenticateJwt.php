<?php

namespace App\Modules\Identity\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\Identity\Infrastructure\Jwt\JwtTokenService;
use Illuminate\Auth\AuthenticationException;

class AuthenticateJwt
{
    public function __construct(
        private JwtTokenService $tokens
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        try {
            $user = $this->tokens->authenticate($token);
        } catch (AuthenticationException $exception) {
            return response()->json([
                'message' => 'Token inválido, vencido o revocado.',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
