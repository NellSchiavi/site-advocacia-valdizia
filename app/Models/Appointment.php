<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'area_atuacao_id',
    'nome_cliente',
    'email_cliente',
    'telefone_cliente',
    'observacao',
    'data',
    'hora_inicio',
    'hora_fim',
])]
class Appointment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NO_SHOW = 'no_show';

    /**
     * Status considerados "ativos" — ocupam um horário na agenda.
     */
    public const STATUS_ATIVOS = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function areaAtuacao(): BelongsTo
    {
        return $this->belongsTo(AreaAtuacao::class);
    }

    public function scopeAtivos($query)
    {
        return $query->whereIn('status', self::STATUS_ATIVOS);
    }

    public function scopeNaData($query, $data)
    {
        return $query->whereDate('data', $data);
    }

    /**
     * Filtra agendamentos cujo intervalo [hora_inicio, hora_fim) sobrepõe o
     * intervalo informado. Fórmula padrão de sobreposição de intervalos:
     * existente.inicio < novo.fim E existente.fim > novo.inicio.
     */
    public function scopeSobrepondo($query, string $horaInicio, string $horaFim)
    {
        return $query->where('hora_inicio', '<', $horaFim)
            ->where('hora_fim', '>', $horaInicio);
    }
}
