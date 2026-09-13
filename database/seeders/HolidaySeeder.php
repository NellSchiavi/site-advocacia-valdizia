<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * Feriados nacionais fixos (recorrência anual automática). Feriados móveis
 * (Carnaval, Sexta-feira Santa, Corpus Christi) mudam de data todo ano e
 * devem ser cadastrados manualmente pela administradora na tela de Feriados
 * — não são chutados aqui para não fixar uma data errada no banco.
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $feriados = [
            ['nome' => 'Confraternização Universal', 'mes' => 1, 'dia' => 1],
            ['nome' => 'Tiradentes', 'mes' => 4, 'dia' => 21],
            ['nome' => 'Dia do Trabalho', 'mes' => 5, 'dia' => 1],
            ['nome' => 'Independência do Brasil', 'mes' => 9, 'dia' => 7],
            ['nome' => 'Nossa Senhora Aparecida', 'mes' => 10, 'dia' => 12],
            ['nome' => 'Finados', 'mes' => 11, 'dia' => 2],
            ['nome' => 'Proclamação da República', 'mes' => 11, 'dia' => 15],
            ['nome' => 'Natal', 'mes' => 12, 'dia' => 25],
        ];

        foreach ($feriados as $feriado) {
            Holiday::query()->updateOrCreate(
                ['mes' => $feriado['mes'], 'dia' => $feriado['dia'], 'ano' => null],
                ['nome' => $feriado['nome'], 'recorrente_anual' => true, 'bloqueado' => true]
            );
        }
    }
}
