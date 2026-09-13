<?php

use App\Http\Controllers\Admin\AdminAgendaController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

Route::get('/politica-de-privacidade', function () {
    return view('pages.politica-privacidade');
})->name('politica-privacidade');

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
        });
    });
});
