<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AreaAtuacao;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Dashboard real porém simples (Fase 4A, item 8): só os números que já
 * existem no banco hoje. Nenhuma métrica é inventada — as demais telas do
 * painel (agenda, disponibilidade, feriados, configurações) chegam nas
 * próximas fases.
 */
class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $hoje = CarbonImmutable::now()->startOfDay();

        return view('admin.dashboard', [
            'consultasHoje' => Appointment::query()->naData($hoje)->ativos()->count(),
            'consultasConfirmadas' => Appointment::query()
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->whereDate('data', '>=', $hoje)
                ->count(),
            'consultasFuturas' => Appointment::query()
                ->ativos()
                ->whereDate('data', '>', $hoje)
                ->count(),
            'areasAtivas' => AreaAtuacao::query()->ativo()->count(),
        ]);
    }
}
