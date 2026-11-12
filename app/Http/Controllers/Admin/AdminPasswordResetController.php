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
 * Recuperação de senha do painel administrativo via PasswordBroker nativo
 * (token com hash em password_reset_tokens, expiração e invalidação após uso
 * resolvidas pelo framework).
 *
 * A resposta pública de store() é sempre a mesma, exista ou não a conta: o
 * status retornado pelo broker é ignorado de propósito, para evitar
 * enumeração de usuários.
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
            // Mensagem única para token inválido/expirado/e-mail sem conta.
            return back()->withErrors([
                'email' => 'Não foi possível redefinir a senha. O link pode ter expirado — solicite um novo.',
            ]);
        }

        return redirect()->route('admin.login')->with('status', 'Senha redefinida com sucesso. Você já pode entrar com a nova senha.');
    }
}
