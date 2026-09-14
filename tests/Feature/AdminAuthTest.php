<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Fase 4A: autenticação e estrutura base do painel administrativo. Cobre só
 * login/logout/autorização/dashboard — nenhuma funcionalidade de agenda,
 * disponibilidade, feriados ou configurações existe ainda.
 */
class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private function criarAdmin(array $atributos = []): User
    {
        // ->fresh(): "role" não é mass-assignable (só name/email/password) e
        // nasce do default do schema ("admin") aplicado pelo MySQL no INSERT
        // — recarregar do banco garante que o objeto em memória reflita esse
        // valor (senão o Gate de autorização vê role=null neste mesmo request).
        return User::factory()->create(array_merge([
            'email' => 'admin@valdiziamelo.local',
            'password' => 'SenhaValida123!',
        ], $atributos))->fresh();
    }

    public function test_usuario_nao_autenticado_e_redirecionado_para_o_login(): void
    {
        $resposta = $this->get('/admin');

        $resposta->assertRedirect(route('admin.login'));
    }

    public function test_pagina_de_login_carrega(): void
    {
        $resposta = $this->get(route('admin.login'));

        $resposta->assertOk();
        $resposta->assertSee('Painel administrativo');
        $resposta->assertSee('Entrar');
    }

    public function test_credenciais_validas_realizam_login(): void
    {
        $admin = $this->criarAdmin();

        $resposta = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'SenhaValida123!',
        ]);

        $resposta->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_credenciais_invalidas_sao_recusadas_com_mensagem_generica(): void
    {
        $admin = $this->criarAdmin();

        // Senha errada.
        $respostaSenhaErrada = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'senha-errada',
        ]);
        $respostaSenhaErrada->assertSessionHasErrors('email');
        $this->assertGuest();
        $mensagemSenhaErrada = session('errors')->first('email');
        $this->assertSame('Credenciais inválidas.', $mensagemSenhaErrada);

        // E-mail inexistente — a mensagem precisa ser IDÊNTICA à de senha
        // errada, para não revelar se o e-mail existe ou não (item 5).
        $respostaEmailInexistente = $this->post(route('admin.login.store'), [
            'email' => 'nao-existe@example.com',
            'password' => 'qualquer-coisa',
        ]);
        $respostaEmailInexistente->assertSessionHasErrors('email');
        $this->assertGuest();
        $mensagemEmailInexistente = session('errors')->first('email');
        $this->assertSame('Credenciais inválidas.', $mensagemEmailInexistente);
    }

    public function test_usuario_autenticado_acessa_o_dashboard(): void
    {
        $admin = $this->criarAdmin();

        $resposta = $this->actingAs($admin)->get(route('admin.dashboard'));

        $resposta->assertOk();
        $resposta->assertSee('Dashboard');
        $resposta->assertSee('Consultas hoje');
    }

    public function test_usuario_autenticado_nao_acessa_login_novamente(): void
    {
        $admin = $this->criarAdmin();

        $resposta = $this->actingAs($admin)->get(route('admin.login'));

        $resposta->assertRedirect('/admin');
    }

    public function test_usuario_autenticado_consegue_fazer_logout(): void
    {
        $admin = $this->criarAdmin();

        $resposta = $this->actingAs($admin)->post(route('admin.logout'));

        $resposta->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_apos_logout_admin_volta_a_exigir_autenticacao(): void
    {
        $admin = $this->criarAdmin();

        $this->actingAs($admin)->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $resposta = $this->get('/admin');
        $resposta->assertRedirect(route('admin.login'));
    }

    public function test_logout_via_get_nao_existe(): void
    {
        $admin = $this->criarAdmin();

        $resposta = $this->actingAs($admin)->get('/admin/logout');

        $resposta->assertStatus(405);
        $this->assertAuthenticatedAs($admin);
    }

    /**
     * A estrutura de autorização atual (users.role, enum só com "admin") não
     * permite persistir um usuário com outro papel no banco — então este
     * teste prova a regra do Gate diretamente, sem round-trip HTTP: um
     * usuário sem role "admin" nunca passa pelo Gate "access-admin-panel".
     */
    public function test_usuario_sem_papel_admin_nao_passa_pelo_gate_do_painel(): void
    {
        $usuarioSemPapelAdmin = new User(['name' => 'Outro', 'email' => 'outro@example.com', 'password' => 'irrelevante']);
        $usuarioSemPapelAdmin->role = 'outro-papel';

        $this->assertFalse(Gate::forUser($usuarioSemPapelAdmin)->allows('access-admin-panel'));
    }

    // ==========================================================
    // Fase 4F — session fixation
    // ==========================================================

    public function test_login_bem_sucedido_regenera_o_id_da_sessao(): void
    {
        $admin = $this->criarAdmin();

        // Inicia a sessão como visitante antes de autenticar.
        $this->get(route('admin.login'));
        $idAntes = session()->getId();

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'SenhaValida123!',
        ]);

        $this->assertNotSame($idAntes, session()->getId());
    }

    public function test_logout_invalida_a_sessao(): void
    {
        $admin = $this->criarAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'));
        $idAntesDoLogout = session()->getId();

        $this->post(route('admin.logout'));

        $this->assertNotSame($idAntesDoLogout, session()->getId());
        $this->assertGuest();
    }

    public function test_rate_limit_do_login_bloqueia_apos_exceder_tentativas(): void
    {
        $admin = $this->criarAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.store'), [
                'email' => $admin->email,
                'password' => 'senha-errada',
            ]);
        }

        // Sexta tentativa, mesmo com a senha CORRETA, deve ser bloqueada pelo
        // rate limit — prova que a proteção contra brute force independe da
        // validade das credenciais na tentativa seguinte.
        $resposta = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'SenhaValida123!',
        ]);

        $resposta->assertSessionHasErrors('email');
        $this->assertStringContainsString('Muitas tentativas', session('errors')->first('email'));
        $this->assertGuest();
    }
}
