<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\TransicaoDeStatusInvalidaException;
use App\Http\Controllers\Controller;
use App\Models\AreaAtuacao;
use App\Models\Appointment;
use App\Services\AppointmentNotificationService;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Agenda administrativa. As regras de disponibilidade e de transição de
 * status ficam em AvailabilityService/AppointmentService; este controller só
 * faz a tradução HTTP.
 */
class AdminAgendaController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly AvailabilityService $availability,
        private readonly AppointmentNotificationService $notificacoes,
    ) {}

    public function index(Request $request): View
    {
        $validado = $request->validate([
            'visao' => ['nullable', Rule::in(['dia', 'semana'])],
            'data' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(array_keys(Appointment::STATUS_LABELS))],
            'area_atuacao_id' => ['nullable', 'integer', 'exists:areas_atuacao,id'],
            'busca' => ['nullable', 'string', 'max:190'],
        ]);

        $visao = $validado['visao'] ?? 'dia';
        $dataSelecionada = isset($validado['data'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validado['data'])->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $inicioIntervalo = $visao === 'semana'
            ? $dataSelecionada->startOfWeek(CarbonImmutable::MONDAY)
            : $dataSelecionada;
        $fimIntervalo = $visao === 'semana'
            ? $inicioIntervalo->addDays(6)
            : $dataSelecionada;

        // Minimização de dados pessoais: a listagem não carrega
        // e-mail/telefone/observação, que só são lidos no detalhe (show()).
        $consultas = Appointment::query()
            ->select(['id', 'area_atuacao_id', 'nome_cliente', 'data', 'hora_inicio', 'hora_fim', 'status'])
            ->with('areaAtuacao:id,nome')
            ->whereBetween('data', [$inicioIntervalo->toDateString(), $fimIntervalo->toDateString()])
            ->when($validado['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validado['area_atuacao_id'] ?? null, fn ($query, $areaId) => $query->where('area_atuacao_id', $areaId))
            ->when($validado['busca'] ?? null, fn ($query, $busca) => $this->aplicarBusca($query, $busca))
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->get();

        $consultasPorDia = $consultas->groupBy(fn (Appointment $appointment) => $appointment->data->toDateString());

        // A agenda é de recurso único (uma profissional atende todas as
        // áreas), então a área passada é apenas exigida pela assinatura e não
        // altera o resultado. Calculado só na visão diária.
        $horariosLivres = [];
        if ($visao === 'dia') {
            $areaReferencia = AreaAtuacao::query()->orderBy('id')->first();

            if ($areaReferencia) {
                $horariosLivres = $this->availability->horariosDisponiveis($areaReferencia->id, $dataSelecionada);
            }
        }

        return view('admin.agenda.index', [
            'visao' => $visao,
            'dataSelecionada' => $dataSelecionada,
            'inicioIntervalo' => $inicioIntervalo,
            'fimIntervalo' => $fimIntervalo,
            'consultasPorDia' => $consultasPorDia,
            'horariosLivres' => $horariosLivres,
            'areas' => AreaAtuacao::query()->orderBy('nome')->get(['id', 'nome']),
            'statusFiltro' => $validado['status'] ?? null,
            'areaFiltro' => $validado['area_atuacao_id'] ?? null,
            'buscaFiltro' => $validado['busca'] ?? null,
        ]);
    }

    /**
     * Busca textual por nome, e-mail ou telefone do cliente — condição
     * adicional aos filtros de data/status/área, nunca os substitui.
     * Feita no banco (LIKE), sem carregar a tabela inteira para o PHP.
     *
     * telefone_cliente não tem máscara imposta em nenhuma camada (o input
     * público é livre, sem JS de formatação) — o valor pode estar salvo com
     * ou sem "(11) 99999-9999". Para não depender do formato exato digitado
     * na busca, remove espaço/parênteses/hífen dos dois lados na comparação
     * de telefone, sem alterar o dado armazenado nem criar coluna nova.
     */
    private function aplicarBusca($query, string $busca)
    {
        $soDigitos = preg_replace('/\D+/', '', $busca);

        return $query->where(function ($query) use ($busca, $soDigitos) {
            $query->where('nome_cliente', 'like', "%{$busca}%")
                ->orWhere('email_cliente', 'like', "%{$busca}%");

            if ($soDigitos !== '') {
                $query->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(telefone_cliente, ' ', ''), '(', ''), ')', ''), '-', '') LIKE ?",
                    ["%{$soDigitos}%"]
                );
            }
        });
    }

    /**
     * Detalhe completo de uma consulta, carregado sob demanda quando o card
     * é aberto.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load('areaAtuacao:id,nome');

        return response()->json([
            'id' => $appointment->id,
            'nome_cliente' => $appointment->nome_cliente,
            'email_cliente' => $appointment->email_cliente,
            'telefone_cliente' => $appointment->telefone_cliente,
            'assunto' => $appointment->assunto,
            'area' => $appointment->areaAtuacao->nome,
            'data' => $appointment->data->toDateString(),
            'hora_inicio' => $appointment->hora_inicio,
            'hora_fim' => $appointment->hora_fim,
            'observacao' => $appointment->observacao,
            'status' => $appointment->status,
            'status_label' => Appointment::STATUS_LABELS[$appointment->status] ?? $appointment->status,
            'created_at' => $appointment->created_at?->toIso8601String(),
        ]);
    }

    public function cancelar(Request $request, Appointment $appointment): RedirectResponse
    {
        $validado = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->appointments->cancelar($appointment, $validado['motivo'] ?? null);
        } catch (TransicaoDeStatusInvalidaException $e) {
            return back()->withErrors(['acao' => $e->getMessage()]);
        }

        // O e-mail só é enviado depois que o cancelamento foi persistido;
        // uma falha no envio não desfaz a operação.
        $appointment->load('areaAtuacao:id,nome');
        $this->notificacoes->enviarCancelamento($appointment);

        return back()->with('status', 'Consulta cancelada.');
    }

    public function concluir(Appointment $appointment): RedirectResponse
    {
        try {
            $this->appointments->marcarComoConcluido($appointment);
        } catch (TransicaoDeStatusInvalidaException $e) {
            return back()->withErrors(['acao' => $e->getMessage()]);
        }

        return back()->with('status', 'Consulta marcada como concluída.');
    }

    public function naoCompareceu(Appointment $appointment): RedirectResponse
    {
        try {
            $this->appointments->marcarComoNaoCompareceu($appointment);
        } catch (TransicaoDeStatusInvalidaException $e) {
            return back()->withErrors(['acao' => $e->getMessage()]);
        }

        return back()->with('status', 'Consulta marcada como não comparecida.');
    }
}
