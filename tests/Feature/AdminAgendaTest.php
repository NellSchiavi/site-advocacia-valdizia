<?php

namespace Tests\Feature;

use App\Models\AreaAtuacao;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsAgendaFixtures;
use Tests\TestCase;

/**
 * Fase 4B: agenda administrativa. Cobre só autorização, listagem/filtros,
 * detalhe e as três ações de status (cancelar/concluir/não compareceu) —
 * disponibilidade, feriados, bloqueios e configuração de horários chegam em
 * fases futuras e não são tocados aqui.
 */
class AdminAgendaTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAgendaFixtures;

    private User $admin;

    private AreaAtuacao $area;

    protected function setUp(): void
    {
        parent::setUp();

        // ->fresh(): "role" nasce do default do schema, não é mass-assignable
        // (mesma ressalva documentada em AdminAuthTest).
        $this->admin = User::factory()->create()->fresh();
        $this->area = $this->criarAreaAtuacao();
        $this->criarExpedientePadrao();
        $this->criarConfiguracaoGlobal();
    }

    protected function tearDown(): void
    {
        // Alguns testes usam travelTo() (regra de transição temporal da
        // Fase 4B.2) — garante que o "agora" congelado não vaze entre testes.
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Move o relógio de teste para o instante exato em que a consulta
     * termina (data + hora_fim) — o limite mínimo em que COMPLETED/NO_SHOW
     * passam a ser permitidos pela regra do AppointmentService.
     */
    private function viajarParaFimDaConsulta(Appointment $consulta): void
    {
        $this->travelTo(CarbonImmutable::parse("{$consulta->data->toDateString()} {$consulta->hora_fim}"));
    }

    private function criarConsulta(array $atributos = []): Appointment
    {
        $segunda = $this->proximaSegundaFeira();

        return Appointment::query()->create(array_merge([
            'area_atuacao_id' => $this->area->id,
            'nome_cliente' => 'Cliente Teste',
            'email_cliente' => 'cliente@example.com',
            'telefone_cliente' => '11999999999',
            'observacao' => null,
            'data' => $segunda->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fim' => '11:00:00',
        ], $atributos))->fresh();
    }

    public function test_visitante_nao_autenticado_nao_acessa_a_agenda(): void
    {
        $resposta = $this->get(route('admin.agenda'));

        $resposta->assertRedirect(route('admin.login'));
    }

    public function test_admin_acessa_a_agenda(): void
    {
        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda'));

        $resposta->assertOk();
        $resposta->assertSee('Agenda');
    }

    public function test_agenda_mostra_consultas_reais(): void
    {
        $consulta = $this->criarConsulta(['nome_cliente' => 'Maria da Silva']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', ['data' => $consulta->data->toDateString()]));

        $resposta->assertOk();
        $resposta->assertSee('Maria da Silva');
    }

    public function test_agenda_vazia_funciona(): void
    {
        $diaSemConsulta = $this->proximaSegundaFeira()->addWeeks(4);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', ['data' => $diaSemConsulta->toDateString()]));

        $resposta->assertOk();
        $resposta->assertSee('Nenhuma consulta');
    }

    public function test_filtro_por_data(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $consultaA = $this->criarConsulta(['nome_cliente' => 'Cliente Dia A', 'data' => $segunda->toDateString()]);
        $this->criarConsulta(['nome_cliente' => 'Cliente Dia B', 'data' => $segunda->addWeek()->toDateString(), 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', ['data' => $consultaA->data->toDateString()]));

        $resposta->assertSee('Cliente Dia A');
        $resposta->assertDontSee('Cliente Dia B');
    }

    public function test_filtro_por_status(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $confirmada = $this->criarConsulta(['nome_cliente' => 'Cliente Confirmado']);
        $cancelada = $this->criarConsulta(['nome_cliente' => 'Cliente Cancelado', 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);
        $cancelada->status = Appointment::STATUS_CANCELLED;
        $cancelada->save();

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'status' => Appointment::STATUS_CANCELLED,
        ]));

        $resposta->assertSee('Cliente Cancelado');
        $resposta->assertDontSee('Cliente Confirmado');
    }

    public function test_filtro_por_area(): void
    {
        $outraArea = AreaAtuacao::query()->create(['slug' => 'direito-civil-teste', 'nome' => 'Direito Civil', 'ativo' => true]);
        $segunda = $this->proximaSegundaFeira();

        $daAreaOriginal = $this->criarConsulta(['nome_cliente' => 'Cliente Área Original']);
        $this->criarConsulta([
            'nome_cliente' => 'Cliente Outra Área',
            'area_atuacao_id' => $outraArea->id,
            'hora_inicio' => '11:00:00',
            'hora_fim' => '12:00:00',
        ]);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'area_atuacao_id' => $this->area->id,
        ]));

        $resposta->assertSee('Cliente Área Original');
        $resposta->assertDontSee('Cliente Outra Área');
        $this->assertNotNull($daAreaOriginal);
    }

    public function test_detalhe_da_consulta_retorna_dados_completos(): void
    {
        $consulta = $this->criarConsulta([
            'email_cliente' => 'detalhe@example.com',
            'telefone_cliente' => '11977776666',
            'observacao' => 'Dúvida específica.',
        ]);

        $resposta = $this->actingAs($this->admin)->getJson(route('admin.agenda.show', $consulta));

        $resposta->assertOk();
        $resposta->assertJson([
            'id' => $consulta->id,
            'nome_cliente' => 'Cliente Teste',
            'email_cliente' => 'detalhe@example.com',
            'telefone_cliente' => '11977776666',
            'area' => $this->area->nome,
            'observacao' => 'Dúvida específica.',
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_cancelamento_autorizado_altera_o_status(): void
    {
        $consulta = $this->criarConsulta();

        $resposta = $this->actingAs($this->admin)->post(route('admin.agenda.cancelar', $consulta), [
            'motivo' => 'Cliente desmarcou por telefone.',
        ]);

        $resposta->assertRedirect();
        $consulta->refresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $consulta->status);
        $this->assertSame('Cliente desmarcou por telefone.', $consulta->cancelled_reason);
    }

    public function test_consulta_cancelada_aparece_com_status_atualizado_na_agenda(): void
    {
        $consulta = $this->criarConsulta();
        $this->actingAs($this->admin)->post(route('admin.agenda.cancelar', $consulta));

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', ['data' => $consulta->data->toDateString()]));

        $resposta->assertSee(Appointment::STATUS_LABELS[Appointment::STATUS_CANCELLED]);
    }

    public function test_conclusao_autorizada_altera_o_status(): void
    {
        $consulta = $this->criarConsulta();
        $this->viajarParaFimDaConsulta($consulta);

        $resposta = $this->actingAs($this->admin)->post(route('admin.agenda.concluir', $consulta));

        $resposta->assertRedirect();
        $this->assertSame(Appointment::STATUS_COMPLETED, $consulta->fresh()->status);
    }

    public function test_conclusao_de_consulta_futura_e_rejeitada(): void
    {
        // Regra da Fase 4B.2: COMPLETED só é permitido depois que a consulta
        // termina — sem viajar no tempo, "criarConsulta()" agenda no futuro.
        $consulta = $this->criarConsulta();

        $resposta = $this->actingAs($this->admin)->post(route('admin.agenda.concluir', $consulta));

        $resposta->assertSessionHasErrors('acao');
        $this->assertSame(Appointment::STATUS_CONFIRMED, $consulta->fresh()->status);
    }

    public function test_nao_comparecimento_autorizado_altera_o_status(): void
    {
        $consulta = $this->criarConsulta();
        $this->viajarParaFimDaConsulta($consulta);

        $resposta = $this->actingAs($this->admin)->post(route('admin.agenda.nao-compareceu', $consulta));

        $resposta->assertRedirect();
        $this->assertSame(Appointment::STATUS_NO_SHOW, $consulta->fresh()->status);
    }

    public function test_nao_comparecimento_de_consulta_futura_e_rejeitado(): void
    {
        $consulta = $this->criarConsulta();

        $resposta = $this->actingAs($this->admin)->post(route('admin.agenda.nao-compareceu', $consulta));

        $resposta->assertSessionHasErrors('acao');
        $this->assertSame(Appointment::STATUS_CONFIRMED, $consulta->fresh()->status);
    }

    public function test_rotas_da_agenda_exigem_autenticacao_e_gate_de_admin(): void
    {
        foreach (['admin.agenda', 'admin.agenda.show', 'admin.agenda.cancelar', 'admin.agenda.concluir', 'admin.agenda.nao-compareceu'] as $nome) {
            $middleware = Route::getRoutes()->getByName($nome)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Rota {$nome} deveria exigir autenticação.");
            $this->assertContains('can:access-admin-panel', $middleware, "Rota {$nome} deveria exigir o Gate de admin.");
        }
    }

    public function test_rotas_de_acao_estao_protegidas_por_csrf(): void
    {
        // O client de teste do Laravel desativa a checagem de CSRF durante
        // os testes (PreventRequestForgery::runningUnitTests()) — por isso a
        // proteção real é verificada aqui pela presença do middleware "web"
        // (que inclui a validação de CSRF), e não tentando de fato simular
        // um POST sem token dentro do próprio PHPUnit.
        foreach (['admin.agenda.cancelar', 'admin.agenda.concluir', 'admin.agenda.nao-compareceu'] as $nome) {
            $middleware = Route::getRoutes()->getByName($nome)->gatherMiddleware();

            $this->assertContains('web', $middleware, "Rota {$nome} deveria estar no grupo web (CSRF).");
        }
    }

    public function test_tentativa_de_manipular_status_arbitrariamente_e_rejeitada(): void
    {
        $consulta = $this->criarConsulta();

        // Mesmo enviando um "status" arbitrário no corpo do POST, o backend
        // nunca lê esse campo — a ação em si (cancelar) é que define o
        // resultado (item 7 da Fase 4B).
        $this->actingAs($this->admin)->post(route('admin.agenda.cancelar', $consulta), [
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        $this->assertSame(Appointment::STATUS_CANCELLED, $consulta->fresh()->status);
    }

    public function test_operacao_invalida_para_o_status_atual_e_rejeitada(): void
    {
        $consulta = $this->criarConsulta();
        $consulta->status = Appointment::STATUS_COMPLETED;
        $consulta->save();

        $resposta = $this->actingAs($this->admin)->post(route('admin.agenda.cancelar', $consulta));

        $resposta->assertSessionHasErrors('acao');
        $this->assertSame(Appointment::STATUS_COMPLETED, $consulta->fresh()->status);
    }

    public function test_dados_pessoais_nao_aparecem_em_urls_nem_na_listagem(): void
    {
        $consulta = $this->criarConsulta([
            'email_cliente' => 'privado@example.com',
            'telefone_cliente' => '11955554444',
        ]);

        $urlDetalhe = route('admin.agenda.show', $consulta);
        $this->assertStringNotContainsString('privado@example.com', $urlDetalhe);
        $this->assertStringNotContainsString('11955554444', $urlDetalhe);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', ['data' => $consulta->data->toDateString()]));

        // Minimização (item 8): a listagem nunca embute e-mail/telefone —
        // eles só saem do banco no endpoint de detalhe, buscado sob demanda.
        $resposta->assertDontSee('privado@example.com');
        $resposta->assertDontSee('11955554444');
    }
}
