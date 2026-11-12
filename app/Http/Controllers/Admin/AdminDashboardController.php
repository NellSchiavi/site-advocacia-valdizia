<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AreaAtuacao;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Dashboard do painel com indicadores calculados a partir do banco.
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
