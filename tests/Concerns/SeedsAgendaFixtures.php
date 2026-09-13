<?php

namespace Tests\Concerns;

use App\Models\AreaAtuacao;
use App\Models\AvailabilityRule;
use App\Models\ConfiguracaoGlobal;

/**
 * Fixtures mínimas usadas pelos testes de agenda/agendamento: uma área de
 * atuação, o expediente padrão (segunda a sexta, 09:00–18:00 — igual ao
 * seeder de produção) e a configuração global default.
 */
trait SeedsAgendaFixtures
{
    protected function criarAreaAtuacao(): AreaAtuacao
    {
        return AreaAtuacao::query()->create([
            'slug' => 'direito-previdenciario',
            'nome' => 'Direito Previdenciário',
            'ativo' => true,
        ]);
    }

    protected function criarExpedientePadrao(): void
    {
        foreach ([1, 2, 3, 4, 5] as $dia) {
            AvailabilityRule::query()->create([
                'day_of_week' => $dia,
                'hora_inicio' => '09:00:00',
                'hora_fim' => '18:00:00',
                'ativo' => true,
            ]);
        }
    }

    protected function criarConfiguracaoGlobal(array $atributos = []): ConfiguracaoGlobal
    {
        $config = ConfiguracaoGlobal::atual();
        $config->fill(array_merge([
            'duracao_consulta_min' => 60,
            'intervalo_entre_consultas_min' => 0,
            'antecedencia_minima_horas' => 0,
            'antecedencia_maxima_dias' => 365,
            'modo_confirmacao' => ConfiguracaoGlobal::MODO_AUTOMATICO,
        ], $atributos))->save();

        return $config->fresh();
    }

    /**
     * Retorna a próxima ocorrência de uma segunda-feira a partir de hoje
     * (garante um dia dentro do expediente padrão, nunca no passado).
     */
    protected function proximaSegundaFeira(): \Carbon\CarbonImmutable
    {
        $data = \Carbon\CarbonImmutable::now()->addDay()->startOfDay();

        while ($data->dayOfWeek !== 1) {
            $data = $data->addDay();
        }

        return $data;
    }
}
