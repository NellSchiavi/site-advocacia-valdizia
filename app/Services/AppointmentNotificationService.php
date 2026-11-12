<?php

namespace App\Services;

use App\Mail\ConsultaCanceladaMail;
use App\Mail\ConsultaConfirmadaMail;
use App\Models\Appointment;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Único ponto de envio de e-mail transacional ao cliente. É chamado sempre
 * depois que a operação foi persistida (fora de qualquer DB::transaction())
 * por AppointmentController::store() (confirmação),
 * AdminAgendaController::cancelar() (cancelamento manual) e
 * AdminAvailabilityService (cancelamento por alteração de disponibilidade).
 * Falhas de envio são apenas registradas em log, nunca propagadas.
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
            // Sem dados pessoais no log: apenas id e tipo de evento.
            Log::warning("Falha ao enviar e-mail de {$tipo}.", [
                'appointment_id' => $appointment->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
