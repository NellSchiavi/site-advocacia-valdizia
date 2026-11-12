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
        // O único login da aplicação é o do painel administrativo, por isso
        // os redirecionamentos de "auth"/"guest" apontam para ele. Um login
        // público exigiria variar isto por rota/guard.
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo('/admin');

        // Headers de segurança em toda resposta do grupo "web" (ver
        // SecurityHeaders).
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // O Response de uma exceção (guest/auth redirect, 404, 419, 500...)
        // é montado fora do pipeline de middleware — sem isto, essas
        // respostas ficariam sem os headers de segurança (ver SecurityHeaders).
        $exceptions->respond(fn ($response) => app(SecurityHeaders::class)->aplicar($response));
    })->create();
