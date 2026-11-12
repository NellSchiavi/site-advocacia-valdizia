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
     * Substitui a notificação nativa (Illuminate\Auth\Notifications\
     * ResetPassword) por um Mailable com a identidade visual do projeto. A
     * geração e a validação do token continuam a cargo do PasswordBroker.
     * Falhas de envio não são propagadas, para que Password::sendResetLink()
     * sempre devolva a mesma mensagem genérica.
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
