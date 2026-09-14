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
            // Fase 4C: 55 minutos de consulta + 5 de intervalo (não editável
            // pelo painel nesta fase — centralizado aqui, sem número mágico
            // espalhado pelo código).
            'duracao_consulta_min' => 55,
            'intervalo_entre_consultas_min' => 5,
            'antecedencia_minima_horas' => 2,
            'antecedencia_maxima_dias' => 60,
            'modo_confirmacao' => ConfiguracaoGlobal::MODO_AUTOMATICO,
        ])->save();
    }
}
