<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Firebase\JWT\JWT;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => str_repeat('a', 64),
            'jwt.ttl' => 900,
            'app.url' => 'http://localhost',
        ]);
    }

    private function tokenFor(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss' => config('app.url'),
            'sub' => (string) $user->id,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 900,
            'jti' => (string) \Illuminate\Support\Str::uuid(),
            'ver' => (int) $user->token_version,
        ], config('jwt.secret'), 'HS256');
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Luka',
            'email' => 'luka@luka.com',
            'password' => Hash::make('luka123'),
        ]);
    }

    public function test_categories_require_authentication(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_category(): void
    {
        $user = $this->createUser();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/categories', [
                'name' => 'Instrumentos',
                'description' => 'Instrumentos musicales',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Instrumentos');

        $this->assertDatabaseHas('categories', [
            'name' => 'Instrumentos',
        ]);
    }

    public function test_duplicate_category_is_rejected(): void
    {
        $user = $this->createUser();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/categories', [
                'name' => 'Instrumentos',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/categories', [
                'name' => 'Instrumentos',
            ])
            ->assertUnprocessable();
    }

    public function test_revoked_token_is_rejected(): void
    {
        $user = $this->createUser();
        $token = $this->tokenFor($user);

        $user->increment('token_version');

        $this->withToken($token)
            ->getJson('/api/v1/categories')
            ->assertUnauthorized();
    }
}
