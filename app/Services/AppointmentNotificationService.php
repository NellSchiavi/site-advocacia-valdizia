<?php

namespace App\Services;

use App\Mail\ConsultaCanceladaMail;
use App\Mail\ConsultaConfirmadaMail;
use App\Models\Appointment;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fase 4E: único ponto que efetivamente envia e-mail transacional ao
 * cliente. Deliberadamente separado de AppointmentService/AvailabilityService
 * (Services de domínio — não devem conter lógica de apresentação) e chamado
 * sempre DEPOIS que a operação já foi persistida (fora de qualquer
 * DB::transaction()) pelos três caminhos que criam/cancelam Appointment:
 * AppointmentController::store() (confirmação), AdminAgendaController::cancelar()
 * (cancelamento manual) e AdminAvailabilityService (cancelamento por alteração
 * de disponibilidade). Uma falha de envio nunca é propagada — é só registrada
 * (item 14): a operação que já está no banco não pode ser desfeita por um
 * problema de SMTP/API de e-mail.
 */
class AppointmentNotificationService
{
    public function enviarConfirmacao(Appointment $appointment): void
    {
        $this->enviar($appointment, new ConsultaConfirmadaMail($appointment), 'confirmação');
    }

    public function enviarCancelamento(Appointment $appointment): void
    {
        $this->enviar($appointment, new ConsultaCanceladaMail($appointment), 'cancelamento');
    }

    private function enviar(Appointment $appointment, Mailable $mail, string $tipo): void
    {
        try {
            Mail::to($appointment->email_cliente)->send($mail);
        } catch (\Throwable $e) {
            // PII minimizada no log (item 14): id + tipo de evento, nunca o
            // e-mail do cliente nem o conteúdo da mensagem.
            Log::warning("Falha ao enviar e-mail de {$tipo}.", [
                'appointment_id' => $appointment->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
