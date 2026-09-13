<?php

namespace Database\Seeders;

use App\Models\AvailabilityRule;
use Illuminate\Database\Seeder;

/**
 * Configuração inicial definida no PLANO-TECNICO.md: segunda a sexta,
 * 09:00–18:00; sábado e domingo permanecem sem regra (= bloqueados por
 * padrão). Tudo isso é reconfigurável depois pelo painel administrativo —
 * nenhum desses valores fica hardcoded em código além deste seeder inicial.
 */
class AvailabilityRuleSeeder extends Seeder
{
    public function run(): void
    {
        // 0 = domingo ... 6 = sábado (convenção do Carbon/dayOfWeek)
        $diasUteis = [1, 2, 3, 4, 5]; // segunda a sexta

        foreach ($diasUteis as $dia) {
            AvailabilityRule::query()->updateOrCreate(
                ['day_of_week' => $dia, 'hora_inicio' => '09:00:00', 'hora_fim' => '18:00:00'],
                ['ativo' => true]
            );
        }
    }
}
