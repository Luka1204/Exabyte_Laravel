<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\SubscriptionAccessExemption;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class DemoCommerceSeeder extends Seeder
{
    private const SALE_KEY = '00000000-0000-4000-8000-000000000101';

    private const RETURN_KEY = '00000000-0000-4000-8000-000000000102';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException(
                'Demo commerce data may only be seeded in local and testing environments.'
            );
        }

        $email = (string) env('ERP_INITIAL_ADMIN_EMAIL', 'demo@exabyte.local');
        $companyName = (string) env('ERP_INITIAL_COMPANY_NAME', 'Exabyte Demo');
        $owner = User::query()->where('email', $email)->first();
        $company = Company::query()->where('name', $companyName)->first();

        if (! $owner || ! $company || ! $company->memberships()->whereKey($owner->id)->exists()) {
            throw new RuntimeException(
                'Run AdminUserSeeder before DemoCommerceSeeder to create the configured owner and company.'
            );
        }

        DB::transaction(function () use ($owner, $company): void {
            SubscriptionAccessExemption::query()->firstOrCreate(
                ['company_id' => $company->id],
                [
                    'reason' => 'local_demo_data',
                    'expires_at' => null,
                    'created_at' => now(),
                ],
            );

            $category = Category::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Productos de muestra'],
                ['description' => 'Catálogo ficticio para pruebas locales.'],
            );

            $products = collect([
                [
                    'name' => 'Café molido 250 g',
                    'sku' => 'DEMO-CAFE-250',
                    'barcode' => '7790000000101',
                    'price' => '10000.00',
                    'tax_rate' => '21.00',
                ],
                [
                    'name' => 'Taza térmica',
                    'sku' => 'DEMO-TAZA-001',
                    'barcode' => '7790000000102',
                    'price' => '8500.00',
                    'tax_rate' => '21.00',
                ],
            ])->mapWithKeys(function (array $attributes) use ($category, $company): array {
                $product = Product::query()->firstOrCreate(
                    ['company_id' => $company->id, 'sku' => $attributes['sku']],
                    [
                        ...$attributes,
                        'category_id' => $category->id,
                        'is_active' => true,
                    ],
                );

                return [$product->sku => $product];
            });

            $coffee = $products['DEMO-CAFE-250'];
            $mug = $products['DEMO-TAZA-001'];
            $openedAt = now()->subDays(2)->startOfDay()->setTime(9, 0);
            $sale = DB::table('sales')
                ->where('company_id', $company->id)
                ->where('idempotency_key', self::SALE_KEY)
                ->first();
            $cashSession = $sale
                ? DB::table('cash_sessions')->where('id', $sale->cash_session_id)->first()
                : null;

            if (! $cashSession) {
                $cashSessionId = DB::table('cash_sessions')->insertGetId([
                    'company_id' => $company->id,
                    'opened_by' => $owner->id,
                    'closed_by' => $owner->id,
                    'opening_amount' => '100000.00',
                    'expected_cash' => '112100.00',
                    'counted_cash' => '112000.00',
                    'difference' => '-100.00',
                    'opened_at' => $openedAt,
                    'closed_at' => $openedAt->copy()->addHours(8),
                    'created_at' => $openedAt,
                    'updated_at' => $openedAt,
                ]);
            } else {
                $cashSessionId = $cashSession->id;
            }

            $saleId = $sale?->id ?? DB::table('sales')->insertGetId([
                'company_id' => $company->id,
                'cash_session_id' => $cashSessionId,
                'user_id' => $owner->id,
                'idempotency_key' => self::SALE_KEY,
                'request_hash' => hash('sha256', 'exabyte-local-demo-sale-v1'),
                'ticket_number' => 'DEMO-0001',
                'status' => 'completed',
                'subtotal' => '20000.00',
                'tax_total' => '4200.00',
                'total' => '24200.00',
                'cash_received' => '25000.00',
                'change_due' => '800.00',
                'created_at' => $openedAt->copy()->addHours(2),
                'updated_at' => $openedAt->copy()->addHours(2),
            ]);

            $saleItemId = DB::table('sale_items')
                ->where('sale_id', $saleId)
                ->where('product_id', $coffee->id)
                ->value('id');
            if (! $saleItemId) {
                $saleItemId = DB::table('sale_items')->insertGetId([
                    'sale_id' => $saleId,
                    'product_id' => $coffee->id,
                    'product_name' => $coffee->name,
                    'sku' => $coffee->sku,
                    'quantity' => '2.000',
                    'unit_price' => '10000.00',
                    'tax_rate' => '21.00',
                    'line_subtotal' => '20000.00',
                    'line_tax' => '4200.00',
                    'line_total' => '24200.00',
                    'created_at' => $openedAt->copy()->addHours(2),
                    'updated_at' => $openedAt->copy()->addHours(2),
                ]);
            }

            $return = DB::table('sale_returns')
                ->where('company_id', $company->id)
                ->where('idempotency_key', self::RETURN_KEY)
                ->first();
            $returnId = $return?->id ?? DB::table('sale_returns')->insertGetId([
                'company_id' => $company->id,
                'sale_id' => $saleId,
                'cash_session_id' => $cashSessionId,
                'refunded_by' => $owner->id,
                'idempotency_key' => self::RETURN_KEY,
                'request_hash' => hash('sha256', 'exabyte-local-demo-return-v1'),
                'reason' => 'Devolución de muestra local.',
                'total' => '12100.00',
                'created_at' => $openedAt->copy()->addHours(3),
                'updated_at' => $openedAt->copy()->addHours(3),
            ]);

            $this->insertIfMissing('sale_return_items', [
                'sale_return_id' => $returnId,
                'sale_item_id' => $saleItemId,
            ], [
                'product_id' => $coffee->id,
                'quantity' => '1.000',
                'refund_amount' => '12100.00',
                'created_at' => $openedAt->copy()->addHours(3),
                'updated_at' => $openedAt->copy()->addHours(3),
            ]);

            $this->seedInventory(
                $company->id,
                $owner->id,
                $coffee,
                '20.000',
                '19.000',
                $saleId,
                $openedAt,
            );
            $this->seedInventory(
                $company->id,
                $owner->id,
                $mug,
                '10.000',
                '10.000',
                null,
                $openedAt,
            );
        });
    }

    private function seedInventory(
        int $companyId,
        int $userId,
        Product $product,
        string $openingStock,
        string $finalStock,
        ?int $saleId,
        Carbon $openedAt
    ): void {
        $this->insertIfMissing('inventory_balances', [
            'company_id' => $companyId,
            'product_id' => $product->id,
        ], [
            'quantity' => $finalStock,
            'created_at' => $openedAt,
            'updated_at' => $openedAt,
        ]);

        $openingKey = $product->sku === 'DEMO-CAFE-250'
            ? '00000000-0000-4000-8000-000000000201'
            : '00000000-0000-4000-8000-000000000202';

        $this->insertIfMissing('inventory_movements', [
            'company_id' => $companyId,
            'idempotency_key' => $openingKey,
        ], [
            'product_id' => $product->id,
            'user_id' => $userId,
            'sale_id' => null,
            'type' => 'adjustment',
            'quantity_delta' => $openingStock,
            'balance_after' => $openingStock,
            'reason' => 'Existencia inicial de muestra local.',
            'created_at' => $openedAt,
        ]);

        if ($product->sku !== 'DEMO-CAFE-250' || $saleId === null) {
            return;
        }

        $this->insertIfMissing('inventory_movements', [
            'company_id' => $companyId,
            'idempotency_key' => '00000000-0000-4000-8000-000000000203',
        ], [
            'product_id' => $product->id,
            'user_id' => $userId,
            'sale_id' => $saleId,
            'type' => 'sale',
            'quantity_delta' => '-2.000',
            'balance_after' => '18.000',
            'reason' => 'Venta de muestra local DEMO-0001.',
            'created_at' => $openedAt->copy()->addHours(2),
        ]);

        $this->insertIfMissing('inventory_movements', [
            'company_id' => $companyId,
            'idempotency_key' => '00000000-0000-4000-8000-000000000204',
        ], [
            'product_id' => $product->id,
            'user_id' => $userId,
            'sale_id' => $saleId,
            'type' => 'sale_return',
            'quantity_delta' => '1.000',
            'balance_after' => '19.000',
            'reason' => 'Devolución de muestra local DEMO-0001.',
            'created_at' => $openedAt->copy()->addHours(3),
        ]);
    }

    /**
     * @param array<string, int|string> $identity
     * @param array<string, mixed> $attributes
     */
    private function insertIfMissing(
        string $table,
        array $identity,
        array $attributes
    ): void {
        if (DB::table($table)->where($identity)->exists()) {
            return;
        }

        DB::table($table)->insert([...$identity, ...$attributes]);
    }
}
