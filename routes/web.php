<?php

use App\Http\Controllers\Admin\AdminAgendaController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminAvailabilityController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPasswordResetController;
use App\Http\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

Route::get('/politica-de-privacidade', function () {
    return view('pages.politica-privacidade');
})->name('politica-privacidade');

/**
 * Sitemap mínimo (Fase 5) — só as páginas públicas reais do site. Gerado
 * dinamicamente via url()/route() para refletir automaticamente o domínio
 * real assim que APP_URL for configurado em produção (Fase 6) — nenhum
 * domínio fixo é escrito aqui.
 */
Route::get('/sitemap.xml', function () {
    $urls = [
        ['loc' => route('home'), 'changefreq' => 'monthly', 'priority' => '1.0'],
        ['loc' => route('agendamento.create'), 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['loc' => route('politica-privacidade'), 'changefreq' => 'yearly', 'priority' => '0.3'],
    ];

    return response()
        ->view('sitemap', ['urls' => $urls])
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::prefix('agendamento')->name('agendamento.')->group(function () {
    Route::get('/', [AppointmentController::class, 'create'])->name('create');
    Route::get('/disponibilidade', [AppointmentController::class, 'disponibilidadeMensal'])
        ->middleware('throttle:60,1')
        ->name('disponibilidade');
    Route::get('/horarios', [AppointmentController::class, 'horariosDoDia'])
        ->middleware('throttle:60,1')
        ->name('horarios');
    Route::post('/', [AppointmentController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('store');
    Route::get('/{appointment}/confirmado', [AppointmentController::class, 'confirmado'])
        ->middleware('signed')
        ->name('confirmado');
});

/**
 * Painel administrativo (Fase 4A — só autenticação e estrutura; agenda,
 * disponibilidade, feriados e configurações chegam nas próximas fases).
 */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('login.store');

        // Recuperação de senha (Fase 4F) — só faz sentido para quem ainda
        // não está autenticado, mesmo escopo "guest" do login.
        Route::get('/forgot-password', [AdminPasswordResetController::class, 'create'])
            ->name('password.request');
        Route::post('/forgot-password', [AdminPasswordResetController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('password.email');
        Route::get('/reset-password/{token}', [AdminPasswordResetController::class, 'edit'])
            ->name('password.reset');
        Route::post('/reset-password', [AdminPasswordResetController::class, 'update'])
            ->middleware('throttle:5,1')
            ->name('password.update');
    });

    Route::middleware('auth')->group(function () {
        // Logout não depende do Gate de admin: qualquer sessão autenticada
        // do painel deve conseguir encerrar a própria sessão.
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

        Route::middleware('can:access-admin-panel')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

            // Agenda administrativa (Fase 4B) — só autenticação já não basta:
            // este grupo herda também o Gate "access-admin-panel" acima.
            Route::prefix('agenda')->group(function () {
                Route::get('/', [AdminAgendaController::class, 'index'])->name('agenda');
                Route::get('/{appointment}', [AdminAgendaController::class, 'show'])->name('agenda.show');
                Route::post('/{appointment}/cancelar', [AdminAgendaController::class, 'cancelar'])->name('agenda.cancelar');
                Route::post('/{appointment}/concluir', [AdminAgendaController::class, 'concluir'])->name('agenda.concluir');
                Route::post('/{appointment}/nao-compareceu', [AdminAgendaController::class, 'naoCompareceu'])->name('agenda.nao-compareceu');
            });

            // Disponibilidade administrativa (Fase 4C): horários semanais,
            // exceções, feriados e bloqueios — tudo converge para o mesmo
            // AvailabilityService via AdminAvailabilityService.
            Route::prefix('disponibilidade')->name('disponibilidade.')->group(function () {
                Route::get('/', [AdminAvailabilityController::class, 'index'])->name('index');
                Route::post('/horarios/{diaDaSemana}', [AdminAvailabilityController::class, 'atualizarRegra'])
                    ->whereNumber('diaDaSemana')
                    ->name('horarios.atualizar');
                Route::post('/excecoes', [AdminAvailabilityController::class, 'salvarExcecao'])->name('excecoes.salvar');
                Route::delete('/excecoes/{excecao}', [AdminAvailabilityController::class, 'removerExcecao'])->name('excecoes.remover');
                Route::post('/feriados', [AdminAvailabilityController::class, 'salvarFeriado'])->name('feriados.salvar');
                Route::delete('/feriados/{feriado}', [AdminAvailabilityController::class, 'removerFeriado'])->name('feriados.remover');
            });
        });
    });
});
