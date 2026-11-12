<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AlteracaoDeAgendaAfetaConsultasException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Holiday;
use App\Services\AdminAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Painel de disponibilidade: horários semanais, exceções, feriados e
 * bloqueios. As regras de conflito e cancelamento ficam no
 * AdminAvailabilityService.
 */
class AdminAvailabilityController extends Controller
{
    public function __construct(private readonly AdminAvailabilityService $disponibilidade) {}

    public function index(): View
    {
        $regrasPorDia = AvailabilityRule::query()->get()->keyBy('day_of_week');

        // Segunda a domingo (1..6, depois 0) — ordem de leitura natural para
        // quem não é técnico, mesmo com day_of_week seguindo a convenção
        // Carbon (0 = domingo).
        $ordemExibicao = [1, 2, 3, 4, 5, 6, 0];
        $nomesDias = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];

        $dias = [];
        foreach ($ordemExibicao as $diaDaSemana) {
            $regra = $regrasPorDia->get($diaDaSemana);
            $dias[] = [
                'dia' => $diaDaSemana,
                'nome' => $nomesDias[$diaDaSemana],
                'hora_inicio' => $regra ? substr($regra->hora_inicio, 0, 5) : '09:00',
                'hora_fim' => $regra ? substr($regra->hora_fim, 0, 5) : '18:00',
                'ativo' => $regra?->ativo ?? false,
            ];
        }

        return view('admin.disponibilidade.index', [
            'dias' => $dias,
            'excecoes' => AvailabilityException::query()
                ->where('tipo', AvailabilityException::TIPO_HORARIO_ESPECIAL)
                ->orderBy('data')
                ->get(),
            'bloqueios' => AvailabilityException::query()
                ->where('tipo', AvailabilityException::TIPO_FECHADO_TOTAL)
                ->orderBy('data')
                ->get(),
            'feriados' => Holiday::query()->orderBy('mes')->orderBy('dia')->get(),
        ]);
    }

    public function atualizarRegra(Request $request, int $diaDaSemana): RedirectResponse
    {
        abort_unless($diaDaSemana >= 0 && $diaDaSemana <= 6, 404);

        $dados = $request->validate([
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fim' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'ativo' => ['required', 'boolean'],
        ]);

        try {
            $canceladas = $this->disponibilidade->atualizarRegraSemanal($diaDaSemana, [
                'hora_inicio' => "{$dados['hora_inicio']}:00",
                'hora_fim' => "{$dados['hora_fim']}:00",
                'ativo' => (bool) $dados['ativo'],
            ], $request->user(), $request->boolean('confirmar_cancelamento'));
        } catch (AlteracaoDeAgendaAfetaConsultasException $e) {
            return back()->withInput()->with('conflito_pendente', [
                'tipo' => 'regra',
                'dia_da_semana' => $diaDaSemana,
                'afetadas' => $this->resumoAfetadas($e->consultasAfetadas),
            ]);
        }

        return back()->with('status', $this->mensagemSucesso('Horário', $canceladas));
    }

    public function salvarExcecao(Request $request): RedirectResponse
    {
        $ehHorarioEspecial = $request->input('tipo') === AvailabilityException::TIPO_HORARIO_ESPECIAL;

        $dados = $request->validate([
            'id' => ['nullable', 'integer', 'exists:availability_exceptions,id'],
            'tipo' => ['required', Rule::in([AvailabilityException::TIPO_HORARIO_ESPECIAL, AvailabilityException::TIPO_FECHADO_TOTAL])],
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => [$ehHorarioEspecial ? 'required' : 'nullable', 'date_format:H:i'],
            'hora_fim' => [$ehHorarioEspecial ? 'required' : 'nullable', 'date_format:H:i', 'after:hora_inicio'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $existente = isset($dados['id']) ? AvailabilityException::query()->find($dados['id']) : null;

        try {
            $canceladas = $this->disponibilidade->salvarExcecao($existente, [
                'data' => $dados['data'],
                'tipo' => $dados['tipo'],
                'hora_inicio' => isset($dados['hora_inicio']) ? "{$dados['hora_inicio']}:00" : null,
                'hora_fim' => isset($dados['hora_fim']) ? "{$dados['hora_fim']}:00" : null,
                'motivo' => $dados['motivo'] ?? null,
            ], $request->user(), $request->boolean('confirmar_cancelamento'));
        } catch (AlteracaoDeAgendaAfetaConsultasException $e) {
            return back()->withInput()->with('conflito_pendente', [
                'tipo' => $ehHorarioEspecial ? 'excecao' : 'bloqueio',
                'afetadas' => $this->resumoAfetadas($e->consultasAfetadas),
            ]);
        }

        return back()->with('status', $this->mensagemSucesso($ehHorarioEspecial ? 'Exceção' : 'Bloqueio', $canceladas));
    }

    public function removerExcecao(AvailabilityException $excecao): RedirectResponse
    {
        $this->disponibilidade->removerExcecao($excecao, request()->user());

        return back()->with('status', 'Removido com sucesso.');
    }

    public function salvarFeriado(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'id' => ['nullable', 'integer', 'exists:holidays,id'],
            'nome' => ['required', 'string', 'max:120'],
            'mes' => ['required', 'integer', 'between:1,12'],
            'dia' => ['required', 'integer', 'between:1,31', function ($attribute, $value, $fail) use ($request) {
                // 2024 é bissexto — permite 29/02 para feriados recorrentes.
                if (! checkdate((int) $request->input('mes'), (int) $value, 2024)) {
                    $fail('Informe um dia válido para o mês escolhido.');
                }
            }],
            'ano' => ['nullable', 'integer', 'min:2020'],
        ]);

        $existente = isset($dados['id']) ? Holiday::query()->find($dados['id']) : null;

        try {
            $canceladas = $this->disponibilidade->salvarFeriado($existente, [
                'nome' => $dados['nome'],
                'mes' => $dados['mes'],
                'dia' => $dados['dia'],
                'ano' => $dados['ano'] ?? null,
                'recorrente_anual' => $request->boolean('recorrente_anual', true),
            ], $request->user(), $request->boolean('confirmar_cancelamento'));
        } catch (AlteracaoDeAgendaAfetaConsultasException $e) {
            return back()->withInput()->with('conflito_pendente', [
                'tipo' => 'feriado',
                'afetadas' => $this->resumoAfetadas($e->consultasAfetadas),
            ]);
        }

        return back()->with('status', $this->mensagemSucesso('Feriado', $canceladas));
    }

    public function removerFeriado(Holiday $feriado): RedirectResponse
    {
        $this->disponibilidade->removerFeriado($feriado, request()->user());

        return back()->with('status', 'Feriado removido.');
    }

    private function mensagemSucesso(string $rotulo, Collection $canceladas): string
    {
        if ($canceladas->isEmpty()) {
            return "{$rotulo} salvo com sucesso.";
        }

        return "{$rotulo} salvo com sucesso. {$canceladas->count()} consulta(s) cancelada(s) — cliente(s) notificado(s) por e-mail.";
    }

    /**
     * @return array<int, array{id:int,data:string,hora_inicio:string,hora_fim:string,nome_cliente:string}>
     */
    private function resumoAfetadas(Collection $afetadas): array
    {
        return $afetadas->map(fn (Appointment $a) => [
            'id' => $a->id,
            'data' => $a->data->toDateString(),
            'hora_inicio' => substr($a->hora_inicio, 0, 5),
            'hora_fim' => substr($a->hora_fim, 0, 5),
            'nome_cliente' => $a->nome_cliente,
        ])->values()->all();
    }
}
