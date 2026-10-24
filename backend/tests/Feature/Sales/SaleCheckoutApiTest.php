<?php

namespace Tests\Feature\Sales;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleCheckoutApiTest extends TestCase
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
            'name' => 'Cajero',
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

    private function companyHeaders(User $user, Company $company): array
    {
        return [
            'Authorization' => 'Bearer '.$this->tokenFor($user),
            'X-Company-ID' => (string) $company->id,
        ];
    }

    private function createProduct(Company $company, string $sku = 'SKU-001'): Product
    {
        $product = Product::query()->create([
            'company_id' => $company->id,
            'name' => 'Artículo',
            'sku' => $sku,
            'price' => '100.00',
            'tax_rate' => '21.00',
            'is_active' => true,
        ]);

        InventoryBalance::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'quantity' => '5.000',
        ]);

        return $product;
    }

    private function openCash(array $headers, string $openingAmount = '50.00'): int
    {
        return $this->withHeaders($headers)
            ->postJson('/api/v1/cash/session', ['opening_amount' => $openingAmount])
            ->assertCreated()
            ->json('data.id');
    }

    private function salePayload(Product $product, array $overrides = []): array
    {
        return [
            'items' => [
                ['product_id' => $product->id, 'quantity' => '2.000'],
            ],
            'cash_received' => '300.00',
            'idempotency_key' => (string) Str::uuid(),
            ...$overrides,
        ];
    }

    public function test_cash_session_requires_authentication_and_company_context(): void
    {
        $this->getJson('/api/v1/cash/session')->assertUnauthorized();

        $this->withToken($this->tokenFor($this->createUser()))
            ->getJson('/api/v1/cash/session')
            ->assertBadRequest();
    }

    public function test_company_can_open_only_one_cash_session_at_a_time(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $headers = $this->companyHeaders($owner, $company);

        $this->openCash($headers);
        $this->withHeaders($headers)
            ->postJson('/api/v1/cash/session', ['opening_amount' => '20.00'])
            ->assertConflict();

        $this->withHeaders($headers)
            ->getJson('/api/v1/cash/session')
            ->assertOk()
            ->assertJsonPath('data.opening_amount', '50.00')
            ->assertJsonPath('data.closed_at', null);

        $this->assertDatabaseCount('cash_sessions', 1);
    }

    public function test_checkout_recalculates_totals_deducts_stock_and_returns_change(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $cashSessionId = $this->openCash($headers);

        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated()
            ->assertJsonPath('data.ticket_number', sprintf('EXA-%d-%08d', $company->id, 1))
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.tax_total', '42.00')
            ->assertJsonPath('data.total', '242.00')
            ->assertJsonPath('data.cash_received', '300.00')
            ->assertJsonPath('data.change_due', '58.00')
            ->assertJsonPath('data.items.0.quantity', '2.000');

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'quantity' => '3.000',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sale_id' => 1,
            'type' => 'sale',
            'quantity_delta' => '-2.000',
        ]);
        $this->assertDatabaseHas('cash_sessions', [
            'id' => $cashSessionId,
            'expected_cash' => '292.00',
        ]);
    }

    public function test_checkout_is_idempotent_but_conflicts_when_key_payload_changes(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $this->openCash($headers);
        $payload = $this->salePayload($product);

        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $payload)
            ->assertCreated();
        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $payload)
            ->assertOk()
            ->assertJsonPath('replayed', true);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'quantity' => '3.000',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product, [
                'idempotency_key' => $payload['idempotency_key'],
                'cash_received' => '400.00',
            ]))
            ->assertConflict();
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_sale_without_open_cash_or_sufficient_stock_is_rejected_atomically(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);

        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertConflict();
        $this->assertDatabaseCount('sales', 0);

        $this->openCash($headers);
        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product, [
                'items' => [['product_id' => $product->id, 'quantity' => '6.000']],
            ]))
            ->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'quantity' => '5.000',
        ]);
    }

    public function test_server_rejects_insufficient_cash_and_client_supplied_prices(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $this->openCash($headers);

        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product, [
                'cash_received' => '100.00',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => '1.000',
                    'unit_price' => '0.01',
                ]],
            ]))
            ->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_member_can_sell_and_foreign_tenant_product_is_rejected(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($member, $company);
        $this->openCash($headers);

        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated()
            ->assertJsonPath('data.user_id', $member->id);

        $otherCompany = $this->createCompany($owner, 'Otra');
        $foreignProduct = $this->createProduct($otherCompany, 'OTRO-001');
        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($foreignProduct))
            ->assertUnprocessable();
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_cash_close_reconciles_counted_amount_and_prevents_future_sales(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $cashSessionId = $this->openCash($headers);

        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson("/api/v1/cash/session/{$cashSessionId}/close", [
                'counted_cash' => '300.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.expected_cash', '292.00')
            ->assertJsonPath('data.counted_cash', '300.00')
            ->assertJsonPath('data.difference', '8.00');

        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertConflict();
        $this->assertDatabaseHas('cash_sessions', [
            'id' => $cashSessionId,
            'closed_by' => $owner->id,
            'difference' => '8.00',
        ]);
    }

    public function test_sale_history_is_scoped_to_the_active_company(): void
    {
        $user = $this->createUser();
        $firstCompany = $this->createCompany($user, 'Primera');
        $secondCompany = $this->createCompany($user, 'Segunda');
        $product = $this->createProduct($firstCompany);
        $headers = $this->companyHeaders($user, $firstCompany);
        $this->openCash($headers);
        $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated();

        $this->withHeaders($this->companyHeaders($user, $secondCompany))
            ->getJson('/api/v1/sales')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_owner_can_void_sale_and_restore_stock_and_cash_atomically(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $cashSessionId = $this->openCash($headers);
        $saleId = $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$saleId}/void", ['reason' => 'Venta duplicada'])
            ->assertOk()
            ->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.void_reason', 'Venta duplicada')
            ->assertJsonPath('data.voided_by', $owner->id);

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'quantity' => '5.000',
        ]);
        $this->assertDatabaseHas('cash_sessions', [
            'id' => $cashSessionId,
            'expected_cash' => '50.00',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sale_id' => $saleId,
            'type' => 'sale_void',
            'quantity_delta' => '2.000',
            'reason' => 'Venta duplicada',
        ]);
        $this->assertDatabaseCount('inventory_movements', 2);

        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$saleId}/void", ['reason' => 'Reintento'])
            ->assertConflict();
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'quantity' => '5.000',
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/v1/cash/session/{$cashSessionId}/close", [
                'counted_cash' => '50.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.expected_cash', '50.00')
            ->assertJsonPath('data.difference', '0.00');
    }

    public function test_member_cannot_void_a_sale(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($member, $company);
        $this->openCash($headers);
        $saleId = $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$saleId}/void", ['reason' => 'No corresponde'])
            ->assertForbidden();

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'status' => 'completed',
            'voided_by' => null,
        ]);
    }

    public function test_sale_from_a_closed_cash_session_cannot_be_voided(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $cashSessionId = $this->openCash($headers);
        $saleId = $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated()
            ->json('data.id');
        $this->withHeaders($headers)
            ->postJson("/api/v1/cash/session/{$cashSessionId}/close", [
                'counted_cash' => '292.00',
            ])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$saleId}/void", ['reason' => 'Fuera de turno'])
            ->assertConflict();
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'status' => 'completed',
            'voided_by' => null,
        ]);
    }

    public function test_owner_can_partially_return_sale_and_reconcile_stock_and_cash(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $cashSessionId = $this->openCash($headers);
        $sale = $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated()
            ->json('data');
        $returnPayload = [
            'items' => [[
                'sale_item_id' => $sale['items'][0]['id'],
                'quantity' => '1.000',
            ]],
            'reason' => 'Producto sin usar',
            'idempotency_key' => (string) Str::uuid(),
        ];

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$sale['id']}/returns", $returnPayload)
            ->assertCreated()
            ->assertJsonPath('data.sale_id', $sale['id'])
            ->assertJsonPath('data.total', '121.00')
            ->assertJsonPath('data.items.0.quantity', '1.000')
            ->assertJsonPath('data.items.0.refund_amount', '121.00');

        $this->assertDatabaseHas('sales', [
            'id' => $sale['id'],
            'status' => 'partially_returned',
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'quantity' => '4.000',
        ]);
        $this->assertDatabaseHas('cash_sessions', [
            'id' => $cashSessionId,
            'expected_cash' => '171.00',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sale_id' => $sale['id'],
            'type' => 'sale_return',
            'quantity_delta' => '1.000',
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$sale['id']}/returns", $returnPayload)
            ->assertOk()
            ->assertJsonPath('data.total', '121.00')
            ->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('sale_returns', 1);

        $secondReturn = $returnPayload;
        $secondReturn['idempotency_key'] = (string) Str::uuid();
        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$sale['id']}/returns", $secondReturn)
            ->assertCreated()
            ->assertJsonPath('data.total', '121.00');

        $this->assertDatabaseHas('sales', [
            'id' => $sale['id'],
            'status' => 'returned',
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'quantity' => '5.000',
        ]);
        $this->assertDatabaseHas('cash_sessions', [
            'id' => $cashSessionId,
            'expected_cash' => '50.00',
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/v1/cash/session/{$cashSessionId}/close", [
                'counted_cash' => '50.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.expected_cash', '50.00')
            ->assertJsonPath('data.difference', '0.00');
    }

    public function test_sale_return_rejects_overreturn_and_members_without_manager_role(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $company = $this->createCompany($owner);
        $company->memberships()->attach($member->id, ['role' => 'member']);
        $product = $this->createProduct($company);
        $headers = $this->companyHeaders($owner, $company);
        $this->openCash($headers);
        $sale = $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated()
            ->json('data');
        $returnPayload = [
            'items' => [[
                'sale_item_id' => $sale['items'][0]['id'],
                'quantity' => '1.000',
            ]],
            'reason' => 'Devolución parcial',
            'idempotency_key' => (string) Str::uuid(),
        ];

        $this->withHeaders($this->companyHeaders($member, $company))
            ->postJson("/api/v1/sales/{$sale['id']}/returns", $returnPayload)
            ->assertForbidden();

        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$sale['id']}/returns", $returnPayload)
            ->assertCreated();

        $overreturn = $returnPayload;
        $overreturn['idempotency_key'] = (string) Str::uuid();
        $overreturn['items'][0]['quantity'] = '2.000';
        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$sale['id']}/returns", $overreturn)
            ->assertUnprocessable();

        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'quantity' => '4.000',
        ]);
    }

    public function test_sale_return_rejects_items_from_another_sale(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $firstProduct = $this->createProduct($company, 'SKU-001');
        $secondProduct = $this->createProduct($company, 'SKU-002');
        $headers = $this->companyHeaders($owner, $company);
        $this->openCash($headers);
        $firstSale = $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($firstProduct))
            ->assertCreated()
            ->json('data');
        $secondSale = $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($secondProduct))
            ->assertCreated()
            ->json('data');

        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$firstSale['id']}/returns", [
                'items' => [[
                    'sale_item_id' => $secondSale['items'][0]['id'],
                    'quantity' => '1.000',
                ]],
                'reason' => 'Artículo incorrecto',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('sale_returns', 0);
    }

    public function test_sales_report_reconciles_sales_returns_and_voids_within_tenant_and_date_range(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner, 'Empresa del reporte');
        $otherCompany = $this->createCompany($owner, 'Otra empresa');
        $product = $this->createProduct($company);
        $otherProduct = $this->createProduct($otherCompany);
        $headers = $this->companyHeaders($owner, $company);
        $this->openCash($headers);

        $returnedSale = $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product))
            ->assertCreated()
            ->json('data');
        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$returnedSale['id']}/returns", [
                'items' => [[
                    'sale_item_id' => $returnedSale['items'][0]['id'],
                    'quantity' => '1.000',
                ]],
                'reason' => 'Devolución parcial',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $voidedSaleId = $this->withHeaders($headers)
            ->postJson('/api/v1/sales', $this->salePayload($product, [
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertCreated()
            ->json('data.id');
        $this->withHeaders($headers)
            ->postJson("/api/v1/sales/{$voidedSaleId}/void", ['reason' => 'Venta duplicada'])
            ->assertOk();

        $this->openCash($this->companyHeaders($owner, $otherCompany));
        $this->withHeaders($this->companyHeaders($owner, $otherCompany))
            ->postJson('/api/v1/sales', $this->salePayload($otherProduct))
            ->assertCreated();

        $today = now()->toDateString();
        $this->withHeaders($headers)
            ->getJson("/api/v1/reports/sales?from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('data.sales_count', 1)
            ->assertJsonPath('data.sales_total', '242.00')
            ->assertJsonPath('data.returns_count', 1)
            ->assertJsonPath('data.returns_total', '121.00')
            ->assertJsonPath('data.voided_count', 1)
            ->assertJsonPath('data.voided_total', '242.00')
            ->assertJsonPath('data.net_sales', '121.00')
            ->assertJsonPath('data.average_ticket', '242.00');

        $future = now()->addDay()->toDateString();
        $this->withHeaders($headers)
            ->getJson("/api/v1/reports/sales?from={$future}&to={$future}")
            ->assertOk()
            ->assertJsonPath('data.sales_count', 0)
            ->assertJsonPath('data.net_sales', '0.00');

        $this->withHeaders($this->companyHeaders($owner, $otherCompany))
            ->getJson("/api/v1/reports/sales?from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('data.sales_count', 1)
            ->assertJsonPath('data.returns_count', 0)
            ->assertJsonPath('data.net_sales', '242.00');
    }

    public function test_sales_report_requires_a_valid_date_range_and_company_membership(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompany($owner);
        $headers = $this->companyHeaders($owner, $company);
        $this->withHeaders($headers)
            ->getJson('/api/v1/reports/sales?from=2026-10-24&to=2026-10-23')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');

        $this->withToken($this->tokenFor($owner))
            ->withHeader('X-Company-ID', '')
            ->getJson('/api/v1/reports/sales')
            ->assertBadRequest();
    }
}
