<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['data', 'tipo', 'hora_inicio', 'hora_fim', 'motivo', 'created_by'])]
class AvailabilityException extends Model
{
    public const TIPO_FECHADO_TOTAL = 'fechado_total';

    public const TIPO_FECHADO_PARCIAL = 'fechado_parcial';

    public const TIPO_HORARIO_ESPECIAL = 'horario_especial';

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeNaData($query, $data)
    {
        return $query->whereDate('data', $data);
    }
}
