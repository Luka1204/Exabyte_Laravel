<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyMemberApiTest extends TestCase
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

    public function test_company_owner_can_list_members_scoped_to_the_company(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $otherOwner = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);
        $this->createCompany($otherOwner, 'Otra empresa');

        $this->withToken($this->tokenFor($owner))
            ->getJson("/api/v1/companies/{$company->id}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $member->id,
                'email' => $member->email,
                'role' => 'member',
            ])
            ->assertJsonMissing(['id' => $otherOwner->id]);
    }

    public function test_company_owner_can_change_member_role(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);

        $this->withToken($this->tokenFor($owner))
            ->patchJson(
                "/api/v1/companies/{$company->id}/members/{$member->id}",
                ['role' => 'manager']
            )
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.role', 'manager');

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $member->id,
            'role' => 'manager',
        ]);
    }

    public function test_company_owner_can_remove_a_non_owner_member(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'manager']);

        $this->withToken($this->tokenFor($owner))
            ->deleteJson(
                "/api/v1/companies/{$company->id}/members/{$member->id}"
            )
            ->assertNoContent();

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $member->id,
            'is_active' => false,
        ]);
    }

    public function test_non_owner_cannot_manage_company_members(): void
    {
        $owner = $this->createUser();
        $manager = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($manager->id, ['role' => 'manager']);

        $this->withToken($this->tokenFor($manager))
            ->getJson("/api/v1/companies/{$company->id}/members")
            ->assertForbidden();
    }

    public function test_member_cannot_probe_a_company_they_do_not_belong_to(): void
    {
        $owner = $this->createUser();
        $nonMember = $this->createUser();
        $company = $this->createCompany($owner);

        $this->withToken($this->tokenFor($nonMember))
            ->getJson("/api/v1/companies/{$company->id}/members")
            ->assertNotFound()
            ->assertJsonPath('message', 'Empresa no disponible.');
    }

    public function test_owner_membership_cannot_be_changed_or_removed(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $headers = $this->withToken($this->tokenFor($owner));

        $headers->patchJson(
            "/api/v1/companies/{$company->id}/members/{$owner->id}",
            ['role' => 'member']
        )->assertUnprocessable();

        $headers->deleteJson(
            "/api/v1/companies/{$company->id}/members/{$owner->id}"
        )->assertUnprocessable();

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_owner_role_cannot_be_assigned_through_member_role_update(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);

        $this->withToken($this->tokenFor($owner))
            ->patchJson(
                "/api/v1/companies/{$company->id}/members/{$member->id}",
                ['role' => 'owner']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Usuario '.Str::random(6),
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('secret-password'),
        ]);
    }

    private function createCompany(User $owner, string $name = 'Comercio'): Company
    {
        $company = Company::query()->create(['name' => $name]);
        $company->memberships()->attach($owner->id, ['role' => 'owner']);

        return $company;
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
