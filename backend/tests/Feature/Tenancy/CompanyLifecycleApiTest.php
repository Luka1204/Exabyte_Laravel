<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Activity\Infrastructure\Persistence\Eloquent\CompanyActivityEvent;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyLifecycleApiTest extends TestCase
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

    public function test_owner_can_deactivate_and_reactivate_company_and_membership(): void
    {
        [$owner, $company, $headers] = $this->companyContext();
        $member = $this->createUser();
        $company->memberships()->attach($member->id, ['role' => 'member']);
        $ownerHeaders = ['Authorization' => 'Bearer '.$this->tokenFor($owner)];

        $this->withHeaders($ownerHeaders)
            ->patchJson("/api/v1/companies/{$company->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($headers)
            ->getJson('/api/v1/categories')
            ->assertNotFound();

        $this->withHeaders($ownerHeaders)
            ->patchJson("/api/v1/companies/{$company->id}", ['is_active' => true])
            ->assertOk();

        $this->withHeaders($ownerHeaders)
            ->deleteJson("/api/v1/companies/{$company->id}/members/{$member->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $member->id,
            'is_active' => false,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->tokenFor($member),
            'X-Company-ID' => (string) $company->id,
        ])->getJson('/api/v1/categories')->assertNotFound();

        $this->withHeaders($ownerHeaders)
            ->patchJson("/api/v1/companies/{$company->id}/members/{$member->id}", [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->tokenFor($member),
            'X-Company-ID' => (string) $company->id,
        ])->getJson('/api/v1/categories')->assertOk();
    }

    public function test_activity_log_records_changes_and_is_limited_to_company_admins(): void
    {
        [$owner, $company] = $this->companyContext();
        $member = $this->createUser();
        $company->memberships()->attach($member->id, ['role' => 'member']);
        $ownerHeaders = [
            'Authorization' => 'Bearer '.$this->tokenFor($owner),
            'X-Company-ID' => (string) $company->id,
        ];

        $this->withHeaders($ownerHeaders)
            ->patchJson("/api/v1/companies/{$company->id}", ['name' => 'Comercio actualizado'])
            ->assertOk();

        $this->withHeaders($ownerHeaders)
            ->getJson('/api/v1/activity')
            ->assertOk()
            ->assertJsonPath('data.0.actor.id', $owner->id)
            ->assertJsonPath('data.0.action', 'record.updated')
            ->assertJsonPath('data.0.subject_id', (string) $company->id)
            ->assertJsonPath('data.0.details.fields', 'name');

        $this->assertSame(1, CompanyActivityEvent::query()
            ->where('company_id', $company->id)
            ->count());

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->tokenFor($member),
            'X-Company-ID' => (string) $company->id,
        ])->getJson('/api/v1/activity')->assertForbidden();
    }

    private function companyContext(): array
    {
        $owner = $this->createUser();
        $company = Company::query()->create(['name' => 'Comercio']);
        $company->memberships()->attach($owner->id, ['role' => 'owner']);

        return [
            $owner,
            $company,
            [
                'Authorization' => 'Bearer '.$this->tokenFor($owner),
                'X-Company-ID' => (string) $company->id,
            ],
        ];
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Usuario',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('safe-test-password'),
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
            'jti' => (string) Str::uuid(),
            'ver' => (int) $user->token_version,
        ], config('jwt.secret'), 'HS256');
    }
}
