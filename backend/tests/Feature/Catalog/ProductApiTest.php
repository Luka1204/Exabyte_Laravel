<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductApiTest extends TestCase
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
            'jti' => (string) Str::uuid(),
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

    public function test_products_require_authentication(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_products_require_an_active_company(): void
    {
        $this->withToken($this->tokenFor($this->createUser()))
            ->getJson('/api/v1/products')
            ->assertBadRequest();
    }

    public function test_owner_can_create_and_list_products_within_active_company(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $categoryId = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Instrumentos',
        ])->id;

        $this->withHeaders($this->companyHeaders($owner, $company))
            ->postJson('/api/v1/products', [
                'name' => 'Guitarra acústica',
                'sku' => 'GTR-001',
                'barcode' => '7790000000012',
                'category_id' => $categoryId,
                'price' => '125000.50',
                'tax_rate' => '21.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Guitarra acústica')
            ->assertJsonPath('data.sku', 'GTR-001')
            ->assertJsonPath('data.price', '125000.50')
            ->assertJsonPath('data.tax_rate', '21.00')
            ->assertJsonPath('data.category_id', $categoryId);

        $this->withHeaders($this->companyHeaders($owner, $company))
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('per_page', 15)
            ->assertJsonPath('data.0.sku', 'GTR-001');

        $this->assertDatabaseHas('products', [
            'company_id' => $company->id,
            'category_id' => $categoryId,
            'sku' => 'GTR-001',
            'barcode' => '7790000000012',
            'price' => '125000.50',
            'tax_rate' => '21.00',
        ]);
    }

    public function test_products_are_isolated_between_companies_and_sku_can_repeat_per_tenant(): void
    {
        $user = $this->createUser();
        $firstCompany = $this->createCompany($user, 'Primera');
        $secondCompany = $this->createCompany($user, 'Segunda');

        foreach ([$firstCompany, $secondCompany] as $company) {
            $this->withHeaders($this->companyHeaders($user, $company))
                ->postJson('/api/v1/products', [
                    'name' => 'Producto',
                    'sku' => 'SKU-001',
                    'price' => '10.00',
                ])
                ->assertCreated();
        }

        $this->withHeaders($this->companyHeaders($user, $firstCompany))
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_id', $firstCompany->id);

        $this->withHeaders($this->companyHeaders($user, $secondCompany))
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_id', $secondCompany->id);
    }

    public function test_duplicate_sku_or_barcode_is_rejected_only_within_company(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany($user);
        $headers = $this->companyHeaders($user, $company);
        $product = [
            'name' => 'Primero',
            'sku' => 'SKU-001',
            'barcode' => '7790000000012',
            'price' => '10.00',
        ];

        $this->withHeaders($headers)->postJson('/api/v1/products', $product)->assertCreated();
        $this->withHeaders($headers)->postJson('/api/v1/products', [
            ...$product,
            'name' => 'SKU duplicado',
            'barcode' => '7790000000029',
        ])->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->withHeaders($headers)->postJson('/api/v1/products', [
            ...$product,
            'name' => 'Código duplicado',
            'sku' => 'SKU-002',
        ])->assertUnprocessable()->assertJsonValidationErrors('barcode');
    }

    public function test_category_must_belong_to_the_active_company(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany($user, 'Activa');
        $otherCompany = $this->createCompany($user, 'Otra');
        $category = Category::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Ajena',
        ]);

        $this->withHeaders($this->companyHeaders($user, $company))
            ->postJson('/api/v1/products', [
                'name' => 'Producto',
                'sku' => 'SKU-001',
                'price' => '10.00',
                'category_id' => $category->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    }

    public function test_client_cannot_override_product_company_id(): void
    {
        $user = $this->createUser();
        $activeCompany = $this->createCompany($user, 'Activa');
        $otherCompany = $this->createCompany($user, 'Otra');

        $this->withHeaders($this->companyHeaders($user, $activeCompany))
            ->postJson('/api/v1/products', [
                'name' => 'Producto privado',
                'sku' => 'SKU-001',
                'price' => '10.00',
                'company_id' => $otherCompany->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.company_id', $activeCompany->id);

        $this->assertDatabaseHas('products', [
            'company_id' => $activeCompany->id,
            'sku' => 'SKU-001',
        ]);
        $this->assertDatabaseMissing('products', [
            'company_id' => $otherCompany->id,
            'sku' => 'SKU-001',
        ]);
    }

    public function test_member_can_list_but_cannot_create_products(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);

        $this->withHeaders($this->companyHeaders($member, $company))
            ->postJson('/api/v1/products', [
                'name' => 'Producto no permitido',
                'sku' => 'SKU-001',
                'price' => '10.00',
            ])
            ->assertForbidden();

        $this->withHeaders($this->companyHeaders($member, $company))
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseMissing('products', [
            'company_id' => $company->id,
            'sku' => 'SKU-001',
        ]);
    }

    public function test_product_prices_and_tax_are_validated_as_nonnegative_decimal_values(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany($user);

        $this->withHeaders($this->companyHeaders($user, $company))
            ->postJson('/api/v1/products', [
                'name' => 'Producto inválido',
                'sku' => 'SKU-001',
                'price' => '-1.00',
                'tax_rate' => '100.01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'tax_rate']);
    }
}
