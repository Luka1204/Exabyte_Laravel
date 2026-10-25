<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use LogicException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException(
                'DatabaseSeeder only installs demo data in local and testing environments.'
            );
        }

        $this->call([
            AdminUserSeeder::class,
            PlanCatalogSeeder::class,
            DemoCommerceSeeder::class,
        ]);
    }
}
