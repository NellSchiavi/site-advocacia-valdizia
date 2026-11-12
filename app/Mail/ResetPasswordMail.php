<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de redefinição de senha do painel administrativo. O token é gerado
 * e validado pelo PasswordBroker nativo (tabela password_reset_tokens); este
 * Mailable define apenas a apresentação.
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
