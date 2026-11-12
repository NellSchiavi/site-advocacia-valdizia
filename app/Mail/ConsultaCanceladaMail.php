<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail enviado ao cliente quando uma consulta é cancelada, usado pelos dois
 * caminhos de cancelamento: manual, pela agenda administrativa
 * (AdminAgendaController::cancelar()), e por alteração de disponibilidade
 * (AdminAvailabilityService). O texto é neutro ("sua consulta foi
 * cancelada") para valer nos dois casos.
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
