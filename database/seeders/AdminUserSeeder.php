<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cria um usuário administrador para desenvolvimento local.
 *
 * IMPORTANTE: a senha abaixo é um valor de desenvolvimento e não deve ser
 * usada fora do ambiente local/homologação.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'dev@valdiziamelo.local'],
            [
                'name' => 'Dra. Valdizia Melo (usuário de desenvolvimento)',
                'password' => 'TrocarEssaSenha123!',
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
