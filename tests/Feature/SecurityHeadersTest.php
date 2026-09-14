<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Fase 4F: confirma que os headers de segurança (SecurityHeaders) chegam em
 * respostas do grupo "web" — tanto no site público quanto no painel admin
 * (que exige redirect para /admin/login, mas a resposta de redirect também
 * passa pelo grupo "web" e já deve carregar os headers).
 */
class SecurityHeadersTest extends TestCase
{
    public function test_homepage_recebe_headers_de_seguranca(): void
    {
        $resposta = $this->get('/');

        $resposta->assertHeader('X-Content-Type-Options', 'nosniff');
        $resposta->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $resposta->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $resposta->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $resposta->assertHeader('Content-Security-Policy');
    }

    public function test_csp_restringe_script_src_a_self_e_unsafe_eval(): void
    {
        $resposta = $this->get('/');

        $csp = $resposta->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'unsafe-eval'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);

        // 'unsafe-inline' é aceito só em style-src (necessário para
        // x-show/x-transition do Alpine, confirmado via Playwright na Fase
        // 4F) — nunca em script-src, que é o que importaria para bloquear
        // um <script> injetado por payload de XSS refletido.
        $scriptSrc = collect(explode(';', $csp))
            ->first(fn ($diretiva) => trim($diretiva) !== '' && str_starts_with(trim($diretiva), 'script-src'));
        $this->assertStringNotContainsString('unsafe-inline', $scriptSrc);
    }

    public function test_pagina_de_login_admin_recebe_headers_de_seguranca(): void
    {
        $resposta = $this->get(route('admin.login'));

        $resposta->assertHeader('X-Content-Type-Options', 'nosniff');
        $resposta->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $resposta->assertHeader('Content-Security-Policy');
    }

    public function test_redirect_para_login_tambem_recebe_headers_de_seguranca(): void
    {
        $resposta = $this->get('/admin');

        $resposta->assertRedirect(route('admin.login'));
        $resposta->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
