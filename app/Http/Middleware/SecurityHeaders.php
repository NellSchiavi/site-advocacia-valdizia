<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers de segurança aplicados a toda resposta do grupo "web" (site
 * público e painel administrativo).
 *
 * CSP: todo o front-end é servido pelo próprio domínio via Vite (JS, CSS e
 * fontes, sem CDN nem recursos externos), então font-src/img-src/connect-src
 * ficam restritos a 'self'. script-src e style-src têm duas concessões
 * exigidas pela build padrão do Alpine.js (não a @alpinejs/csp); sem elas a
 * interface deixa de funcionar:
 *
 *  - 'unsafe-eval' em script-src: Alpine avalia expressões de x-data/x-show/
 *    @click/etc. via `new Function()`.
 *  - 'unsafe-inline' em style-src: x-show/x-transition (usados até no
 *    header público, presente em toda página) escondem/mostram elementos
 *    escrevendo direto em `element.style.display`/propriedades de transição
 *    via JS — CSP trata isso como "inline style" mesmo não vindo de nenhum
 *    atributo style="" no HTML-fonte, e não existe hash/nonce que cubra
 *    valor calculado em runtime.
 *
 * Remover essas concessões exige migrar para a build CSP do Alpine
 * (registrar componentes via Alpine.data() e reescrever as diretivas nas
 * views). Nenhuma das duas exceções permite executar um <script> ou
 * <style> injetado por payload de XSS refletido: script-src/style-src ainda
 * exigem 'self' para a ORIGEM do recurso, as exceções só liberam que o
 * bundle JÁ confiável (nosso próprio) manipule eval/estilo internamente.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return $this->aplicar($next($request));
    }

    /**
     * Também chamado por bootstrap/app.php (Exceptions::respond()): respostas
     * geradas a partir de exceções (redirect de guest/auth, 404, 419, 500...)
     * são montadas fora do pipeline de middleware e, sem isso, ficariam sem
     * os headers de segurança.
     */
    public function aplicar(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]));

        return $response;
    }
}
