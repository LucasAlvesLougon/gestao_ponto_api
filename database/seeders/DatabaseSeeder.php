<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@ponto.com'],
            [
                'name' => 'Usuário Demo',
                'password' => \Illuminate\Support\Facades\Hash::make('senha1234'),
                'timezone' => 'America/Sao_Paulo',
            ]
        );
    }
}
