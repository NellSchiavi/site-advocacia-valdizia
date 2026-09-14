<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminForgotPasswordRequest;
use App\Http\Requests\Admin\AdminResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Fase 4F: recuperação de senha do painel administrativo. Usa o
 * PasswordBroker nativo do Laravel (token com hash em password_reset_tokens,
 * expiração e invalidação após uso já resolvidas pelo framework) — nenhuma
 * criptografia/expiração própria é implementada aqui.
 *
 * Mensagem pública SEMPRE genérica (item 5 da Fase 4F): o retorno de
 * store() nunca varia entre "e-mail existe" e "e-mail não existe", e o
 * status retornado pelo broker é deliberadamente ignorado com esse
 * propósito — só usado para decidir SE um e-mail foi enviado, nunca para
 * mudar o que o usuário vê.
 */
class AdminPasswordResetController extends Controller
{
    private const MENSAGEM_GENERICA = 'Se houver uma conta associada a este e-mail, enviaremos instruções para redefinir sua senha.';

    public function create(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function store(AdminForgotPasswordRequest $request): RedirectResponse
    {
        Password::broker('users')->sendResetLink($request->only('email'));

        return back()->with('status', self::MENSAGEM_GENERICA);
    }

    public function edit(string $token): View
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => request()->query('email', ''),
        ]);
    }

    public function update(AdminResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $senha): void {
                $user->forceFill(['password' => $senha])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Mensagem única para token inválido/expirado/e-mail sem conta —
            // mesmo princípio de não diferenciar respostas (item 5/6).
            return back()->withErrors([
                'email' => 'Não foi possível redefinir a senha. O link pode ter expirado — solicite um novo.',
            ]);
        }

        return redirect()->route('admin.login')->with('status', 'Senha redefinida com sucesso. Você já pode entrar com a nova senha.');
    }
}
