<?php

namespace App\Modules\Identity\Application\UseCases;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Jwt\JwtTokenService;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\RefreshToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefreshSession
{
    public function __construct(private JwtTokenService $tokens) {}

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, refresh_expires_in: int}
     */
    public function create(User $user): array
    {
        $familyId = (string) Str::uuid();
        $refreshToken = $this->persistRefreshToken($user, $familyId);

        return $this->response($user, $familyId, $refreshToken);
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, refresh_expires_in: int}
     */
    public function rotate(string $refreshToken): array
    {
        $result = DB::transaction(function () use ($refreshToken): ?array {
            $token = RefreshToken::query()
                ->where('token_hash', hash('sha256', $refreshToken))
                ->lockForUpdate()
                ->first();

            if (! $token) {
                return null;
            }

            if ($token->revoked_at !== null) {
                $this->revokeFamily($token->family_id);

                return null;
            }

            if (! $token->expires_at->isFuture()) {
                $token->update(['revoked_at' => now()]);

                return null;
            }

            $user = $token->user;

            if ((int) $token->token_version !== (int) $user->token_version) {
                $this->revokeFamily($token->family_id);

                return null;
            }

            $token->update(['revoked_at' => now()]);
            $newRefreshToken = $this->persistRefreshToken($user, $token->family_id);

            return [
                'user' => $user,
                'family_id' => $token->family_id,
                'refresh_token' => $newRefreshToken,
            ];
        }, attempts: 3);

        if (! $result) {
            throw new AuthenticationException('El refresh token es inválido, venció o fue revocado.');
        }

        return $this->response(
            $result['user'],
            $result['family_id'],
            $result['refresh_token'],
        );
    }

    public function revokeFamily(string $familyId): void
    {
        RefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function persistRefreshToken(User $user, string $familyId): string
    {
        $refreshToken = bin2hex(random_bytes(32));

        RefreshToken::query()->create([
            'user_id' => $user->getKey(),
            'family_id' => $familyId,
            'token_hash' => hash('sha256', $refreshToken),
            'token_version' => (int) $user->token_version,
            'expires_at' => now()->addSeconds($this->refreshTtl()),
        ]);

        return $refreshToken;
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, refresh_expires_in: int}
     */
    private function response(User $user, string $familyId, string $refreshToken): array
    {
        return [
            'access_token' => $this->tokens->issue($user, $familyId),
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 900),
            'refresh_expires_in' => $this->refreshTtl(),
        ];
    }

    private function refreshTtl(): int
    {
        return (int) config('jwt.refresh_ttl', 2592000);
    }
}
