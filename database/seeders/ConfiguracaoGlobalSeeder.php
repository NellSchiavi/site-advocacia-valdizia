<?php

namespace Database\Seeders;

use App\Models\ConfiguracaoGlobal;
use Illuminate\Database\Seeder;

class ConfiguracaoGlobalSeeder extends Seeder
{
    public function run(): void
    {
        $config = ConfiguracaoGlobal::atual();
        $config->fill([
            'duracao_consulta_min' => 60,
            'intervalo_entre_consultas_min' => 0,
            'antecedencia_minima_horas' => 2,
            'antecedencia_maxima_dias' => 60,
            'modo_confirmacao' => ConfiguracaoGlobal::MODO_AUTOMATICO,
        ])->save();
    }
}
