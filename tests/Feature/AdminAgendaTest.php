<?php

namespace Tests\Feature;

use App\Mail\ConsultaCanceladaMail;
use App\Models\AreaAtuacao;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
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
            'assunto' => 'Assunto de teste',
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

    /**
     * Auditoria de overflow (pós Fase 5): o card do modal de detalhe
     * precisa de altura máxima + rolagem interna própria, senão conteúdo
     * longo (observação extensa, ações + confirmação abertas) pode
     * ultrapassar a viewport sem nenhuma forma de alcançar o restante —
     * regressão real encontrada e corrigida nesta auditoria. PHPUnit não
     * renderiza CSS/JS, então este teste só garante que as classes
     * responsáveis pelo comportamento não sejam removidas por engano; o
     * comportamento visual em si foi validado via Playwright.
     */
    public function test_modal_de_detalhe_tem_altura_maxima_e_rolagem_interna(): void
    {
        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda'));

        $resposta->assertOk();
        $resposta->assertSee('max-h-[calc(100vh-2rem)]', false);
        $resposta->assertSee('overflow-y-auto', false);
        // Cabeçalho (título + botão Fechar) precisa continuar fixo durante
        // a rolagem interna, para que o Fechar nunca dependa de rolar de volta.
        $resposta->assertSee('sticky top-0', false);
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

    // ==========================================================
    // Fase 4F — XSS e SQL injection (regressão)
    // ==========================================================

    public function test_payload_html_no_nome_do_cliente_nao_e_interpretado_como_html_na_agenda(): void
    {
        $payload = '<script>alert(1)</script>';
        $consulta = $this->criarConsulta(['nome_cliente' => $payload]);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', ['data' => $consulta->data->toDateString()]));

        // Blade escapa por padrão ({{ }}): a versão ESCAPADA deve aparecer...
        $resposta->assertSee($payload);
        // ...e a tag <script> bruta, capaz de executar, nunca deve aparecer.
        $resposta->assertDontSee($payload, false);
    }

    public function test_payload_html_no_detalhe_da_consulta_e_devolvido_como_json_seguro(): void
    {
        $payload = '<script>alert(1)</script>';
        $consulta = $this->criarConsulta([
            'nome_cliente' => $payload,
            'assunto' => $payload,
            'observacao' => $payload,
        ]);

        $resposta = $this->actingAs($this->admin)->getJson(route('admin.agenda.show', $consulta));

        // response()->json() sempre serializa como string de dados, nunca
        // como HTML executável — o valor volta intacto (não é HTML nesta
        // camada), e quem renderiza no admin usa x-text (Alpine), que define
        // textContent, nunca innerHTML (ver agenda/index.blade.php).
        $resposta->assertJson(['nome_cliente' => $payload, 'assunto' => $payload, 'observacao' => $payload]);
        $resposta->assertHeader('Content-Type', 'application/json');
    }

    public function test_busca_com_caracteres_especiais_de_sql_e_tratada_como_texto_literal(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta(['nome_cliente' => 'Cliente Normal']);

        // Sem dígitos de propósito: um payload com "1" seria capturado pela
        // normalização de telefone (item 6 da Fase 4D) e combinaria com
        // qualquer telefone que contenha "11" — falso positivo já
        // encontrado ao escrever este teste, não uma falha de segurança.
        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => "' OR 'x'='x",
        ]));

        // Nenhum erro de SQL (a query builder/bindings tratam a busca como
        // texto literal) e nenhum resultado — a string não corresponde a
        // nenhum nome/e-mail/telefone real.
        $resposta->assertOk();
        $resposta->assertDontSee('Cliente Normal');
        $resposta->assertSee('Nenhuma consulta encontrada para essa busca.');
    }

    // ==========================================================
    // Fase 4E — e-mail de cancelamento manual
    // ==========================================================

    public function test_cancelamento_manual_dispara_email_de_cancelamento(): void
    {
        Mail::fake();
        $consulta = $this->criarConsulta();

        $this->actingAs($this->admin)->post(route('admin.agenda.cancelar', $consulta), [
            'motivo' => 'Cliente desmarcou por telefone.',
        ]);

        Mail::assertSent(ConsultaCanceladaMail::class, fn ($mail) => $mail->hasTo($consulta->email_cliente)
            && $mail->appointment->id === $consulta->id);
    }

    public function test_cancelamento_manual_nao_exclui_fisicamente_a_consulta(): void
    {
        $consulta = $this->criarConsulta();

        $this->actingAs($this->admin)->post(route('admin.agenda.cancelar', $consulta));

        $this->assertDatabaseHas('appointments', [
            'id' => $consulta->id,
            'status' => Appointment::STATUS_CANCELLED,
        ]);
    }

    public function test_payload_html_no_nome_do_cliente_nao_e_interpretado_no_email_de_cancelamento(): void
    {
        $payload = '<script>alert(1)</script>';
        $consulta = $this->criarConsulta(['nome_cliente' => $payload])->load('areaAtuacao');

        $html = (new \App\Mail\ConsultaCanceladaMail($consulta))->render();

        $this->assertStringContainsString(e($payload), $html);
        $this->assertStringNotContainsString($payload, $html);
    }

    public function test_falha_no_envio_do_email_nao_desfaz_cancelamento_manual(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Falha simulada de envio.'));
        $consulta = $this->criarConsulta();

        $resposta = $this->actingAs($this->admin)->post(route('admin.agenda.cancelar', $consulta));

        $resposta->assertRedirect();
        $this->assertSame(Appointment::STATUS_CANCELLED, $consulta->fresh()->status);
    }

    // ==========================================================
    // Fase 4D — busca por nome, e-mail ou telefone
    // ==========================================================

    public function test_busca_por_nome_completo(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta(['nome_cliente' => 'Maria da Silva']);
        $this->criarConsulta(['nome_cliente' => 'João Souza', 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'Maria da Silva',
        ]));

        $resposta->assertSee('Maria da Silva');
        $resposta->assertDontSee('João Souza');
    }

    public function test_busca_por_nome_parcial(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta(['nome_cliente' => 'Maria da Silva']);
        $this->criarConsulta(['nome_cliente' => 'João Souza', 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'mar',
        ]));

        $resposta->assertSee('Maria da Silva');
        $resposta->assertDontSee('João Souza');
    }

    public function test_busca_por_nome_sem_resultado(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta(['nome_cliente' => 'Maria da Silva']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'Cliente Inexistente',
        ]));

        $resposta->assertDontSee('Maria da Silva');
        $resposta->assertSee('Nenhuma consulta encontrada para essa busca.');
    }

    public function test_busca_por_email_completo(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $alvo = $this->criarConsulta(['nome_cliente' => 'Cliente Alvo', 'email_cliente' => 'alvo@example.com']);
        $this->criarConsulta(['nome_cliente' => 'Cliente Outro', 'email_cliente' => 'outro@example.com', 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'alvo@example.com',
        ]));

        $resposta->assertSee('Cliente Alvo');
        $resposta->assertDontSee('Cliente Outro');
        $this->assertNotNull($alvo);
    }

    public function test_busca_por_parte_do_email(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta(['nome_cliente' => 'Cliente Alvo', 'email_cliente' => 'joao@exemplo.com']);
        $this->criarConsulta(['nome_cliente' => 'Cliente Outro', 'email_cliente' => 'maria@outro.com', 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'joao@exemplo',
        ]));

        $resposta->assertSee('Cliente Alvo');
        $resposta->assertDontSee('Cliente Outro');
    }

    public function test_busca_por_email_sem_resultado(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta(['nome_cliente' => 'Cliente Alvo', 'email_cliente' => 'alvo@example.com']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'inexistente@example.com',
        ]));

        $resposta->assertDontSee('Cliente Alvo');
        $resposta->assertSee('Nenhuma consulta encontrada para essa busca.');
    }

    public function test_busca_por_telefone_no_formato_armazenado(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta(['nome_cliente' => 'Cliente Alvo', 'telefone_cliente' => '11988887777']);
        $this->criarConsulta(['nome_cliente' => 'Cliente Outro', 'telefone_cliente' => '11933332222', 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => '11988887777',
        ]));

        $resposta->assertSee('Cliente Alvo');
        $resposta->assertDontSee('Cliente Outro');
    }

    public function test_busca_por_telefone_com_mascara_encontra_telefone_sem_mascara(): void
    {
        // telefone_cliente não tem máscara imposta em nenhuma camada — a
        // normalização (remover espaço/parênteses/hífen dos dois lados)
        // precisa funcionar mesmo quando o valor salvo é só dígitos.
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta(['nome_cliente' => 'Cliente Alvo', 'telefone_cliente' => '11988887777']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => '(11) 98888-7777',
        ]));

        $resposta->assertSee('Cliente Alvo');
    }

    public function test_busca_por_telefone_sem_mascara_encontra_telefone_com_mascara(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta(['nome_cliente' => 'Cliente Alvo', 'telefone_cliente' => '(11) 98888-7777']);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => '11988887777',
        ]));

        $resposta->assertSee('Cliente Alvo');
    }

    public function test_busca_combinada_com_data(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $alvo = $this->criarConsulta(['nome_cliente' => 'Maria da Silva', 'data' => $segunda->toDateString()]);
        $this->criarConsulta(['nome_cliente' => 'Maria da Silva', 'data' => $segunda->addWeek()->toDateString()]);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $alvo->data->toDateString(),
            'busca' => 'Maria',
        ]));

        $resposta->assertSee('Maria da Silva');
        $resposta->assertSee('Consultas (1)');
    }

    public function test_busca_combinada_com_status(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $confirmada = $this->criarConsulta(['nome_cliente' => 'Maria da Silva']);
        $cancelada = $this->criarConsulta(['nome_cliente' => 'Maria Souza', 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);
        $cancelada->status = Appointment::STATUS_CANCELLED;
        $cancelada->save();

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'Maria',
            'status' => Appointment::STATUS_CANCELLED,
        ]));

        $resposta->assertSee('Maria Souza');
        $resposta->assertDontSee('Maria da Silva');
        $this->assertNotNull($confirmada);
    }

    public function test_busca_combinada_com_area(): void
    {
        $outraArea = AreaAtuacao::query()->create(['slug' => 'direito-civil-teste-4d', 'nome' => 'Direito Civil', 'ativo' => true]);
        $segunda = $this->proximaSegundaFeira();

        $this->criarConsulta(['nome_cliente' => 'Maria da Silva']);
        $this->criarConsulta([
            'nome_cliente' => 'Maria Souza',
            'area_atuacao_id' => $outraArea->id,
            'hora_inicio' => '11:00:00',
            'hora_fim' => '12:00:00',
        ]);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'Maria',
            'area_atuacao_id' => $this->area->id,
        ]));

        $resposta->assertSee('Maria da Silva');
        $resposta->assertDontSee('Maria Souza');
    }

    public function test_busca_combinada_com_data_status_e_area(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $alvo = $this->criarConsulta(['nome_cliente' => 'Maria da Silva']);
        $alvo->status = Appointment::STATUS_CONFIRMED;
        $alvo->save();

        // Mesmo dia/área, nome parecido, status diferente — não deve aparecer.
        $statusErrado = $this->criarConsulta(['nome_cliente' => 'Maria Pendente', 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);
        $statusErrado->status = Appointment::STATUS_CANCELLED;
        $statusErrado->save();

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'Maria',
            'status' => Appointment::STATUS_CONFIRMED,
            'area_atuacao_id' => $this->area->id,
        ]));

        $resposta->assertSee('Maria da Silva');
        $resposta->assertDontSee('Maria Pendente');
    }

    public function test_busca_nao_cria_endpoint_publico(): void
    {
        $resposta = $this->get(route('admin.agenda', ['busca' => 'qualquer']));

        $resposta->assertRedirect(route('admin.login'));
    }

    public function test_busca_respeita_minimizacao_de_dados_na_listagem(): void
    {
        $segunda = $this->proximaSegundaFeira();
        $this->criarConsulta([
            'nome_cliente' => 'Maria da Silva',
            'email_cliente' => 'maria@example.com',
            'telefone_cliente' => '11988887777',
        ]);

        $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
            'data' => $segunda->toDateString(),
            'busca' => 'Maria',
        ]));

        $resposta->assertSee('Maria da Silva');
        $resposta->assertDontSee('maria@example.com');
        $resposta->assertDontSee('11988887777');
    }

    public function test_busca_nao_quebra_regressao_de_status_filtraveis(): void
    {
        $segunda = $this->proximaSegundaFeira();

        $pendente = $this->criarConsulta(['nome_cliente' => 'Consulta Um']);
        $pendente->status = Appointment::STATUS_PENDING;
        $pendente->save();

        // Atribuição direta (não via AppointmentService) — assim como os
        // demais testes de regressão desta classe, não depende da regra
        // temporal da Fase 4B.2 nem precisa de travelTo().
        $concluida = $this->criarConsulta(['nome_cliente' => 'Consulta Dois', 'hora_inicio' => '11:00:00', 'hora_fim' => '12:00:00']);
        $concluida->status = Appointment::STATUS_COMPLETED;
        $concluida->save();

        $naoCompareceu = $this->criarConsulta(['nome_cliente' => 'Consulta Tres', 'hora_inicio' => '13:00:00', 'hora_fim' => '14:00:00']);
        $naoCompareceu->status = Appointment::STATUS_NO_SHOW;
        $naoCompareceu->save();

        foreach ([
            [Appointment::STATUS_PENDING, 'Consulta Um'],
            [Appointment::STATUS_COMPLETED, 'Consulta Dois'],
            [Appointment::STATUS_NO_SHOW, 'Consulta Tres'],
        ] as [$status, $nomeEsperado]) {
            $resposta = $this->actingAs($this->admin)->get(route('admin.agenda', [
                'data' => $segunda->toDateString(),
                'busca' => 'Consulta',
                'status' => $status,
            ]));

            $resposta->assertSee($nomeEsperado);
        }
    }
}
