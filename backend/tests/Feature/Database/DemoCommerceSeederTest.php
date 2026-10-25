<?php

namespace Tests\Feature\Database;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoCommerceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_builds_consistent_idempotent_commerce_data_without_fake_billing(): void
    {
        $owner = User::query()->create([
            'name' => 'Demo Owner',
            'email' => (string) env('ERP_INITIAL_ADMIN_EMAIL', 'demo@exabyte.local'),
            'password' => Hash::make('not-used-in-demo-seeder'),
        ]);
        $company = Company::query()->create([
            'name' => (string) env('ERP_INITIAL_COMPANY_NAME', 'Exabyte Demo'),
        ]);
        $company->memberships()->attach($owner->id, ['role' => 'owner']);

        $this->seed(\Database\Seeders\DemoCommerceSeeder::class);
        $this->seed(\Database\Seeders\DemoCommerceSeeder::class);

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('inventory_balances', 2);
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->assertDatabaseCount('cash_sessions', 1);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertDatabaseCount('sale_return_items', 1);
        $this->assertSame(19.0, (float) DB::table('inventory_balances')
            ->where('product_id', DB::table('products')->where('sku', 'DEMO-CAFE-250')->value('id'))
            ->value('quantity'));
        $this->assertDatabaseHas('cash_sessions', [
            'company_id' => $company->id,
            'expected_cash' => '112100.00',
            'difference' => '-100.00',
        ]);
        $this->assertDatabaseHas('subscription_access_exemptions', [
            'company_id' => $company->id,
            'reason' => 'local_demo_data',
        ]);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertDatabaseCount('provider_events', 0);
        $this->assertDatabaseCount('platform_admins', 0);
    }
}
