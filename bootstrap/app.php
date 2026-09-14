<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Único guard/login da aplicação por enquanto é o painel administrativo
        // (Fase 4A) — por isso os redirecionamentos padrão de "auth"/"guest"
        // já podem apontar direto para lá. Se um login público for adicionado
        // no futuro, isto precisa passar a variar por rota/guard.
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo('/admin');

        // Fase 4F: headers de segurança em toda resposta do grupo "web"
        // (site público + painel) — ver SecurityHeaders para o raciocínio
        // completo de cada header/diretiva de CSP.
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Fase 4F: o Response de uma exceção (guest/auth redirect, 404, 419,
        // 500...) é montado fora do pipeline de middleware — sem isto, essas
        // respostas ficariam sem os headers de segurança (ver SecurityHeaders).
        $exceptions->respond(fn ($response) => app(SecurityHeaders::class)->aplicar($response));
    })->create();
