<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validação e autenticação do login administrativo.
 *
 * A mensagem de erro é sempre genérica ("Credenciais inválidas.") e não
 * revela se o e-mail existe. O throttling por e-mail+IP segue o padrão
 * nativo do Laravel contra força bruta.
 */
class AdminLoginRequest extends FormRequest
{
    private const MAX_TENTATIVAS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credenciais = $this->only('email', 'password');

        if (! Auth::attempt($credenciais, $this->boolean('lembrar'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        // Estar autenticado não implica permissão administrativa: só o papel
        // "admin" pode manter a sessão do painel aberta.
        if (Auth::user()->role !== 'admin') {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_TENTATIVAS)) {
            return;
        }

        $segundos = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas de login. Tente novamente em {$segundos} segundos.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }
}
