<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail enviado ao cliente quando uma consulta é cancelada por uma
 * alteração administrativa da agenda (Fase 4C, item 10). Usa a arquitetura
 * de mail já existente no projeto (config/mail.php, .env MAIL_*) — nenhum
 * mecanismo de envio paralelo.
 */
class ConsultaCanceladaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cancelamento de consulta — Dra. Valdizia Melo',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consulta-cancelada',
        );
    }
}
