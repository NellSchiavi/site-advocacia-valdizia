<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail enviado ao cliente quando uma consulta é cancelada — reutilizado
 * pelos dois caminhos de cancelamento (Fase 4E): manual, pela agenda
 * administrativa (AdminAgendaController::cancelar()), e por alteração de
 * disponibilidade (AdminAvailabilityService). Um único template para os
 * dois: o texto é neutro ("sua consulta foi cancelada"), sem atribuir um
 * motivo específico que só é verdadeiro para um dos dois caminhos (item 3).
 * Usa a arquitetura de mail já existente no projeto (config/mail.php, .env
 * MAIL_*) — nenhum mecanismo de envio paralelo.
 */
class ConsultaCanceladaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Consulta cancelada — Advocacia e Assessoria Jurídica',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consulta-cancelada',
            text: 'emails.consulta-cancelada-texto',
        );
    }
}
