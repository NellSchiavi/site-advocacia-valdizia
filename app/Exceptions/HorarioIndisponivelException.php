<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançada quando um horário solicitado não está (ou deixou de estar)
 * disponível no momento em que o agendamento é efetivamente gravado.
 */
class HorarioIndisponivelException extends RuntimeException
{
    public static function paraHorario(string $data, string $horaInicio): self
    {
        return new self("O horário {$horaInicio} de {$data} não está disponível. Escolha outro horário.");
    }
}
