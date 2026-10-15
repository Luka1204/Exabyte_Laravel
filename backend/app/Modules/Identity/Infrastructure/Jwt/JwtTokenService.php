<?php

namespace App\Modules\Identity\Infrastructure\Jwt;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use UnexpectedValueException;
use DomainException;

class JwtTokenService
{
    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if (strlen($secret) < 32) {
            throw new \RuntimeException(
                'JWT_SECRET debe tener al menos 32 caracteres.'
            );
        }

        return $secret;
    }

    public function issue(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss' => (string) config('app.url'),
            'sub' => (string) $user->getKey(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + (int) config('jwt.ttl', 900),
            'jti' => (string) Str::uuid(),
            'ver' => (int) $user->token_version,
        ], $this->secret(), 'HS256');
    }

    public function authenticate(string $token): User
    {
        try {
            $claims = JWT::decode(
                $token,
                new Key($this->secret(), 'HS256')
            );
        } catch (UnexpectedValueException | DomainException $exception) {
            throw new AuthenticationException('Token inválido o vencido.');
        }

        if (
            !isset($claims->iss, $claims->sub, $claims->exp, $claims->ver)
            || $claims->iss !== (string) config('app.url')
            || !ctype_digit((string) $claims->sub)
        ) {
            throw new AuthenticationException('Token inválido.');
        }

        $user = User::query()->find($claims->sub);

        if (
            !$user
            || (int) $claims->ver !== (int) $user->token_version
        ) {
            throw new AuthenticationException('Sesión revocada.');
        }

        return $user;
    }
}
