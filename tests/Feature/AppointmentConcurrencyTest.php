<?php

namespace Tests\Feature;

use App\Models\AreaAtuacao;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAgendaFixtures;
use Tests\TestCase;

/**
 * Prova a garantia de concorrência descrita no PLANO-TECNICO.md (seção 3):
 * o lock por data em "agenda_locks" realmente serializa transações
 * concorrentes no MySQL, e — quando o ambiente suporta pcntl_fork — dois
 * processos disputando o mesmo horário produzem exatamente 1 agendamento
 * ativo, nunca 2.
 */
class AppointmentConcurrencyTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAgendaFixtures;

    private AreaAtuacao $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = $this->criarAreaAtuacao();
        $this->criarExpedientePadrao();
        $this->criarConfiguracaoGlobal();
    }

    public function test_lock_da_agenda_bloqueia_outra_sessao_ate_o_commit(): void
    {
        $segunda = $this->proximaSegundaFeira()->toDateString();

        // Duas conexões MySQL totalmente independentes da conexão padrão do
        // teste — o RefreshDatabase já mantém a conexão padrão dentro de uma
        // transação/savepoint que só é desfeita no tearDown, então um commit
        // nela não liberaria o lock de verdade (nem tornaria a linha visível
        // para outra sessão). Usando conexões próprias, garantimos uma
        // transação real do lado A e uma leitura real do lado B.
        config(['database.connections.conexao_a' => config('database.connections.mysql')]);
        config(['database.connections.conexao_b' => config('database.connections.mysql')]);
        $conexaoA = DB::connection('conexao_a');
        $conexaoB = DB::connection('conexao_b');
        $conexaoB->statement('SET SESSION innodb_lock_wait_timeout = 1');

        // Insere (em modo autocommit, fora de qualquer transação) para que a
        // linha já esteja commitada e visível para as duas conexões abaixo.
        $conexaoA->table('agenda_locks')->insertOrIgnore(['data' => $segunda, 'created_at' => now()]);

        $conexaoA->beginTransaction();

        try {
            $conexaoA->table('agenda_locks')->where('data', $segunda)->lockForUpdate()->first();

            $bloqueado = false;

            try {
                $conexaoB->select('SELECT * FROM agenda_locks WHERE data = ? FOR UPDATE NOWAIT', [$segunda]);
            } catch (\Throwable $e) {
                $bloqueado = true;
            }

            $this->assertTrue(
                $bloqueado,
                'A segunda conexão deveria falhar ao tentar travar a mesma data enquanto a primeira mantém o lock aberto.'
            );
        } finally {
            $conexaoA->commit();
        }

        // Depois do commit real da conexão A, a mesma checagem na conexão B
        // (agora sem NOWAIT) tem que suceder normalmente.
        $linha = $conexaoB->select('SELECT * FROM agenda_locks WHERE data = ? FOR UPDATE', [$segunda]);
        $this->assertNotEmpty($linha);

        // Este teste opera em conexões próprias, fora da transação padrão do
        // RefreshDatabase — o INSERT acima foi um commit real, então a
        // limpeza também precisa ser explícita para não vazar para os
        // próximos testes da suíte.
        $conexaoA->table('agenda_locks')->where('data', $segunda)->delete();

        $conexaoA->disconnect();
        $conexaoB->disconnect();
    }

    public function test_duas_tentativas_concorrentes_para_o_mesmo_horario_resultam_em_um_unico_confirmado(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl não disponível nesta imagem PHP — a garantia real de concorrência é coberta pelo teste de lock acima.');
        }

        $segunda = $this->proximaSegundaFeira();
        $dados = [
            'area_atuacao_id' => $this->area->id,
            'nome_cliente' => 'Cliente Teste',
            'email_cliente' => 'cliente@example.com',
            'telefone_cliente' => '11999999999',
            'assunto' => 'Assunto de teste',
            'observacao' => null,
            'data' => $segunda->toDateString(),
            'hora_inicio' => '10:00',
        ];

        // RefreshDatabase mantém o teste inteiro dentro de uma transação (para
        // poder desfazer tudo no final sem recriar o schema). Precisamos
        // COMMITAR essa transação antes do fork, senão os processos filhos
        // (conexões MySQL totalmente separadas) não enxergam as fixtures
        // criadas no setUp (área de atuação, expediente, configuração).
        $nivelTransacaoOriginal = DB::transactionLevel();
        for ($i = 0; $i < $nivelTransacaoOriginal; $i++) {
            DB::commit();
        }
        DB::disconnect();

        $pids = [];

        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Falha ao criar processo filho para o teste de concorrência.');
            }

            if ($pid === 0) {
                // Processo filho: precisa da sua própria conexão de banco.
                DB::purge();

                try {
                    app(AppointmentService::class)->agendar($dados);
                } catch (\Throwable $e) {
                    // Esperado para exatamente um dos dois filhos.
                }

                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $ativos = Appointment::query()
            ->naData($segunda)
            ->ativos()
            ->where('hora_inicio', '10:00:00')
            ->count();

        $this->assertSame(1, $ativos, 'Exatamente um agendamento ativo deveria existir para o horário disputado.');

        // As fixtures (área, expediente, configuração) e o agendamento criado
        // pelos processos filhos foram commitados de verdade (fora da
        // transação padrão do RefreshDatabase) para ficarem visíveis a eles.
        // Como um rollback normal não desfaz isso, a limpeza é explícita —
        // senão os dados vazariam para os demais testes da suíte.
        Appointment::query()->where('area_atuacao_id', $this->area->id)->delete();
        \App\Models\AvailabilityRule::query()->delete();
        \App\Models\ConfiguracaoGlobal::query()->delete();
        $this->area->delete();

        // Reabre a mesma quantidade de níveis de transação que existia antes
        // do fork, para que o tearDown do RefreshDatabase encontre algo para
        // desfazer (agora só o estado trivial gerado pela limpeza acima).
        for ($i = 0; $i < $nivelTransacaoOriginal; $i++) {
            DB::beginTransaction();
        }
    }
}
