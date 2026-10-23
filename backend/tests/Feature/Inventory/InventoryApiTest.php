<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryApiTest extends TestCase
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
            'name' => 'Usuario',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('local-password'),
        ]);
    }

    private function createCompany(User $owner, string $name = 'Comercio'): Company
    {
        $company = Company::query()->create(['name' => $name]);
        $company->memberships()->attach($owner->id, ['role' => 'owner']);

        return $company;
    }

    private function createProduct(Company $company, string $sku = 'SKU-001'): Product
    {
        return Product::query()->create([
            'company_id' => $company->id,
            'name' => 'Producto',
            'sku' => $sku,
            'price' => '100.00',
            'tax_rate' => '0.00',
            'is_active' => true,
        ]);
    }

    private function companyHeaders(User $user, Company $company): array
    {
        return [
            'Authorization' => 'Bearer '.$this->tokenFor($user),
            'X-Company-ID' => (string) $company->id,
        ];
    }

    private function adjustment(Product $product, array $overrides = []): array
    {
        return [
            'product_id' => $product->id,
            'quantity_delta' => '5.250',
            'reason' => 'Conteo inicial',
            'idempotency_key' => (string) Str::uuid(),
            ...$overrides,
        ];
    }

    public function test_inventory_requires_authentication_and_active_company(): void
    {
        $this->getJson('/api/v1/inventory/stocks')->assertUnauthorized();

        $this->withToken($this->tokenFor($this->createUser()))
            ->getJson('/api/v1/inventory/stocks')
            ->assertBadRequest();
    }

    public function test_owner_can_adjust_stock_and_list_balance_and_movement(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);

        $this->withHeaders($headers)
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment($product))
            ->assertCreated()
            ->assertJsonPath('data.quantity_delta', '5.250')
            ->assertJsonPath('data.balance_after', '5.250')
            ->assertJsonPath('replayed', false);

        $this->withHeaders($headers)
            ->getJson('/api/v1/inventory/stocks')
            ->assertOk()
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.quantity', '5.250');

        $this->withHeaders($headers)
            ->getJson('/api/v1/inventory/movements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reason', 'Conteo inicial');

        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_replaying_identical_idempotency_key_does_not_apply_stock_twice(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $payload = $this->adjustment($product, ['quantity_delta' => '2.5']);

        $this->withHeaders($headers)
            ->postJson('/api/v1/inventory/adjustments', $payload)
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/v1/inventory/adjustments', $payload)
            ->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('data.balance_after', '2.500');

        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'quantity' => '2.500',
        ]);
    }

    public function test_reusing_idempotency_key_for_another_operation_conflicts(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $payload = $this->adjustment($product);

        $this->withHeaders($headers)
            ->postJson('/api/v1/inventory/adjustments', $payload)
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/v1/inventory/adjustments', [
                ...$payload,
                'quantity_delta' => '4.000',
            ])
            ->assertConflict();

        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_adjustment_cannot_leave_negative_stock_and_is_rolled_back(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);

        $this->withHeaders($this->companyHeaders($owner, $company))
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment(
                $product,
                ['quantity_delta' => '-0.001']
            ))
            ->assertUnprocessable();

        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_stock_decrease_updates_balance_and_keeps_a_separate_ledger_entry(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);

        $this->withHeaders($headers)
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment(
                $product,
                ['quantity_delta' => '3.500']
            ))
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment(
                $product,
                ['quantity_delta' => '-1.250', 'reason' => 'Merma']
            ))
            ->assertCreated()
            ->assertJsonPath('data.balance_after', '2.250');

        $this->withHeaders($headers)
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment(
                $product,
                ['quantity_delta' => '-3.000', 'reason' => 'Salida no permitida']
            ))
            ->assertUnprocessable();

        $this->withHeaders($headers)
            ->getJson('/api/v1/inventory/stocks')
            ->assertOk()
            ->assertJsonPath('data.0.quantity', '2.250');

        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_adjustment_rejects_zero_quantity_and_cross_company_products(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner, 'Activa');
        $otherCompany = $this->createCompany($owner, 'Otra');
        $product = $this->createProduct($otherCompany);

        $this->withHeaders($this->companyHeaders($owner, $company))
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment(
                $product,
                ['quantity_delta' => '0.000']
            ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'quantity_delta']);

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_members_can_read_but_only_owner_and_manager_can_adjust(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $manager = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);
        $company->memberships()->attach($manager->id, ['role' => 'manager']);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($member, $company);

        $this->withHeaders($headers)
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment($product))
            ->assertForbidden();

        $this->withHeaders($headers)
            ->getJson('/api/v1/inventory/stocks')
            ->assertOk();

        $this->withHeaders($this->companyHeaders($manager, $company))
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment($product))
            ->assertCreated();

        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_inventory_balances_and_history_are_isolated_between_companies(): void
    {
        $user = $this->createUser();
        $firstCompany = $this->createCompany($user, 'Primera');
        $secondCompany = $this->createCompany($user, 'Segunda');
        $firstProduct = $this->createProduct($firstCompany);
        $secondProduct = $this->createProduct($secondCompany);

        $this->withHeaders($this->companyHeaders($user, $firstCompany))
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment($firstProduct))
            ->assertCreated();
        $this->withHeaders($this->companyHeaders($user, $secondCompany))
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment($secondProduct))
            ->assertCreated();

        $this->withHeaders($this->companyHeaders($user, $firstCompany))
            ->getJson('/api/v1/inventory/stocks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $firstProduct->id);

        $this->withHeaders($this->companyHeaders($user, $firstCompany))
            ->getJson('/api/v1/inventory/movements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $firstProduct->id);
    }

    public function test_client_cannot_override_company_or_actor_on_adjustment(): void
    {
        $owner = $this->createUser();
        $otherUser = $this->createUser();
        $company = $this->createCompany($owner);
        $otherCompany = $this->createCompany($owner, 'Otra');
        $product = $this->createProduct($company);

        $this->withHeaders($this->companyHeaders($owner, $company))
            ->postJson('/api/v1/inventory/adjustments', $this->adjustment($product, [
                'company_id' => $otherCompany->id,
                'user_id' => $otherUser->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.user_id', $owner->id);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'user_id' => $owner->id,
        ]);
    }
}
