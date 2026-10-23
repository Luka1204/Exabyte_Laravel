<?php

namespace App\Modules\Identity\Infrastructure\Jwt;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\RefreshToken;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use UnexpectedValueException;

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

    public function issue(User $user, string $familyId): string
    {
        $now = time();

        return JWT::encode([
            'iss' => (string) config('app.url'),
            'sub' => (string) $user->getKey(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + (int) config('jwt.ttl', 900),
            'jti' => (string) Str::uuid(),
            'sid' => $familyId,
            'ver' => (int) $user->token_version,
        ], $this->secret(), 'HS256');
    }

    public function authenticate(string $token): AuthenticatedSession
    {
        try {
            $claims = JWT::decode(
                $token,
                new Key($this->secret(), 'HS256')
            );
        } catch (UnexpectedValueException|DomainException $exception) {
            throw new AuthenticationException('Token inválido o vencido.');
        }

        if (
            ! isset($claims->iss, $claims->sub, $claims->exp, $claims->ver)
            || $claims->iss !== (string) config('app.url')
            || ! ctype_digit((string) $claims->sub)
            || (isset($claims->sid) && ! Str::isUuid((string) $claims->sid))
        ) {
            throw new AuthenticationException('Token inválido.');
        }

        $user = User::query()->find($claims->sub);

        if (
            ! $user
            || (int) $claims->ver !== (int) $user->token_version
        ) {
            throw new AuthenticationException('Sesión revocada.');
        }

        $familyId = isset($claims->sid) ? (string) $claims->sid : null;

        if (
            $familyId !== null
            && ! RefreshToken::query()
                ->where('user_id', $user->getKey())
                ->where('family_id', $familyId)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists()
        ) {
            throw new AuthenticationException('Sesión revocada.');
        }

        return new AuthenticatedSession($user, $familyId);
    }
}
