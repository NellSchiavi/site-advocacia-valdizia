<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'mes', 'dia', 'ano', 'recorrente_anual', 'bloqueado'])]
class Holiday extends Model
{
    protected function casts(): array
    {
        return [
            'mes' => 'integer',
            'dia' => 'integer',
            'ano' => 'integer',
            'recorrente_anual' => 'boolean',
            'bloqueado' => 'boolean',
        ];
    }

    public function scopeBloqueado($query)
    {
        return $query->where('bloqueado', true);
    }

    public function scopeParaData($query, int $mes, int $dia, ?int $ano = null)
    {
        return $query->where('mes', $mes)->where('dia', $dia)
            ->where(function ($q) use ($ano) {
                $q->where('recorrente_anual', true)
                    ->orWhere('ano', $ano);
            });
    }
}
