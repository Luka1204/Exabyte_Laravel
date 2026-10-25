<?php

namespace Database\Seeders;

use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan;
use Illuminate\Database\Seeder;
use LogicException;

class PlanCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException(
                'Illustrative plans may only be seeded in local and testing environments.'
            );
        }

        foreach ($this->plans() as $plan) {
            Plan::query()->firstOrCreate(
                ['code' => $plan['code']],
                $plan,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        $allFeatures = [
            'catalog' => true,
            'inventory' => true,
            'pos' => true,
            'sales_reports' => true,
            'inventory_reports' => true,
            'cash_reports' => true,
        ];

        return [
            [
                'code' => 'inicial',
                'name' => 'Inicial',
                'description' => 'Plan de demostración local. Precio ilustrativo, no es una oferta comercial.',
                'benefits' => [
                    'Punto de venta e inventario',
                    'Reportes de ventas, stock y caja',
                    'Hasta 100 productos',
                ],
                'currency' => 'ARS',
                'monthly_amount' => '9900.00',
                'annual_amount' => '99000.00',
                'limits' => [
                    'users' => 1,
                    'branches' => 1,
                    'cash_registers' => 1,
                    'products' => 100,
                ],
                'entitlements' => $allFeatures,
                'trial_days' => 0,
                'grace_days' => 3,
                'terms_version' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'comercio',
                'name' => 'Comercio',
                'description' => 'Plan de demostración local. Precio ilustrativo, no es una oferta comercial.',
                'benefits' => [
                    'Todas las funciones del plan Inicial',
                    'Hasta 500 productos y 3 usuarios',
                    'Ideal para probar la operación diaria',
                ],
                'currency' => 'ARS',
                'monthly_amount' => '19900.00',
                'annual_amount' => '199000.00',
                'limits' => [
                    'users' => 3,
                    'branches' => 1,
                    'cash_registers' => 2,
                    'products' => 500,
                ],
                'entitlements' => $allFeatures,
                'trial_days' => 0,
                'grace_days' => 5,
                'terms_version' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'gestion',
                'name' => 'Gestión',
                'description' => 'Plan de demostración local. Precio ilustrativo, no es una oferta comercial.',
                'benefits' => [
                    'Todas las funciones comerciales disponibles',
                    'Hasta 20 usuarios y 5000 productos',
                    'Capacidad ampliada para pruebas',
                ],
                'currency' => 'ARS',
                'monthly_amount' => '39900.00',
                'annual_amount' => '399000.00',
                'limits' => [
                    'users' => 20,
                    'branches' => 5,
                    'cash_registers' => 10,
                    'products' => 5000,
                ],
                'entitlements' => $allFeatures,
                'trial_days' => 0,
                'grace_days' => 7,
                'terms_version' => 1,
                'is_active' => true,
            ],
        ];
    }
}
