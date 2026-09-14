<?php

namespace Tests\Feature;

use App\Exceptions\AlteracaoDeAgendaAfetaConsultasException;
use App\Mail\ConsultaCanceladaMail;
use App\Models\AreaAtuacao;
use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\ConfiguracaoGlobal;
use App\Models\Holiday;
use App\Models\User;
use App\Services\AdminAvailabilityService;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use Database\Seeders\AvailabilityRuleSeeder;
use Database\Seeders\ConfiguracaoGlobalSeeder;
use Database\Seeders\HolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsAgendaFixtures;
use Tests\TestCase;

/**
 * Fase 4C: configuração administrativa da agenda (horários semanais,
 * exceções, feriados, bloqueios) — sempre convergindo para o mesmo
 * AvailabilityService, nunca uma segunda lógica de disponibilidade.
 */
class AdminAvailabilityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAgendaFixtures;

    private User $admin;

    private AreaAtuacao $area;

    private AdminAvailabilityService $disponibilidade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create()->fresh();
        $this->area = $this->criarAreaAtuacao();
        $this->criarExpedientePadrao();
        $this->criarConfiguracaoGlobal();
        $this->disponibilidade = app(AdminAvailabilityService::class);
    }

    private function criarConsulta(\Carbon\CarbonImmutable $data, string $horaInicio, string $horaFim): Appointment
    {
        return Appointment::query()->create([
            'area_atuacao_id' => $this->area->id,
            'nome_cliente' => 'Cliente Teste',
            'email_cliente' => 'cliente@example.com',
            'telefone_cliente' => '11999999999',
            'assunto' => 'Assunto de teste',
            'data' => $data->toDateString(),
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim,
        ])->fresh();
    }

    // =====================================================================
    // Seeders: configuração inicial (item 31 dos critérios de aceite)
    // =====================================================================

    public function test_seeder_configura_horarios_padrao_de_segunda_a_quinta(): void
    {
        $this->seed(AvailabilityRuleSeeder::class);

        $regras = AvailabilityRule::query()->get()->keyBy('day_of_week');

        foreach ([1, 2, 3, 4] as $dia) {
            $this->assertSame('09:00:00', $regras[$dia]->hora_inicio);
            $this->assertSame('18:00:00', $regras[$dia]->hora_fim);
            $this->assertTrue((bool) $regras[$dia]->ativo);
        }
    }

    public function test_seeder_configura_sexta_terminando_as_17h(): void
    {
        $this->seed(AvailabilityRuleSeeder::class);

        $sexta = AvailabilityRule::query()->where('day_of_week', 5)->firstOrFail();

        $this->assertSame('09:00:00', $sexta->hora_inicio);
        $this->assertSame('17:00:00', $sexta->hora_fim);
        $this->assertTrue((bool) $sexta->ativo);
    }

    public function test_seeder_deixa_sabado_e_domingo_sem_atendimento(): void
    {
        $this->seed(AvailabilityRuleSeeder::class);

        $this->assertDatabaseMissing('availability_rules', ['day_of_week' => 0]);
        $this->assertDatabaseMissing('availability_rules', ['day_of_week' => 6]);
    }

    public function test_seeder_configura_duracao_55_e_intervalo_5(): void
    {
        $this->seed(ConfiguracaoGlobalSeeder::class);

        $config = ConfiguracaoGlobal::atual();

        $this->assertSame(55, $config->duracao_consulta_min);
        $this->assertSame(5, $config->intervalo_entre_consultas_min);
    }

    public function test_slots_gerados_usam_55_minutos_mais_5_de_intervalo(): void
    {
        $this->criarConfiguracaoGlobal(['duracao_consulta_min' => 55, 'intervalo_entre_consultas_min' => 5]);
        $segunda = $this->proximaSegundaFeira();

        $slots = app(AvailabilityService::class)->horariosDisponiveis($this->area->id, $segunda);

        $this->assertSame(['inicio' => '09:00', 'fim' => '09:55'], $slots[0]);
        $this->assertSame(['inicio' => '10:00', 'fim' => '10:55'], $slots[1]);
        $this->assertSame(['inicio' => '11:00', 'fim' => '11:55'], $slots[2]);
    }

    public function test_seeder_cadastra_feriado_de_ferraz_de_vasconcelos(): void
    {
        $this->seed(HolidaySeeder::class);

        $this->assertDatabaseHas('holidays', [
            'mes' => 10,
            'dia' => 14,
            'nome' => 'Aniversário de Ferraz de Vasconcelos',
            'bloqueado' => 1,
        ]);
    }

    // =====================================================================
    // Segurança / autorização
    // =====================================================================

    public function test_visitante_nao_autenticado_nao_acessa_disponibilidade(): void
    {
        $resposta = $this->get(route('admin.disponibilidade.index'));

        $resposta->assertRedirect(route('admin.login'));
    }

    public function test_admin_acessa_a_pagina_de_disponibilidade(): void
    {
        $resposta = $this->actingAs($this->admin)->get(route('admin.disponibilidade.index'));

        $resposta->assertOk();
        $resposta->assertSee('Disponibilidade');
        $resposta->assertSee('Horários semanais');
    }

    public function test_rotas_de_disponibilidade_exigem_autenticacao_e_gate_de_admin(): void
    {
        foreach ([
            'admin.disponibilidade.index',
            'admin.disponibilidade.excecoes.salvar',
            'admin.disponibilidade.feriados.salvar',
        ] as $nome) {
            $middleware = Route::getRoutes()->getByName($nome)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Rota {$nome} deveria exigir autenticação.");
            $this->assertContains('can:access-admin-panel', $middleware, "Rota {$nome} deveria exigir o Gate de admin.");
            $this->assertContains('web', $middleware, "Rota {$nome} deveria estar no grupo web (CSRF).");
        }
    }

    // =====================================================================
    // Horário padrão semanal
    // =====================================================================

    public function test_admin_atualiza_horario_padrao_sem_consultas_afetadas(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('admin.disponibilidade.horarios.atualizar', 1), [
            'hora_inicio' => '08:00',
            'hora_fim' => '19:00',
            'ativo' => '1',
        ]);

        $resposta->assertRedirect();
        $resposta->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('availability_rules', [
            'day_of_week' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '19:00:00',
        ]);
    }

    public function test_atualizacao_de_horario_com_consulta_afetada_exige_confirmacao_e_nao_aplica(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $consulta = $this->criarConsulta($segunda, '16:00:00', '17:00:00');

        try {
            $this->disponibilidade->atualizarRegraSemanal(1, [
                'hora_inicio' => '09:00:00',
                'hora_fim' => '15:00:00',
                'ativo' => true,
            ], $this->admin, false);
            $this->fail('Deveria ter lançado AlteracaoDeAgendaAfetaConsultasException.');
        } catch (AlteracaoDeAgendaAfetaConsultasException $e) {
            $this->assertCount(1, $e->consultasAfetadas);
            $this->assertSame($consulta->id, $e->consultasAfetadas->first()->id);
        }

        // Nada foi persistido — rollback completo (item 7).
        $this->assertDatabaseHas('availability_rules', ['day_of_week' => 1, 'hora_fim' => '18:00:00']);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $consulta->fresh()->status);
    }

    public function test_confirmacao_de_horario_cancela_consultas_afetadas_registra_log_e_envia_email(): void
    {
        Mail::fake();
        $segunda = $this->proximaSegundaFeira();
        $consulta = $this->criarConsulta($segunda, '16:00:00', '17:00:00');

        $canceladas = $this->disponibilidade->atualizarRegraSemanal(1, [
            'hora_inicio' => '09:00:00',
            'hora_fim' => '15:00:00',
            'ativo' => true,
        ], $this->admin, true);

        $this->assertCount(1, $canceladas);
        $this->assertSame(Appointment::STATUS_CANCELLED, $consulta->fresh()->status);
        $this->assertDatabaseHas('availability_rules', ['day_of_week' => 1, 'hora_fim' => '15:00:00']);
        $this->assertDatabaseHas('admin_logs', ['acao' => 'disponibilidade.horario_padrao.atualizado']);
        Mail::assertSent(ConsultaCanceladaMail::class, fn ($mail) => $mail->hasTo($consulta->email_cliente));
    }

    public function test_consulta_nao_afetada_permanece_confirmada_apos_alteracao_de_outro_dia(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $terca = $segunda->addDay();
        $consultaTerca = $this->criarConsulta($terca, '16:00:00', '17:00:00');

        // Estreita SEGUNDA (não afeta a consulta de terça).
        $this->disponibilidade->atualizarRegraSemanal(1, [
            'hora_inicio' => '09:00:00',
            'hora_fim' => '15:00:00',
            'ativo' => true,
        ], $this->admin, false);

        $this->assertSame(Appointment::STATUS_CONFIRMED, $consultaTerca->fresh()->status);
    }

    // =====================================================================
    // Exceções de horário
    // =====================================================================

    public function test_admin_cria_excecao_de_horario(): void
    {
        $data = $this->proximaSegundaFeira()->addWeek()->toDateString();

        $resposta = $this->actingAs($this->admin)->post(route('admin.disponibilidade.excecoes.salvar'), [
            'tipo' => AvailabilityException::TIPO_HORARIO_ESPECIAL,
            'data' => $data,
            'hora_inicio' => '09:00',
            'hora_fim' => '13:00',
        ]);

        $resposta->assertRedirect();
        $this->assertDatabaseHas('availability_exceptions', [
            'data' => $data,
            'tipo' => AvailabilityException::TIPO_HORARIO_ESPECIAL,
            'hora_inicio' => '09:00:00',
            'hora_fim' => '13:00:00',
        ]);
    }

    public function test_excecao_substitui_a_regra_padrao_e_reduz_o_horario(): void
    {
        $sexta = $this->proximaSegundaFeira()->addDays(4); // sexta da mesma semana
        $this->criarExpedientePadrao(); // garante regra base 09-18 pra sexta também (id 5 já coberto)

        AvailabilityException::query()->create([
            'data' => $sexta->toDateString(),
            'tipo' => AvailabilityException::TIPO_HORARIO_ESPECIAL,
            'hora_inicio' => '09:00:00',
            'hora_fim' => '13:00:00',
        ]);

        $slots = app(AvailabilityService::class)->horariosDisponiveis($this->area->id, $sexta);

        $this->assertNotEmpty($slots);
        foreach ($slots as $slot) {
            $this->assertLessThanOrEqual('13:00', $slot['fim']);
        }
    }

    public function test_excecao_conflitante_com_consulta_existente_exige_confirmacao(): void
    {
        $sexta = $this->proximaSegundaFeira()->addDays(4);
        $consulta = $this->criarConsulta($sexta, '15:00:00', '16:00:00');

        try {
            $this->disponibilidade->salvarExcecao(null, [
                'data' => $sexta->toDateString(),
                'tipo' => AvailabilityException::TIPO_HORARIO_ESPECIAL,
                'hora_inicio' => '09:00:00',
                'hora_fim' => '13:00:00',
                'motivo' => null,
            ], $this->admin, false);
            $this->fail('Deveria ter lançado AlteracaoDeAgendaAfetaConsultasException.');
        } catch (AlteracaoDeAgendaAfetaConsultasException $e) {
            $this->assertCount(1, $e->consultasAfetadas);
        }

        $this->assertDatabaseMissing('availability_exceptions', ['data' => $sexta->toDateString()]);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $consulta->fresh()->status);
    }

    public function test_remocao_de_excecao_restaura_o_calculo_normal(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $excecao = AvailabilityException::query()->create([
            'data' => $segunda->toDateString(),
            'tipo' => AvailabilityException::TIPO_HORARIO_ESPECIAL,
            'hora_inicio' => '09:00:00',
            'hora_fim' => '11:00:00',
        ]);

        $availability = app(AvailabilityService::class);
        $antesDaRemocao = $availability->horariosDisponiveis($this->area->id, $segunda);
        $this->assertLessThanOrEqual('11:00', collect($antesDaRemocao)->last()['fim']);

        $this->disponibilidade->removerExcecao($excecao, $this->admin);

        $depoisDaRemocao = $availability->horariosDisponiveis($this->area->id, $segunda);
        $this->assertGreaterThan(count($antesDaRemocao), count($depoisDaRemocao));
        $this->assertDatabaseHas('admin_logs', ['acao' => 'disponibilidade.excecao.removido']);
    }

    // =====================================================================
    // Bloqueios de dia inteiro
    // =====================================================================

    public function test_dia_bloqueado_nao_aparece_como_disponivel(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $this->disponibilidade->salvarExcecao(null, [
            'data' => $segunda->toDateString(),
            'tipo' => AvailabilityException::TIPO_FECHADO_TOTAL,
            'motivo' => 'Compromisso pessoal',
        ], $this->admin, false);

        $slots = app(AvailabilityService::class)->horariosDisponiveis($this->area->id, $segunda);

        $this->assertEmpty($slots);
    }

    public function test_bloqueio_com_consulta_existente_exige_confirmacao(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $consulta = $this->criarConsulta($segunda, '10:00:00', '11:00:00');

        try {
            $this->disponibilidade->salvarExcecao(null, [
                'data' => $segunda->toDateString(),
                'tipo' => AvailabilityException::TIPO_FECHADO_TOTAL,
                'motivo' => null,
            ], $this->admin, false);
            $this->fail('Deveria ter lançado AlteracaoDeAgendaAfetaConsultasException.');
        } catch (AlteracaoDeAgendaAfetaConsultasException $e) {
            $this->assertCount(1, $e->consultasAfetadas);
            $this->assertSame($consulta->id, $e->consultasAfetadas->first()->id);
        }

        $this->assertDatabaseMissing('availability_exceptions', ['data' => $segunda->toDateString()]);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $consulta->fresh()->status);
    }

    public function test_confirmacao_de_bloqueio_cancela_consulta_preserva_registro_e_notifica_cliente(): void
    {
        Mail::fake();
        $segunda = $this->proximaSegundaFeira();
        $consulta = $this->criarConsulta($segunda, '10:00:00', '11:00:00');

        $this->disponibilidade->salvarExcecao(null, [
            'data' => $segunda->toDateString(),
            'tipo' => AvailabilityException::TIPO_FECHADO_TOTAL,
            'motivo' => 'Viagem',
        ], $this->admin, true);

        $consulta->refresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $consulta->status);
        $this->assertSame('Cancelado por alteração administrativa da agenda.', $consulta->cancelled_reason);
        // Nunca apagada fisicamente — o registro histórico continua no banco.
        $this->assertDatabaseHas('appointments', ['id' => $consulta->id, 'nome_cliente' => 'Cliente Teste']);
        Mail::assertSent(ConsultaCanceladaMail::class, fn ($mail) => $mail->hasTo($consulta->email_cliente));
        $this->assertDatabaseHas('admin_logs', ['acao' => 'disponibilidade.bloqueio.criado']);
    }

    public function test_admin_remove_bloqueio_sem_afetar_consultas(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $bloqueio = AvailabilityException::query()->create([
            'data' => $segunda->toDateString(),
            'tipo' => AvailabilityException::TIPO_FECHADO_TOTAL,
        ]);

        $this->disponibilidade->removerExcecao($bloqueio, $this->admin);

        $this->assertDatabaseMissing('availability_exceptions', ['id' => $bloqueio->id]);
        $this->assertDatabaseHas('admin_logs', ['acao' => 'disponibilidade.bloqueio.removido']);
    }

    // =====================================================================
    // Feriados
    // =====================================================================

    public function test_admin_cria_feriado_com_consulta_existente_exige_confirmacao(): void
    {
        // Usa uma data futura fixa e específica pra não depender do dia da semana atual.
        $dataAlvo = \Carbon\CarbonImmutable::now()->addMonths(2)->startOfMonth()->addDays(10);
        AvailabilityRule::query()->updateOrCreate(
            ['day_of_week' => $dataAlvo->dayOfWeek],
            ['hora_inicio' => '09:00:00', 'hora_fim' => '18:00:00', 'ativo' => true]
        );
        $consulta = $this->criarConsulta($dataAlvo, '10:00:00', '11:00:00');

        try {
            $this->disponibilidade->salvarFeriado(null, [
                'nome' => 'Feriado de teste',
                'mes' => $dataAlvo->month,
                'dia' => $dataAlvo->day,
                'ano' => $dataAlvo->year,
                'recorrente_anual' => false,
            ], $this->admin, false);
            $this->fail('Deveria ter lançado AlteracaoDeAgendaAfetaConsultasException.');
        } catch (AlteracaoDeAgendaAfetaConsultasException $e) {
            $this->assertCount(1, $e->consultasAfetadas);
        }

        $this->assertDatabaseMissing('holidays', ['nome' => 'Feriado de teste']);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $consulta->fresh()->status);
    }

    public function test_confirmacao_de_feriado_cancela_consulta_e_bloqueia_a_data(): void
    {
        Mail::fake();
        $dataAlvo = \Carbon\CarbonImmutable::now()->addMonths(2)->startOfMonth()->addDays(10);
        AvailabilityRule::query()->updateOrCreate(
            ['day_of_week' => $dataAlvo->dayOfWeek],
            ['hora_inicio' => '09:00:00', 'hora_fim' => '18:00:00', 'ativo' => true]
        );
        $consulta = $this->criarConsulta($dataAlvo, '10:00:00', '11:00:00');

        $this->disponibilidade->salvarFeriado(null, [
            'nome' => 'Feriado de teste',
            'mes' => $dataAlvo->month,
            'dia' => $dataAlvo->day,
            'ano' => $dataAlvo->year,
            'recorrente_anual' => false,
        ], $this->admin, true);

        $this->assertDatabaseHas('holidays', ['nome' => 'Feriado de teste']);
        $this->assertSame(Appointment::STATUS_CANCELLED, $consulta->fresh()->status);
        Mail::assertSent(ConsultaCanceladaMail::class);

        $slots = app(AvailabilityService::class)->horariosDisponiveis($this->area->id, $dataAlvo);
        $this->assertEmpty($slots);
    }

    public function test_admin_remove_feriado(): void
    {
        $feriado = Holiday::query()->create([
            'nome' => 'Feriado removível', 'mes' => 8, 'dia' => 20, 'recorrente_anual' => true, 'bloqueado' => true,
        ]);

        $this->disponibilidade->removerFeriado($feriado, $this->admin);

        $this->assertDatabaseMissing('holidays', ['id' => $feriado->id]);
        $this->assertDatabaseHas('admin_logs', ['acao' => 'disponibilidade.feriado.removido']);
    }

    // =====================================================================
    // E-mail
    // =====================================================================

    public function test_falha_no_envio_de_email_nao_impede_o_cancelamento_nem_quebra_a_operacao(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Falha simulada de SMTP'));

        $segunda = $this->proximaSegundaFeira();
        $consulta = $this->criarConsulta($segunda, '10:00:00', '11:00:00');

        $canceladas = $this->disponibilidade->salvarExcecao(null, [
            'data' => $segunda->toDateString(),
            'tipo' => AvailabilityException::TIPO_FECHADO_TOTAL,
            'motivo' => null,
        ], $this->admin, true);

        // A falha de e-mail não impediu o cancelamento nem lançou exceção.
        $this->assertCount(1, $canceladas);
        $this->assertSame(Appointment::STATUS_CANCELLED, $consulta->fresh()->status);
    }

    // =====================================================================
    // Concorrência (garantia prática: bloqueio confirmado impede nova reserva)
    // =====================================================================

    public function test_apos_bloqueio_confirmado_o_agendamento_publico_e_rejeitado(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $this->disponibilidade->salvarExcecao(null, [
            'data' => $segunda->toDateString(),
            'tipo' => AvailabilityException::TIPO_FECHADO_TOTAL,
            'motivo' => null,
        ], $this->admin, false);

        $this->expectException(\App\Exceptions\HorarioIndisponivelException::class);
        app(AppointmentService::class)->agendar([
            'area_atuacao_id' => $this->area->id,
            'nome_cliente' => 'Cliente Novo',
            'email_cliente' => 'novo@example.com',
            'telefone_cliente' => '11988887777',
            'assunto' => 'Assunto',
            'data' => $segunda->toDateString(),
            'hora_inicio' => '10:00',
        ]);
    }

    // =====================================================================
    // Fase 4C.2 — bug real da auditoria 4C.1: duplicação de feriado em
    // duplo-submit, e a unicidade anual x específico correspondente.
    // =====================================================================

    public function test_criar_feriado_anual_duas_vezes_nao_duplica(): void
    {
        $dados = ['nome' => 'Aniversário de Ferraz de Vasconcelos', 'mes' => 10, 'dia' => 14, 'ano' => null, 'recorrente_anual' => true];

        $this->disponibilidade->salvarFeriado(null, $dados, $this->admin, false);
        $this->disponibilidade->salvarFeriado(null, $dados, $this->admin, false);

        $this->assertSame(1, Holiday::query()->where('mes', 10)->where('dia', 14)->whereNull('ano')->count());
    }

    public function test_criar_feriado_especifico_duas_vezes_nao_duplica(): void
    {
        $dados = ['nome' => 'Recesso especial', 'mes' => 12, 'dia' => 25, 'ano' => 2026, 'recorrente_anual' => false];

        $this->disponibilidade->salvarFeriado(null, $dados, $this->admin, false);
        $this->disponibilidade->salvarFeriado(null, $dados, $this->admin, false);

        $this->assertSame(1, Holiday::query()->where('mes', 12)->where('dia', 25)->where('ano', 2026)->count());
    }

    /**
     * Decisão registrada na auditoria 4C.1 (item 6): o próprio
     * scopeParaData() de Holiday já trata "recorrente anual" e "ano
     * específico" como condições independentes (OR) — um feriado anual e um
     * específico para a mesma data já podiam coexistir antes da correção, e
     * a nova constraint (mes, dia, ano_normalizado) preserva exatamente essa
     * coexistência, só bloqueando duas linhas com a mesma chave exata.
     */
    public function test_feriado_anual_e_feriado_especifico_coexistem_na_mesma_data(): void
    {
        $this->disponibilidade->salvarFeriado(null, [
            'nome' => 'Feriado anual', 'mes' => 6, 'dia' => 20, 'ano' => null, 'recorrente_anual' => true,
        ], $this->admin, false);

        $this->disponibilidade->salvarFeriado(null, [
            'nome' => 'Feriado específico 2026', 'mes' => 6, 'dia' => 20, 'ano' => 2026, 'recorrente_anual' => false,
        ], $this->admin, false);

        $this->assertSame(2, Holiday::query()->where('mes', 6)->where('dia', 20)->count());
    }

    public function test_duplo_submit_de_feriado_confirmado_nao_gera_estado_inconsistente(): void
    {
        Mail::fake();
        $dataAlvo = \Carbon\CarbonImmutable::now()->addMonths(2)->startOfMonth()->addDays(10);
        AvailabilityRule::query()->updateOrCreate(
            ['day_of_week' => $dataAlvo->dayOfWeek],
            ['hora_inicio' => '09:00:00', 'hora_fim' => '18:00:00', 'ativo' => true]
        );
        $consulta = $this->criarConsulta($dataAlvo, '10:00:00', '11:00:00');
        $dados = ['nome' => 'Feriado duplo-submit', 'mes' => $dataAlvo->month, 'dia' => $dataAlvo->day, 'ano' => $dataAlvo->year, 'recorrente_anual' => false];

        // Simula duplo-submit: a MESMA requisição já confirmada, enviada duas
        // vezes (duplo clique, ou retry de rede do POST com
        // confirmar_cancelamento=1).
        $this->disponibilidade->salvarFeriado(null, $dados, $this->admin, true);
        $this->disponibilidade->salvarFeriado(null, $dados, $this->admin, true);

        $this->assertSame(1, Holiday::query()->where('mes', $dataAlvo->month)->where('dia', $dataAlvo->day)->where('ano', $dataAlvo->year)->count());
        $this->assertSame(Appointment::STATUS_CANCELLED, $consulta->fresh()->status);
        // Nenhum estado parcial e nenhuma notificação duplicada: a consulta
        // só estava ativa na 1ª chamada — a 2ª não encontra nada para
        // cancelar de novo, então só 1 e-mail é enviado no total.
        Mail::assertSent(ConsultaCanceladaMail::class, 1);
    }

    public function test_feriado_com_conflito_sem_confirmacao_faz_rollback_completo(): void
    {
        $dataAlvo = \Carbon\CarbonImmutable::now()->addMonths(2)->startOfMonth()->addDays(11);
        AvailabilityRule::query()->updateOrCreate(
            ['day_of_week' => $dataAlvo->dayOfWeek],
            ['hora_inicio' => '09:00:00', 'hora_fim' => '18:00:00', 'ativo' => true]
        );
        $consulta = $this->criarConsulta($dataAlvo, '10:00:00', '11:00:00');

        try {
            $this->disponibilidade->salvarFeriado(null, [
                'nome' => 'Feriado conflito', 'mes' => $dataAlvo->month, 'dia' => $dataAlvo->day, 'ano' => $dataAlvo->year, 'recorrente_anual' => false,
            ], $this->admin, false);
            $this->fail('Deveria ter lançado AlteracaoDeAgendaAfetaConsultasException.');
        } catch (AlteracaoDeAgendaAfetaConsultasException $e) {
            $this->assertCount(1, $e->consultasAfetadas);
        }

        $this->assertDatabaseMissing('holidays', ['nome' => 'Feriado conflito']);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $consulta->fresh()->status);
    }

    public function test_feriado_com_conflito_confirmado_apos_rollback_cancela_e_notifica_uma_unica_vez(): void
    {
        Mail::fake();
        $dataAlvo = \Carbon\CarbonImmutable::now()->addMonths(2)->startOfMonth()->addDays(12);
        AvailabilityRule::query()->updateOrCreate(
            ['day_of_week' => $dataAlvo->dayOfWeek],
            ['hora_inicio' => '09:00:00', 'hora_fim' => '18:00:00', 'ativo' => true]
        );
        $consulta = $this->criarConsulta($dataAlvo, '10:00:00', '11:00:00');
        $dados = ['nome' => 'Feriado conflito confirmado', 'mes' => $dataAlvo->month, 'dia' => $dataAlvo->day, 'ano' => $dataAlvo->year, 'recorrente_anual' => false];

        // 1ª requisição, sem confirmação: rollback completo, nada persistido.
        try {
            $this->disponibilidade->salvarFeriado(null, $dados, $this->admin, false);
            $this->fail('Deveria ter lançado AlteracaoDeAgendaAfetaConsultasException.');
        } catch (AlteracaoDeAgendaAfetaConsultasException) {
            // esperado
        }

        // 2ª requisição (mesmo formulário, agora confirmado): aplica de verdade.
        $canceladas = $this->disponibilidade->salvarFeriado(null, $dados, $this->admin, true);

        $this->assertCount(1, $canceladas);
        $this->assertSame(Appointment::STATUS_CANCELLED, $consulta->fresh()->status);
        $this->assertDatabaseHas('holidays', ['nome' => 'Feriado conflito confirmado']);
        Mail::assertSent(ConsultaCanceladaMail::class, 1);
    }

    // =====================================================================
    // Fase 4C.2 — lock adquirido antes de decidir sobre consultas afetadas
    // =====================================================================

    public function test_lock_de_agenda_e_adquirido_para_a_data_alvo_do_feriado(): void
    {
        $dataAlvo = \Carbon\CarbonImmutable::now()->addMonths(3)->startOfMonth()->addDays(5);

        $this->disponibilidade->salvarFeriado(null, [
            'nome' => 'Feriado lock', 'mes' => $dataAlvo->month, 'dia' => $dataAlvo->day, 'ano' => $dataAlvo->year, 'recorrente_anual' => false,
        ], $this->admin, false);

        $this->assertDatabaseHas('agenda_locks', ['data' => $dataAlvo->toDateString()]);
    }

    public function test_lock_de_agenda_e_adquirido_para_as_datas_afetadas_pela_regra_semanal(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $this->disponibilidade->atualizarRegraSemanal(1, [
            'hora_inicio' => '09:00:00', 'hora_fim' => '18:00:00', 'ativo' => true,
        ], $this->admin, false);

        $this->assertDatabaseHas('agenda_locks', ['data' => $segunda->toDateString()]);
    }
}
