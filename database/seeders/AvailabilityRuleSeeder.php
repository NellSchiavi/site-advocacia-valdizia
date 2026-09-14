<?php

namespace Database\Seeders;

use App\Models\AvailabilityRule;
use Illuminate\Database\Seeder;

/**
 * Configuração inicial definida na Fase 4C: segunda a quinta 09:00–18:00,
 * sexta 09:00–17:00; sábado e domingo permanecem sem regra (= bloqueados
 * por padrão). Tudo isso é reconfigurável depois pelo painel administrativo
 * (Fase 4C) — nenhum desses valores fica hardcoded em código além deste
 * seeder inicial.
 */
class AvailabilityRuleSeeder extends Seeder
{
    public function run(): void
    {
        // 0 = domingo ... 6 = sábado (convenção do Carbon/dayOfWeek)
        $segundaAQuinta = [1, 2, 3, 4];

        foreach ($segundaAQuinta as $dia) {
            AvailabilityRule::query()->updateOrCreate(
                ['day_of_week' => $dia],
                ['hora_inicio' => '09:00:00', 'hora_fim' => '18:00:00', 'ativo' => true]
            );
        }

        AvailabilityRule::query()->updateOrCreate(
            ['day_of_week' => 5],
            ['hora_inicio' => '09:00:00', 'hora_fim' => '17:00:00', 'ativo' => true]
        );
    }
}
