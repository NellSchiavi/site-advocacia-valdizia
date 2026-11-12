<?php

namespace App\Exceptions;

use App\Models\Appointment;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Lançada dentro da transação de uma alteração administrativa de
 * disponibilidade quando ela afetaria consultas existentes e o administrador
 * ainda não confirmou a operação. A exceção reverte a alteração (rollback) e
 * leva ao controller a lista de consultas que seriam canceladas, para que a
 * confirmação explícita seja exibida.
 */
class AlteracaoDeAgendaAfetaConsultasException extends RuntimeException
{
    /**
     * @param  Collection<int, Appointment>  $consultasAfetadas
     */
    public function __construct(public readonly Collection $consultasAfetadas)
    {
        parent::__construct('Esta alteração afeta consultas já agendadas — confirmação necessária.');
    }
}
