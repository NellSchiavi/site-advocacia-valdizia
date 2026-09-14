<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\ResetPasswordMail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Fase 4F: substitui a notificação nativa do Laravel (Illuminate\Auth\
     * Notifications\ResetPassword) por um Mailable próprio, só para manter a
     * mesma identidade visual dos demais e-mails do projeto — o mecanismo de
     * geração/validação do token continua 100% nativo (PasswordBroker),
     * nada aqui reimplementa criptografia ou expiração própria. Falha de
     * envio nunca deve propagar: quem chama isto (Password::sendResetLink())
     * precisa continuar podendo devolver a mesma mensagem genérica ao
     * usuário mesmo que o SMTP esteja indisponível.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token)
    {
        $url = URL::route('admin.password.reset', ['token' => $token, 'email' => $this->email]);

        try {
            Mail::to($this->email)->send(new ResetPasswordMail(
                nome: $this->name,
                url: $url,
                expiraEmMinutos: (int) config('auth.passwords.users.expire'),
            ));
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar e-mail de redefinição de senha.', [
                'user_id' => $this->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
