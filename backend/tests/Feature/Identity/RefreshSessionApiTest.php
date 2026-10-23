<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\RefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefreshSessionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => str_repeat('a', 64),
            'jwt.ttl' => 900,
            'jwt.refresh_ttl' => 2592000,
            'app.url' => 'http://localhost',
        ]);
    }

    public function test_login_returns_a_refresh_token_but_only_stores_its_hash(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 900)
            ->assertJsonPath('refresh_expires_in', 2592000);

        $refreshToken = $response->json('refresh_token');

        $this->assertIsString($refreshToken);
        $this->assertSame(64, strlen($refreshToken));
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $refreshToken),
            'revoked_at' => null,
        ]);
        $this->assertDatabaseMissing('refresh_tokens', [
            'token_hash' => $refreshToken,
        ]);
    }

    public function test_refresh_rotation_rejects_reuse_and_revokes_the_session_family(): void
    {
        $user = $this->createUser();
        $login = $this->login($user);
        $oldRefreshToken = $login['refresh_token'];

        $rotated = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ])->assertOk();

        $newAccessToken = $rotated->json('access_token');
        $newRefreshToken = $rotated->json('refresh_token');
        $this->assertNotSame($oldRefreshToken, $newRefreshToken);

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ])->assertUnauthorized();

        $this->withToken($newAccessToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $newRefreshToken,
        ])->assertUnauthorized();

        $this->assertSame(
            0,
            RefreshToken::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_logout_revokes_only_the_current_session(): void
    {
        $user = $this->createUser();
        $firstSession = $this->login($user);
        $secondSession = $this->login($user);

        $this->withToken($firstSession['access_token'])
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->withToken($firstSession['access_token'])
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->withToken($secondSession['access_token'])
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $secondSession['refresh_token'],
        ])->assertOk();
    }

    public function test_expired_refresh_token_cannot_be_rotated(): void
    {
        $user = $this->createUser();
        $session = $this->login($user);

        $this->travel(31)->days();

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $session['refresh_token'],
        ])->assertUnauthorized();
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Usuario',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('secret-password'),
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    private function login(User $user): array
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk();

        return [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
        ];
    }
}
