<?php

namespace Tests\Feature\Database;

use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_catalog_seeder_is_idempotent_and_populates_public_catalog(): void
    {
        $this->seed(\Database\Seeders\PlanCatalogSeeder::class);
        $this->seed(\Database\Seeders\PlanCatalogSeeder::class);

        $this->assertDatabaseCount('plans', 3);
        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.currency', 'ARS')
            ->assertJsonPath('data.0.is_active', null);

        $this->assertTrue(Plan::query()->where('code', 'inicial')->exists());
    }
}
