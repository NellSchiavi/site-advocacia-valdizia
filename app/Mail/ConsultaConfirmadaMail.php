<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail enviado ao cliente quando uma consulta pública é criada com sucesso
 * (Fase 4E). Enviado pelo AppointmentController::store() só depois que
 * AppointmentService::agendar() já retornou (transação já commitada) — nunca
 * dentro da transação, nunca antes da persistência.
 */
class ConsultaConfirmadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Consulta confirmada — Advocacia e Assessoria Jurídica',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consulta-confirmada',
            text: 'emails.consulta-confirmada-texto',
        );
    }
}
