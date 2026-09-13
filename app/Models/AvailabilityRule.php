<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['day_of_week', 'hora_inicio', 'hora_fim', 'ativo'])]
class AvailabilityRule extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeParaDiaDaSemana($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }
}
