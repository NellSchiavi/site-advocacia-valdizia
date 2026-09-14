<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de redefinição de senha do painel administrativo (Fase 4F). O
 * token em si é gerado/validado 100% pelo broker nativo do Laravel
 * (Illuminate\Auth\Passwords\PasswordBroker + tabela password_reset_tokens)
 * — este Mailable só decide a apresentação, mantendo a identidade visual já
 * usada em ConsultaConfirmadaMail/ConsultaCanceladaMail.
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $url,
        public readonly int $expiraEmMinutos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Redefinição de senha — Painel administrativo',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.redefinir-senha',
            text: 'emails.redefinir-senha-texto',
        );
    }
}
