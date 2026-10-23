<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('luka123'),
        ]);
    }

    private function createCompany(User $owner, string $name = 'Comercio'): Company
    {
        $company = Company::query()->create(['name' => $name]);
        $company->memberships()->attach($owner->id, ['role' => 'owner']);

        return $company;
    }

    private function companyHeaders(User $user, Company $company): array
    {
        return [
            'Authorization' => 'Bearer '.$this->tokenFor($user),
            'X-Company-ID' => (string) $company->id,
        ];
    }

    public function test_categories_require_authentication(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertUnauthorized();
    }

    public function test_categories_use_fifteen_items_per_page_by_default(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany($user);

        $this->withHeaders($this->companyHeaders($user, $company))
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('per_page', 15);
    }

    public function test_categories_require_an_active_company(): void
    {
        $user = $this->createUser();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/categories')
            ->assertBadRequest();
    }

    public function test_authenticated_user_can_create_a_company_as_owner(): void
    {
        $user = $this->createUser();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/companies', ['name' => 'Tienda Norte'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tienda Norte')
            ->assertJsonPath('data.role', 'owner');

        $this->assertDatabaseHas('company_memberships', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_company_name_cannot_be_blank_after_trimming(): void
    {
        $user = $this->createUser();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/companies', ['name' => '   '])
            ->assertUnprocessable();
    }

    public function test_company_list_only_contains_user_memberships(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $company = $this->createCompany($user, 'Propia');
        $this->createCompany($otherUser, 'Ajena');

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $company->id);
    }

    public function test_authenticated_user_can_create_category(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany($user);

        $this->withHeaders($this->companyHeaders($user, $company))
            ->postJson('/api/v1/categories', [
                'name' => 'Instrumentos',
                'description' => 'Instrumentos musicales',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Instrumentos');

        $this->assertDatabaseHas('categories', [
            'name' => 'Instrumentos',
            'company_id' => $company->id,
        ]);
    }

    public function test_duplicate_category_is_rejected(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany($user);

        $this->withHeaders($this->companyHeaders($user, $company))
            ->postJson('/api/v1/categories', [
                'name' => 'Instrumentos',
            ])
            ->assertCreated();

        $this->withHeaders($this->companyHeaders($user, $company))
            ->postJson('/api/v1/categories', [
                'name' => 'Instrumentos',
            ])
            ->assertUnprocessable();
    }

    public function test_categories_are_isolated_between_companies(): void
    {
        $user = $this->createUser();
        $firstCompany = $this->createCompany($user, 'Primera');
        $secondCompany = $this->createCompany($user, 'Segunda');

        $this->withHeaders($this->companyHeaders($user, $firstCompany))
            ->postJson('/api/v1/categories', ['name' => 'Alimentos'])
            ->assertCreated();

        $this->withHeaders($this->companyHeaders($user, $secondCompany))
            ->postJson('/api/v1/categories', ['name' => 'Alimentos'])
            ->assertCreated();

        $this->withHeaders($this->companyHeaders($user, $firstCompany))
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alimentos');

        $this->withHeaders($this->companyHeaders($user, $secondCompany))
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alimentos');
    }

    public function test_company_member_can_read_categories_but_cannot_create_them(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);

        $this->withHeaders($this->companyHeaders($owner, $company))
            ->postJson('/api/v1/categories', ['name' => 'Instrumentos'])
            ->assertCreated();

        $this->withHeaders($this->companyHeaders($member, $company))
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->companyHeaders($member, $company))
            ->postJson('/api/v1/categories', ['name' => 'Restringida'])
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', [
            'company_id' => $company->id,
            'name' => 'Restringida',
        ]);
    }

    public function test_company_manager_can_create_categories(): void
    {
        $owner = $this->createUser();
        $manager = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($manager->id, ['role' => 'manager']);

        $this->withHeaders($this->companyHeaders($manager, $company))
            ->postJson('/api/v1/categories', ['name' => 'Gestionable'])
            ->assertCreated();

        $this->assertDatabaseHas('categories', [
            'company_id' => $company->id,
            'name' => 'Gestionable',
        ]);
    }

    public function test_legacy_categories_are_preserved_but_not_exposed_to_companies(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany($user);
        $legacyCategory = Category::query()->create([
            'name' => 'Categoría histórica',
        ]);

        $this->withHeaders($this->companyHeaders($user, $company))
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('categories', [
            'id' => $legacyCategory->id,
            'company_id' => null,
            'name' => 'Categoría histórica',
        ]);
    }

    public function test_non_member_cannot_access_another_company_categories(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $company = $this->createCompany($otherUser);

        $this->withHeaders($this->companyHeaders($user, $company))
            ->getJson('/api/v1/categories')
            ->assertNotFound()
            ->assertJsonPath('message', 'Empresa no disponible.');
    }

    public function test_client_cannot_override_category_company_id(): void
    {
        $user = $this->createUser();
        $activeCompany = $this->createCompany($user, 'Activa');
        $otherCompany = $this->createCompany($user, 'Otra');

        $this->withHeaders($this->companyHeaders($user, $activeCompany))
            ->postJson('/api/v1/categories', [
                'name' => 'Privada',
                'company_id' => $otherCompany->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('categories', [
            'name' => 'Privada',
            'company_id' => $activeCompany->id,
        ]);
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
