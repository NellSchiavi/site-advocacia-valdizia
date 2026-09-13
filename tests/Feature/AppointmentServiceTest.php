<?php

namespace Tests\Feature;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\AreaAtuacao;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAgendaFixtures;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAgendaFixtures;

    private AppointmentService $service;

    private AreaAtuacao $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AppointmentService::class);
        $this->area = $this->criarAreaAtuacao();
        $this->criarExpedientePadrao();
        $this->criarConfiguracaoGlobal();
    }

    private function dadosBase(\Carbon\CarbonImmutable $data, string $horaInicio): array
    {
        return [
            'area_atuacao_id' => $this->area->id,
            'nome_cliente' => 'Cliente Teste',
            'email_cliente' => 'cliente@example.com',
            'telefone_cliente' => '11999999999',
            'observacao' => null,
            'data' => $data->toDateString(),
            'hora_inicio' => $horaInicio,
        ];
    }

    public function test_agenda_com_sucesso_em_horario_disponivel_e_confirma_automaticamente(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $appointment = $this->service->agendar($this->dadosBase($segunda, '10:00'));

        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame('10:00', $appointment->hora_inicio);
        $this->assertSame('11:00', $appointment->hora_fim);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'hora_inicio' => '10:00:00',
            'hora_fim' => '11:00:00',
        ]);
    }

    public function test_rejeita_sobreposicao_total_mesmo_horario_exato(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $this->service->agendar($this->dadosBase($segunda, '14:00'));

        $this->expectException(HorarioIndisponivelException::class);
        $this->service->agendar($this->dadosBase($segunda, '14:00'));
    }

    public function test_rejeita_sobreposicao_parcial_mesmo_com_duracao_diferente(): void
    {
        // Consulta A: 14:00–15:00 (duração de 60min). Em seguida a duração
        // configurada muda para 30min (simula duração variável) e tenta-se
        // reservar 14:30–15:00, que sobrepõe parcialmente a consulta A.
        // A remoção de horários ocupados trabalha em minutos corridos (não
        // em grade fixa), então o conflito é barrado mesmo com grade diferente.
        $segunda = $this->proximaSegundaFeira();

        $this->service->agendar($this->dadosBase($segunda, '14:00'));

        $this->criarConfiguracaoGlobal(['duracao_consulta_min' => 30]);

        $this->expectException(HorarioIndisponivelException::class);
        $this->service->agendar($this->dadosBase($segunda, '14:30'));
    }

    public function test_formula_de_sobreposicao_de_intervalo_e_simetrica(): void
    {
        // Prova direta da fórmula usada pelo AppointmentService/AvailabilityService:
        // existente.inicio < novo.fim E existente.fim > novo.inicio.
        $segunda = $this->proximaSegundaFeira();

        $this->service->agendar($this->dadosBase($segunda, '14:00')); // 14:00–15:00

        $sobrepoeParcialAntes = Appointment::query()->naData($segunda)->ativos()
            ->sobrepondo('13:30', '14:30')->exists();
        $sobrepoeParcialDepois = Appointment::query()->naData($segunda)->ativos()
            ->sobrepondo('14:30', '15:30')->exists();
        $naoSobrepoeAdjacenteAntes = Appointment::query()->naData($segunda)->ativos()
            ->sobrepondo('13:00', '14:00')->exists();
        $naoSobrepoeAdjacenteDepois = Appointment::query()->naData($segunda)->ativos()
            ->sobrepondo('15:00', '16:00')->exists();

        $this->assertTrue($sobrepoeParcialAntes);
        $this->assertTrue($sobrepoeParcialDepois);
        $this->assertFalse($naoSobrepoeAdjacenteAntes);
        $this->assertFalse($naoSobrepoeAdjacenteDepois);
    }

    public function test_permite_consultas_adjacentes_sem_sobreposicao(): void
    {
        // Consulta A: 14:00–15:00. Consulta B: 15:00–16:00 deve ser permitida.
        $segunda = $this->proximaSegundaFeira();

        $this->service->agendar($this->dadosBase($segunda, '14:00'));
        $segundaConsulta = $this->service->agendar($this->dadosBase($segunda, '15:00'));

        $this->assertSame(Appointment::STATUS_CONFIRMED, $segundaConsulta->status);
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_cancelamento_libera_o_horario_para_novo_agendamento(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $appointment = $this->service->agendar($this->dadosBase($segunda, '10:00'));
        $this->service->cancelar($appointment, 'Cliente desmarcou');

        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);

        // O mesmo horário deve poder ser reservado de novo agora que está livre.
        $novo = $this->service->agendar($this->dadosBase($segunda, '10:00'));

        $this->assertSame(Appointment::STATUS_CONFIRMED, $novo->status);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CANCELLED,
        ]);
    }

    public function test_modo_manual_cria_agendamento_como_pendente(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $this->criarConfiguracaoGlobal([
            'modo_confirmacao' => \App\Models\ConfiguracaoGlobal::MODO_MANUAL,
        ]);

        $appointment = $this->service->agendar($this->dadosBase($segunda, '10:00'));

        $this->assertSame(Appointment::STATUS_PENDING, $appointment->status);
    }

    public function test_area_de_atuacao_inexistente_e_rejeitada_na_validacao(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $dados = $this->dadosBase($segunda, '10:00');
        $dados['area_atuacao_id'] = 99999;

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->agendar($dados);
    }

    public function test_area_de_atuacao_ativa_permite_o_agendamento(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $appointment = $this->service->agendar($this->dadosBase($segunda, '10:00'));

        $this->assertSame($this->area->id, $appointment->area_atuacao_id);
    }

    public function test_area_de_atuacao_inativa_e_rejeitada_na_validacao(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $areaInativa = AreaAtuacao::query()->create([
            'slug' => 'direito-inativo',
            'nome' => 'Área Inativa',
            'ativo' => false,
        ]);

        $dados = $this->dadosBase($segunda, '10:00');
        $dados['area_atuacao_id'] = $areaInativa->id;

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->agendar($dados);
    }
}
