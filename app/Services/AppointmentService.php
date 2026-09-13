<?php

namespace App\Services;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\AgendaLock;
use App\Models\Appointment;
use App\Models\ConfiguracaoGlobal;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Fluxo de criação de um agendamento (PLANO-TECNICO.md, seções 3 e 6):
 *
 *  1. valida os dados recebidos
 *  2. calcula hora_fim a partir da duração configurada
 *  3. abre uma transação e adquire o lock da agenda daquele dia
 *  4. revalida a disponibilidade (nunca confia no que o cliente enviou)
 *  5. faz a checagem de sobreposição por intervalo dentro da transação
 *  6. cria o agendamento (status automático ou pendente, conforme configuração)
 *  7. índice único (active_slot) age como rede de segurança adicional
 */
class AppointmentService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  array{
     *     area_atuacao_id: int|string,
     *     nome_cliente: string,
     *     email_cliente: string,
     *     telefone_cliente: string,
     *     observacao?: string|null,
     *     data: string,
     *     hora_inicio: string,
     * }  $dados
     *
     * @throws HorarioIndisponivelException
     */
    public function agendar(array $dados): Appointment
    {
        $validado = Validator::make($dados, [
            'area_atuacao_id' => ['required', 'integer', 'exists:areas_atuacao,id,ativo,1'],
            'nome_cliente' => ['required', 'string', 'max:150'],
            'email_cliente' => ['required', 'email', 'max:190'],
            'telefone_cliente' => ['required', 'string', 'max:30'],
            'observacao' => ['nullable', 'string'],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
        ])->validate();

        $config = ConfiguracaoGlobal::atual();
        $data = CarbonImmutable::createFromFormat('Y-m-d', $validado['data'])->startOfDay();
        $horaInicio = $validado['hora_inicio'];
        $horaFim = CarbonImmutable::createFromFormat('H:i', $horaInicio)
            ->addMinutes($config->duracao_consulta_min)
            ->format('H:i');

        return DB::transaction(function () use ($validado, $config, $data, $horaInicio, $horaFim) {
            $this->adquirirLockDoDia($data);

            $disponivel = $this->availability->estaDisponivel(
                (int) $validado['area_atuacao_id'],
                $data,
                $horaInicio,
                $horaFim,
                $config
            );

            if (! $disponivel) {
                throw HorarioIndisponivelException::paraHorario($data->toDateString(), $horaInicio);
            }

            // Defesa em profundidade: mesmo já tendo revalidado acima sob o lock
            // da data, a fórmula de sobreposição por intervalo é checada de novo
            // de forma explícita (existente.inicio < novo.fim E existente.fim >
            // novo.inicio), cobrindo tanto horários idênticos quanto parciais
            // (ex. 14:00–15:00 vs. 14:30–15:30).
            $conflito = Appointment::query()
                ->naData($data)
                ->ativos()
                ->sobrepondo($horaInicio, $horaFim)
                ->exists();

            if ($conflito) {
                throw HorarioIndisponivelException::paraHorario($data->toDateString(), $horaInicio);
            }

            $status = $config->modo_confirmacao === ConfiguracaoGlobal::MODO_MANUAL
                ? Appointment::STATUS_PENDING
                : Appointment::STATUS_CONFIRMED;

            try {
                $appointment = Appointment::create([
                    'area_atuacao_id' => $validado['area_atuacao_id'],
                    'nome_cliente' => $validado['nome_cliente'],
                    'email_cliente' => $validado['email_cliente'],
                    'telefone_cliente' => $validado['telefone_cliente'],
                    'observacao' => $validado['observacao'] ?? null,
                    'data' => $data->toDateString(),
                    'hora_inicio' => $horaInicio,
                    'hora_fim' => $horaFim,
                ]);
            } catch (QueryException $e) {
                if ($this->violacaoDeUnicidade($e)) {
                    throw HorarioIndisponivelException::paraHorario($data->toDateString(), $horaInicio);
                }

                throw $e;
            }

            $appointment->status = $status;
            $appointment->save();

            return $appointment;
        });
    }

    public function cancelar(Appointment $appointment, ?string $motivo = null): Appointment
    {
        $appointment->status = Appointment::STATUS_CANCELLED;
        $appointment->cancelled_reason = $motivo;
        $appointment->save();

        return $appointment;
    }

    public function marcarComoConcluido(Appointment $appointment): Appointment
    {
        $appointment->status = Appointment::STATUS_COMPLETED;
        $appointment->save();

        return $appointment;
    }

    public function marcarComoNaoCompareceu(Appointment $appointment): Appointment
    {
        $appointment->status = Appointment::STATUS_NO_SHOW;
        $appointment->save();

        return $appointment;
    }

    /**
     * Cria (se necessário) e trava a linha de lock da data dentro da transação
     * atual, serializando qualquer outra tentativa concorrente de agendamento
     * para o mesmo dia até este processo commitar ou reverter.
     */
    private function adquirirLockDoDia(CarbonImmutable $data): void
    {
        DB::table('agenda_locks')->insertOrIgnore([
            'data' => $data->toDateString(),
            'created_at' => now(),
        ]);

        AgendaLock::query()
            ->where('data', $data->toDateString())
            ->lockForUpdate()
            ->first();
    }

    private function violacaoDeUnicidade(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
