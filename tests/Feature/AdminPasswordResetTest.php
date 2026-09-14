<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Fase 4F: recuperação de senha do painel administrativo. Cobre o fluxo
 * completo via PasswordBroker nativo — nunca revelando se um e-mail tem ou
 * não conta associada.
 */
class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAGEM_GENERICA = 'Se houver uma conta associada a este e-mail, enviaremos instruções para redefinir sua senha.';

    private function criarAdmin(array $atributos = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'admin@valdiziamelo.local',
            'password' => 'SenhaAntiga123!',
        ], $atributos))->fresh();
    }

    public function test_pagina_de_recuperacao_carrega(): void
    {
        $resposta = $this->get(route('admin.password.request'));

        $resposta->assertOk();
        $resposta->assertSee('Recuperar senha');
    }

    public function test_usuario_autenticado_nao_acessa_pagina_de_recuperacao(): void
    {
        $admin = $this->criarAdmin();

        $resposta = $this->actingAs($admin)->get(route('admin.password.request'));

        $resposta->assertRedirect('/admin');
    }

    public function test_solicitacao_com_email_existente_envia_email_e_retorna_mensagem_generica(): void
    {
        Mail::fake();
        $admin = $this->criarAdmin();

        $resposta = $this->post(route('admin.password.email'), ['email' => $admin->email]);

        $resposta->assertRedirect();
        $this->assertSame(self::MENSAGEM_GENERICA, session('status'));
        Mail::assertSent(ResetPasswordMail::class);
    }

    public function test_solicitacao_com_email_inexistente_retorna_mensagem_identica_e_nao_envia_email(): void
    {
        Mail::fake();

        $resposta = $this->post(route('admin.password.email'), ['email' => 'nao-existe@example.com']);

        $resposta->assertRedirect();
        // Mesma mensagem literal do teste anterior — nenhuma diferença
        // observável entre e-mail existente e inexistente (item 5).
        $this->assertSame(self::MENSAGEM_GENERICA, session('status'));
        Mail::assertNothingSent();
    }

    public function test_pagina_de_redefinicao_carrega_com_token_na_url(): void
    {
        $admin = $this->criarAdmin();
        $token = Password::broker('users')->createToken($admin);

        $resposta = $this->get(route('admin.password.reset', ['token' => $token, 'email' => $admin->email]));

        $resposta->assertOk();
        $resposta->assertSee('Redefinir senha');
    }

    public function test_token_valido_permite_redefinir_a_senha(): void
    {
        $admin = $this->criarAdmin();
        $token = Password::broker('users')->createToken($admin);

        $resposta = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'SenhaNova456!',
            'password_confirmation' => 'SenhaNova456!',
        ]);

        $resposta->assertRedirect(route('admin.login'));
        $this->assertNotNull(session('status'));
        $this->assertTrue(Hash::check('SenhaNova456!', $admin->fresh()->password));
    }

    public function test_login_funciona_com_a_nova_senha_apos_redefinicao(): void
    {
        $admin = $this->criarAdmin();
        $token = Password::broker('users')->createToken($admin);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'SenhaNova456!',
            'password_confirmation' => 'SenhaNova456!',
        ]);

        $resposta = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'SenhaNova456!',
        ]);

        $resposta->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_token_invalido_e_rejeitado_e_nao_altera_a_senha(): void
    {
        $admin = $this->criarAdmin();

        $resposta = $this->post(route('admin.password.update'), [
            'token' => 'token-fabricado-invalido',
            'email' => $admin->email,
            'password' => 'SenhaNova456!',
            'password_confirmation' => 'SenhaNova456!',
        ]);

        $resposta->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('SenhaAntiga123!', $admin->fresh()->password));
    }

    public function test_token_expirado_e_rejeitado(): void
    {
        $admin = $this->criarAdmin();
        $token = Password::broker('users')->createToken($admin);

        // "expire" está em minutos (config/auth.php) — viaja além disso.
        $minutosExpiracao = (int) config('auth.passwords.users.expire');
        $this->travel($minutosExpiracao + 1)->minutes();

        $resposta = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'SenhaNova456!',
            'password_confirmation' => 'SenhaNova456!',
        ]);

        $resposta->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('SenhaAntiga123!', $admin->fresh()->password));
    }

    public function test_token_nao_e_reutilizavel(): void
    {
        $admin = $this->criarAdmin();
        $token = Password::broker('users')->createToken($admin);

        $primeiraTentativa = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'SenhaNova456!',
            'password_confirmation' => 'SenhaNova456!',
        ]);
        $primeiraTentativa->assertRedirect(route('admin.login'));

        // Mesmo token, segunda tentativa com outra senha — já foi consumido.
        $segundaTentativa = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'SenhaOutra789!',
            'password_confirmation' => 'SenhaOutra789!',
        ]);

        $segundaTentativa->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('SenhaNova456!', $admin->fresh()->password));
    }

    public function test_confirmacao_de_senha_precisa_corresponder(): void
    {
        $admin = $this->criarAdmin();
        $token = Password::broker('users')->createToken($admin);

        $resposta = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'SenhaNova456!',
            'password_confirmation' => 'SenhaDiferente!',
        ]);

        $resposta->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('SenhaAntiga123!', $admin->fresh()->password));
    }

    public function test_rate_limit_da_solicitacao_de_recuperacao(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.password.email'), ['email' => "teste{$i}@example.com"]);
        }

        $resposta = $this->post(route('admin.password.email'), ['email' => 'mais-uma@example.com']);

        $resposta->assertStatus(429);
    }

    public function test_email_de_redefinicao_contem_link_com_token_e_prazo_de_expiracao(): void
    {
        $admin = $this->criarAdmin();
        $token = Password::broker('users')->createToken($admin);
        $url = route('admin.password.reset', ['token' => $token, 'email' => $admin->email]);

        $mail = new ResetPasswordMail(
            nome: $admin->name,
            url: $url,
            expiraEmMinutos: (int) config('auth.passwords.users.expire'),
        );
        $html = $mail->render();

        $this->assertStringContainsString($token, $html);
        $this->assertStringContainsString((string) config('auth.passwords.users.expire'), $html);
    }
}
