<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançada quando uma ação administrativa (cancelar, concluir, marcar não
 * comparecimento) é tentada num agendamento cujo status atual não permite
 * essa transição — ex.: cancelar uma consulta já concluída. Garante que o
 * estado real do banco (possivelmente alterado por outra requisição
 * concorrente) nunca é sobrescrito silenciosamente (Fase 4B, item 9).
 */
class TransicaoDeStatusInvalidaException extends RuntimeException
{
    public static function paraAcao(string $acao, string $statusAtual): self
    {
        return new self("Não é possível {$acao} esta consulta porque o status atual é \"{$statusAtual}\".");
    }

    /**
     * Fase 4B.2: COMPLETED/NO_SHOW só fazem sentido depois que o horário da
     * consulta realmente terminou — marcar uma consulta futura (ou em
     * andamento) como concluída/não comparecida liberaria o horário
     * prematuramente no AvailabilityService, permitindo double-booking.
     */
    public static function paraConsultaNaoEncerrada(string $descricaoDaAcao): self
    {
        return new self("Esta consulta ainda não terminou e não pode ser {$descricaoDaAcao}.");
    }
}
