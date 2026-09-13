<?php

namespace Tests\Feature;

use App\Models\AreaAtuacao;
use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\Holiday;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAgendaFixtures;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAgendaFixtures;

    private AvailabilityService $service;

    private AreaAtuacao $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AvailabilityService::class);
        $this->area = $this->criarAreaAtuacao();
        $this->criarExpedientePadrao();
        $this->criarConfiguracaoGlobal();
    }

    public function test_horario_comercial_gera_slots_dentro_do_expediente(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $slots = $this->service->horariosDisponiveis($this->area->id, $segunda);

        $this->assertNotEmpty($slots);
        $this->assertSame('09:00', $slots[0]['inicio']);
        $this->assertSame('17:00', end($slots)['inicio']);
    }

    public function test_sabado_e_bloqueado_por_padrao(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $sabado = $segunda->addDays(5);

        $this->assertSame(6, $sabado->dayOfWeek);
        $this->assertEmpty($this->service->horariosDisponiveis($this->area->id, $sabado));
    }

    public function test_domingo_e_bloqueado_por_padrao(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $domingo = $segunda->addDays(6);

        $this->assertSame(0, $domingo->dayOfWeek);
        $this->assertEmpty($this->service->horariosDisponiveis($this->area->id, $domingo));
    }

    public function test_feriado_bloqueia_o_dia_inteiro(): void
    {
        $segunda = $this->proximaSegundaFeira();

        Holiday::query()->create([
            'nome' => 'Feriado de teste',
            'mes' => $segunda->month,
            'dia' => $segunda->day,
            'ano' => null,
            'recorrente_anual' => true,
            'bloqueado' => true,
        ]);

        $this->assertEmpty($this->service->horariosDisponiveis($this->area->id, $segunda));
    }

    public function test_data_bloqueada_manualmente_fica_sem_horarios(): void
    {
        $segunda = $this->proximaSegundaFeira();

        AvailabilityException::query()->create([
            'data' => $segunda->toDateString(),
            'tipo' => AvailabilityException::TIPO_FECHADO_TOTAL,
            'motivo' => 'Compromisso pessoal',
        ]);

        $this->assertEmpty($this->service->horariosDisponiveis($this->area->id, $segunda));
    }

    public function test_bloqueio_parcial_remove_apenas_o_intervalo_afetado(): void
    {
        $segunda = $this->proximaSegundaFeira();

        AvailabilityException::query()->create([
            'data' => $segunda->toDateString(),
            'tipo' => AvailabilityException::TIPO_FECHADO_PARCIAL,
            'hora_inicio' => '12:00:00',
            'hora_fim' => '13:00:00',
            'motivo' => 'Almoço',
        ]);

        $slots = $this->service->horariosDisponiveis($this->area->id, $segunda);
        $horariosInicio = array_column($slots, 'inicio');

        $this->assertNotContains('12:00', $horariosInicio);
        $this->assertContains('11:00', $horariosInicio);
        $this->assertContains('13:00', $horariosInicio);
    }

    public function test_horario_ja_agendado_nao_aparece_como_disponivel(): void
    {
        $segunda = $this->proximaSegundaFeira();

        Appointment::query()->create([
            'area_atuacao_id' => $this->area->id,
            'nome_cliente' => 'Cliente Teste',
            'email_cliente' => 'cliente@example.com',
            'telefone_cliente' => '11999999999',
            'data' => $segunda->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fim' => '11:00:00',
        ]);

        $slots = $this->service->horariosDisponiveis($this->area->id, $segunda);
        $horariosInicio = array_column($slots, 'inicio');

        $this->assertNotContains('10:00', $horariosInicio);
        $this->assertContains('09:00', $horariosInicio);
        $this->assertContains('11:00', $horariosInicio);
    }

    public function test_duracao_da_consulta_e_configuravel_e_muda_a_quantidade_de_slots(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $slots60min = $this->service->horariosDisponiveis($this->area->id, $segunda);

        $config = $this->criarConfiguracaoGlobal(['duracao_consulta_min' => 30]);
        $slots30min = $this->service->horariosDisponiveis($this->area->id, $segunda, $config);

        $this->assertCount(9, $slots60min);
        $this->assertCount(18, $slots30min);
    }

    public function test_intervalo_entre_consultas_e_respeitado(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $config = $this->criarConfiguracaoGlobal([
            'duracao_consulta_min' => 60,
            'intervalo_entre_consultas_min' => 15,
        ]);

        $slots = $this->service->horariosDisponiveis($this->area->id, $segunda, $config);

        $this->assertSame('09:00', $slots[0]['inicio']);
        $this->assertSame('10:00', $slots[0]['fim']);
        $this->assertSame('10:15', $slots[1]['inicio']);
    }
}
