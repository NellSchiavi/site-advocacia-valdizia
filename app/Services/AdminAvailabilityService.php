<?php

namespace App\Services;

use App\Exceptions\AlteracaoDeAgendaAfetaConsultasException;
use App\Models\AdminLog;
use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\ConfiguracaoGlobal;
use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra alterações administrativas de disponibilidade: horário padrão
 * semanal, exceções de horário, feriados e bloqueios de dia inteiro. Nenhuma
 * regra de disponibilidade é recalculada aqui — o AvailabilityService é a
 * única fonte de verdade para decidir se uma consulta já agendada ainda cabe
 * no expediente resultante.
 *
 * Toda alteração segue o mesmo fluxo:
 *   1. abre transação e adquire o(s) lock(s) de agenda_locks (mesmo
 *      mecanismo do AppointmentService, em ordem cronológica consistente
 *      para não gerar deadlock com reservas públicas concorrentes);
 *   2. aplica a alteração tentativamente;
 *   3. pergunta ao AvailabilityService se alguma consulta ativa deixou de
 *      caber no expediente resultante;
 *   4. se houver afetadas e ainda não há confirmação explícita, lança
 *      AlteracaoDeAgendaAfetaConsultasException — a transação inteira é
 *      revertida (nada é persistido) e o controller mostra a confirmação;
 *   5. só com confirmação explícita: cancela as consultas afetadas (via
 *      AppointmentService::cancelar(), sem duplicar a regra de transição) e
 *      grava o AdminLog — tudo dentro da mesma transação;
 *   6. só depois que a transação já retornou com sucesso, tenta enviar os
 *      e-mails de cancelamento (falha de e-mail nunca desfaz nem bloqueia a
 *      operação, que a essa altura já está persistida).
 */
