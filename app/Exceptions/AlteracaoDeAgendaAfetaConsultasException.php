<?php

namespace App\Exceptions;

use App\Models\Appointment;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Lançada dentro da transação de uma alteração administrativa de
 * disponibilidade (Fase 4C) quando ela afetaria consultas já existentes e o
 * administrador ainda não confirmou a operação — o AdminAvailabilityService
 * captura esta exceção para desfazer a alteração tentativa (rollback) e
 * devolver ao controller a lista exata de consultas que seriam canceladas,
 * para exibir a confirmação explícita exigida pelo item 6 da Fase 4C.
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
