<?php

namespace App\Http\Controllers;

use App\Exceptions\HorarioIndisponivelException;
use App\Http\Requests\AgendarConsultaRequest;
use App\Models\AreaAtuacao;
use App\Models\Appointment;
use App\Services\AppointmentNotificationService;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Fluxo público de agendamento. As regras de disponibilidade e concorrência
 * ficam em AvailabilityService/AppointmentService; este controller só faz a
 * tradução HTTP.
 */
class AppointmentController extends Controller
{
    public function create(): View
    {
        return view('pages.agendamento', [
            'areas' => AreaAtuacao::query()->ativo()->ordenado()->get(['id', 'slug', 'nome', 'icone', 'descricao']),
        ]);
    }

    /**
     * Disponibilidade resumida do mês (para desenhar o calendário): para cada
     * dia, apenas se existe ou não pelo menos 1 horário livre.
     */
    public function disponibilidadeMensal(Request $request, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'area_atuacao_id' => ['required', 'integer', 'exists:areas_atuacao,id'],
            'mes' => ['required', 'date_format:Y-m'],
        ]);

        $inicioMes = CarbonImmutable::createFromFormat('Y-m-d', $validated['mes'].'-01')->startOfMonth();
        $fimMes = $inicioMes->endOfMonth();
        $hoje = CarbonImmutable::now()->startOfDay();

        $dias = [];
        for ($data = $inicioMes; $data->lte($fimMes); $data = $data->addDay()) {
            // Datas passadas nunca têm horário — evita consultas desnecessárias.
            $dias[$data->toDateString()] = $data->lt($hoje)
                ? false
                : count($availability->horariosDisponiveis((int) $validated['area_atuacao_id'], $data)) > 0;
        }

        return response()->json(['dias' => $dias]);
    }

    /**
     * Horários realmente disponíveis para uma data específica.
     */
    public function horariosDoDia(Request $request, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'area_atuacao_id' => ['required', 'integer', 'exists:areas_atuacao,id'],
            'data' => ['required', 'date_format:Y-m-d'],
        ]);

        $slots = $availability->horariosDisponiveis(
            (int) $validated['area_atuacao_id'],
            CarbonImmutable::createFromFormat('Y-m-d', $validated['data'])
        );

        return response()->json(['horarios' => $slots]);
    }

    public function store(
        AgendarConsultaRequest $request,
        AppointmentService $appointments,
        AppointmentNotificationService $notificacoes
    ): RedirectResponse {
        try {
            $appointment = $appointments->agendar($request->validated());
        } catch (HorarioIndisponivelException) {
            return back()->withInput()->withErrors([
                'hora_inicio' => 'Este horário acabou de ser reservado. Por favor, escolha outro horário.',
            ]);
        }

        // Enviado após o commit: uma falha no envio não desfaz o agendamento
        // nem impede a resposta ao cliente.
        $appointment->load('areaAtuacao:id,nome');
        $notificacoes->enviarConfirmacao($appointment);

        // URL assinada e temporária (2 dias): evita que alguém descubra a
        // confirmação de outra pessoa incrementando o ID na URL, e limita por
        // quanto tempo esses dados pessoais ficam acessíveis por esse link.
        return redirect()->to(
            URL::temporarySignedRoute(
                'agendamento.confirmado',
                now()->addDays(2),
                ['appointment' => $appointment->id]
            )
        );
    }

    public function confirmado(Appointment $appointment): View
    {
        $appointment->load('areaAtuacao');

        return view('pages.agendamento-confirmado', ['appointment' => $appointment]);
    }
}
