<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogLifecycleApiTest extends TestCase
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

    public function test_owner_can_edit_and_toggle_category_status(): void
    {
        [$user, $company, $headers] = $this->companyContext();
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Bebidas',
        ]);

        $this->withHeaders($headers)
            ->putJson("/api/v1/categories/{$category->id}", [
                'name' => 'Bebidas sin alcohol',
                'description' => 'Actualizada',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bebidas sin alcohol');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/categories/{$category->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'company_id' => $company->id,
            'is_active' => false,
        ]);
    }

    public function test_owner_can_edit_and_reactivate_product_without_changing_its_identity(): void
    {
        [$user, $company, $headers] = $this->companyContext();
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Almacén',
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Arroz',
            'sku' => 'ARR-01',
            'price' => '100.00',
            'tax_rate' => '0.00',
        ]);

        $this->withHeaders($headers)
            ->putJson("/api/v1/products/{$product->id}", [
                'name' => 'Arroz largo fino',
                'sku' => 'ARR-01',
                'barcode' => null,
                'category_id' => $category->id,
                'price' => '125.00',
                'tax_rate' => '10.50',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Arroz largo fino')
            ->assertJsonPath('data.price', '125.00');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/products/{$product->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/products/{$product->id}/status", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'company_id' => $company->id,
            'sku' => 'ARR-01',
            'price' => '125.00',
            'is_active' => true,
        ]);
    }

    private function companyContext(): array
    {
        $user = User::query()->create([
            'name' => 'Owner',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('safe-test-password'),
        ]);
        $company = Company::query()->create(['name' => 'Comercio']);
        $company->memberships()->attach($user->id, ['role' => 'owner']);
        $now = time();
        $token = JWT::encode([
            'iss' => config('app.url'),
            'sub' => (string) $user->id,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 900,
            'jti' => (string) Str::uuid(),
            'ver' => (int) $user->token_version,
        ], config('jwt.secret'), 'HS256');

        return [
            $user,
            $company,
            [
                'Authorization' => 'Bearer '.$token,
                'X-Company-ID' => (string) $company->id,
            ],
        ];
    }
}
