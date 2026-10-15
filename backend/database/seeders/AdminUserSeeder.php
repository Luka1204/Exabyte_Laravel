<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Administrador',
                'password' => Hash::make(
                    (string) env('ERP_INITIAL_ADMIN_PASSWORD')
                ),
            ]
        );
    }
}
