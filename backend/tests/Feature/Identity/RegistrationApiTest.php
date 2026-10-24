<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => str_repeat('b', 64),
            'jwt.ttl' => 900,
            'app.url' => 'http://localhost',
        ]);
    }

    public function test_visitor_can_register_a_user_and_owned_company(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Tienda Nueva',
            'email' => 'owner@example.test',
            'password' => 'a-secure-password-123',
            'password_confirmation' => 'a-secure-password-123',
            'company_name' => 'Mi Tienda',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'owner@example.test')
            ->assertJsonPath('company.name', 'Mi Tienda')
            ->assertJsonPath('company.role', 'owner')
            ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in']);

        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertDatabaseHas('company_memberships', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseMissing('platform_admins', ['user_id' => $user->id]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Otra Tienda',
            'email' => 'second@example.test',
            'password' => 'another-secure-password-456',
            'password_confirmation' => 'another-secure-password-456',
            'company_name' => 'Otra empresa',
        ])->assertCreated();
        $secondUser = User::query()->where('email', 'second@example.test')->firstOrFail();
        $claims = JWT::decode($response->json('access_token'), new Key(config('jwt.secret'), 'HS256'));
        $this->assertSame((string) $secondUser->id, (string) $claims->sub);
    }

    public function test_registration_validates_unique_email_and_strong_password(): void
    {
        User::query()->create([
            'name' => 'Existing',
            'email' => 'existing@example.test',
            'password' => 'not-a-real-hash',
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Attacker',
            'email' => 'existing@example.test',
            'password' => 'short',
            'password_confirmation' => 'different',
            'company_name' => 'Duplicada',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('companies', 0);
    }
}
