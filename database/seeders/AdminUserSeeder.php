<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cria a única administradora prevista para a Fase 1 (Dra. Valdizia Melo).
 *
 * IMPORTANTE: a senha abaixo é um valor de DESENVOLVIMENTO, nunca a senha
 * real da cliente. Ela deve ser trocada manualmente (via tinker, ou pela
 * futura tela "Perfil" do painel) antes de qualquer uso fora do ambiente
 * local/homologação.
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