class AdminAvailabilityService
{
    private const MOTIVO_CANCELAMENTO = 'Cancelado por alteração administrativa da agenda.';

    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly AvailabilityService $availability,
        private readonly AppointmentNotificationService $notificacoes,
    ) {}

    /**
     * @param  array{hora_inicio: string, hora_fim: string, ativo: bool}  $dados
     * @return Collection<int, Appointment> consultas canceladas por esta operação
     *
     * @throws AlteracaoDeAgendaAfetaConsultasException
     */
    public function atualizarRegraSemanal(int $diaDaSemana, array $dados, User $admin, bool $confirmado): Collection
    {
        // Calculados fora da transação para que a primeira leitura dentro
        // dela seja a aquisição do lock — assim nenhuma decisão é tomada com
        // base em um snapshot fixado antes do lock.
        $horizonte = ConfiguracaoGlobal::atual()->antecedencia_maxima_dias;
        $datas = $this->datasFuturasParaDiaDaSemana($diaDaSemana, $horizonte);

        $afetadas = DB::transaction(function () use ($diaDaSemana, $dados, $admin, $confirmado, $datas) {
            $this->travarDatasEmOrdem($datas);

            $regra = AvailabilityRule::query()->firstOrNew(['day_of_week' => $diaDaSemana]);
            $existiaAntes = $regra->exists;
            $estadoAnterior = $existiaAntes ? $regra->only(['hora_inicio', 'hora_fim', 'ativo']) : null;

            $regra->day_of_week = $diaDaSemana;
            $regra->hora_inicio = $dados['hora_inicio'];
            $regra->hora_fim = $dados['hora_fim'];
            $regra->ativo = $dados['ativo'];
            $regra->save();

            $afetadas = collect();
            foreach ($datas as $data) {
                $afetadas = $afetadas->merge($this->consultasQueNaoCabem($data));
            }

            $this->confirmarOuLancarConflito($afetadas, $confirmado);

            $this->cancelarConsultasAfetadas($afetadas);

            $this->registrarLog($admin, $existiaAntes ? 'disponibilidade.horario_padrao.atualizado' : 'disponibilidade.horario_padrao.criado', [
                'day_of_week' => $diaDaSemana,
                'estado_anterior' => $estadoAnterior,
                'novo_estado' => $regra->only(['hora_inicio', 'hora_fim', 'ativo']),
                'consultas_canceladas' => $afetadas->pluck('id')->all(),
            ]);

            return $afetadas;
        });

        $this->enviarEmailsDeCancelamento($afetadas);

        return $afetadas;
    }

    /**
     * Cria ou atualiza uma exceção de horário (tipo "horario_especial") ou um
     * bloqueio de dia inteiro (tipo "fechado_total") — mesma tabela, mesmo
     * fluxo, só o "tipo" e os campos de horário mudam.
     *
     * @param  array{data: string, tipo: string, hora_inicio?: string|null, hora_fim?: string|null, motivo?: string|null}  $dados
     * @return Collection<int, Appointment>
     *
     * @throws AlteracaoDeAgendaAfetaConsultasException
     */
    public function salvarExcecao(?AvailabilityException $existente, array $dados, User $admin, bool $confirmado): Collection
    {
        $afetadas = DB::transaction(function () use ($existente, $dados, $admin, $confirmado) {
            $data = CarbonImmutable::parse($dados['data'])->startOfDay();

            $this->appointments->adquirirLockDoDia($data);

            $excecao = $existente ?? AvailabilityException::query()->firstOrNew(['data' => $data->toDateString()]);
            $existiaAntes = $excecao->exists;
            $estadoAnterior = $existiaAntes ? $excecao->only(['tipo', 'hora_inicio', 'hora_fim', 'motivo']) : null;

            $ehHorarioEspecial = $dados['tipo'] === AvailabilityException::TIPO_HORARIO_ESPECIAL;

            $excecao->data = $data->toDateString();
            $excecao->tipo = $dados['tipo'];
            $excecao->hora_inicio = $ehHorarioEspecial ? $dados['hora_inicio'] : null;
            $excecao->hora_fim = $ehHorarioEspecial ? $dados['hora_fim'] : null;
            $excecao->motivo = $dados['motivo'] ?? null;
            $excecao->created_by ??= $admin->id;
            $excecao->save();

            $afetadas = $this->consultasQueNaoCabem($data);

            $this->confirmarOuLancarConflito($afetadas, $confirmado);

            $this->cancelarConsultasAfetadas($afetadas);

            $rotulo = $dados['tipo'] === AvailabilityException::TIPO_FECHADO_TOTAL ? 'bloqueio' : 'excecao';
            $acao = "disponibilidade.{$rotulo}." . ($existiaAntes ? 'atualizado' : 'criado');

            $this->registrarLog($admin, $acao, [
                'data' => $data->toDateString(),
                'estado_anterior' => $estadoAnterior,
                'novo_estado' => $excecao->only(['tipo', 'hora_inicio', 'hora_fim', 'motivo']),
                'consultas_canceladas' => $afetadas->pluck('id')->all(),
            ]);

            return $afetadas;
        });

        $this->enviarEmailsDeCancelamento($afetadas);

        return $afetadas;
    }

    public function removerExcecao(AvailabilityException $excecao, User $admin): void
    {
        DB::transaction(function () use ($excecao, $admin) {
            $tipo = $excecao->tipo;
            $estadoAnterior = $excecao->only(['data', 'tipo', 'hora_inicio', 'hora_fim', 'motivo']);

            $excecao->delete();

            $rotulo = $tipo === AvailabilityException::TIPO_FECHADO_TOTAL ? 'bloqueio' : 'excecao';
            $this->registrarLog($admin, "disponibilidade.{$rotulo}.removido", ['estado_anterior' => $estadoAnterior]);
        });
    }

    /**
     * @param  array{nome: string, mes: int, dia: int, ano?: int|null, recorrente_anual: bool}  $dados
     * @return Collection<int, Appointment>
     *
     * @throws AlteracaoDeAgendaAfetaConsultasException
     */
    public function salvarFeriado(?Holiday $existente, array $dados, User $admin, bool $confirmado): Collection
    {
        $dataAlvo = $this->proximaOcorrenciaFeriado($dados['mes'], $dados['dia'], $dados['ano'] ?? null);

        $afetadas = DB::transaction(function () use ($existente, $dados, $admin, $confirmado, $dataAlvo) {
            // O lock precede qualquer leitura/escrita em "holidays": a decisão
            // sobre consultas afetadas precisa ser tomada com o lock em mãos.
            $this->appointments->adquirirLockDoDia($dataAlvo);

            // Localiza pela chave semântica (mes+dia+ano) quando não há id,
            // evitando duplicar o mesmo feriado em um envio repetido.
            $feriado = $existente ?? Holiday::query()->firstOrNew([
                'mes' => $dados['mes'],
                'dia' => $dados['dia'],
                'ano' => $dados['ano'] ?? null,
            ]);
            $existiaAntes = $feriado->exists;
            $estadoAnterior = $existiaAntes ? $feriado->only(['nome', 'mes', 'dia', 'ano', 'recorrente_anual']) : null;

            $feriado->nome = $dados['nome'];
            $feriado->mes = $dados['mes'];
            $feriado->dia = $dados['dia'];
            $feriado->ano = $dados['ano'] ?? null;
            $feriado->recorrente_anual = $dados['recorrente_anual'] ?? true;
            $feriado->bloqueado = true;
            $feriado->save();

            $afetadas = $this->consultasQueNaoCabem($dataAlvo);

            $this->confirmarOuLancarConflito($afetadas, $confirmado);

            $this->cancelarConsultasAfetadas($afetadas);

            $this->registrarLog($admin, $existiaAntes ? 'disponibilidade.feriado.atualizado' : 'disponibilidade.feriado.criado', [
                'estado_anterior' => $estadoAnterior,
                'novo_estado' => $feriado->only(['nome', 'mes', 'dia', 'ano', 'recorrente_anual']),
                'data_alvo' => $dataAlvo->toDateString(),
                'consultas_canceladas' => $afetadas->pluck('id')->all(),
            ]);

            return $afetadas;
        });

        $this->enviarEmailsDeCancelamento($afetadas);

        return $afetadas;
    }

    public function removerFeriado(Holiday $feriado, User $admin): void
    {
        DB::transaction(function () use ($feriado, $admin) {
            $estadoAnterior = $feriado->only(['nome', 'mes', 'dia', 'ano', 'recorrente_anual', 'bloqueado']);

            $feriado->delete();

            $this->registrarLog($admin, 'disponibilidade.feriado.removido', ['estado_anterior' => $estadoAnterior]);
        });
    }

    /**
     * @throws AlteracaoDeAgendaAfetaConsultasException
     */
    private function confirmarOuLancarConflito(Collection $afetadas, bool $confirmado): void
    {
        if ($afetadas->isNotEmpty() && ! $confirmado) {
            // Lançar dentro da transação reverte toda a operação, sem deixar
            // estado parcial.
            throw new AlteracaoDeAgendaAfetaConsultasException($afetadas);
        }
    }

    private function cancelarConsultasAfetadas(Collection $afetadas): void
    {
        foreach ($afetadas as $consulta) {
            $this->appointments->cancelar($consulta, self::MOTIVO_CANCELAMENTO);
        }
    }

    /**
     * Consultas ativas de uma data que NÃO cabem inteiramente em nenhum dos
     * intervalos abertos do expediente resultante. Usa
     * AvailabilityService::intervalosAbertosDoDia() (regra+exceção+feriado
     * já resolvidos) — nunca a grade de slots (horariosDisponiveis), que
     * geraria falsos conflitos sempre que o horário de início mudasse.
     *
     * @return Collection<int, Appointment>
     */
    private function consultasQueNaoCabem(CarbonImmutable $data): Collection
    {
        $abertos = $this->availability->intervalosAbertosDoDia($data);

        return Appointment::query()
            ->ativos()
            ->naData($data)
            ->with('areaAtuacao:id,nome')
            ->get()
            ->filter(function (Appointment $consulta) use ($abertos) {
                $inicio = $this->availability->paraMinutos($consulta->hora_inicio);
                $fim = $this->availability->paraMinutos($consulta->hora_fim);

                foreach ($abertos as [$aberturaInicio, $aberturaFim]) {
                    if ($inicio >= $aberturaInicio && $fim <= $aberturaFim) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function datasFuturasParaDiaDaSemana(int $diaDaSemana, int $horizonteDias): array
    {
        $hoje = CarbonImmutable::now()->startOfDay();
        $datas = [];

        for ($i = 0; $i <= $horizonteDias; $i++) {
            $candidata = $hoje->addDays($i);

            if ($candidata->dayOfWeek === $diaDaSemana) {
                $datas[] = $candidata;
            }
        }

        return $datas;
    }

    /**
     * Trava as datas em ordem cronológica antes de qualquer checagem. A ordem
     * consistente evita deadlock com o AppointmentService, que também trava
     * agenda_locks por data.
     *
     * @param  array<int, CarbonImmutable>  $datas
     */
    private function travarDatasEmOrdem(array $datas): void
    {
        usort($datas, fn (CarbonImmutable $a, CarbonImmutable $b) => $a <=> $b);

        foreach ($datas as $data) {
            $this->appointments->adquirirLockDoDia($data);
        }
    }

    /**
     * Próxima ocorrência (hoje ou futura) calculada só a partir dos campos
     * submetidos, para poder ser chamada antes de qualquer leitura/escrita em
     * "holidays".
     */
    private function proximaOcorrenciaFeriado(int $mes, int $dia, ?int $ano): CarbonImmutable
    {
        if ($ano) {
            return CarbonImmutable::createFromDate($ano, $mes, $dia)->startOfDay();
        }

        $hoje = CarbonImmutable::now()->startOfDay();
        $candidata = CarbonImmutable::createFromDate($hoje->year, $mes, $dia)->startOfDay();

        return $candidata->lt($hoje) ? $candidata->addYear() : $candidata;
    }

    private function registrarLog(User $admin, string $acao, array $detalhes): void
    {
        AdminLog::create([
            'user_id' => $admin->id,
            'acao' => $acao,
            'detalhes' => $detalhes,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Chamado somente após o commit da transação. Cada envio é isolado pelo
     * AppointmentNotificationService: uma falha não impede as demais
     * notificações nem é propagada.
     */
    private function enviarEmailsDeCancelamento(Collection $afetadas): void
    {
        foreach ($afetadas as $consulta) {
            $this->notificacoes->enviarCancelamento($consulta);
        }
    }
}
