<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'duracao_consulta_min',
    'intervalo_entre_consultas_min',
    'antecedencia_minima_horas',
    'antecedencia_maxima_dias',
    'modo_confirmacao',
])]
class ConfiguracaoGlobal extends Model
{
    protected $table = 'configuracoes_globais';

    public const MODO_AUTOMATICO = 'automatico';

    public const MODO_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'duracao_consulta_min' => 'integer',
            'intervalo_entre_consultas_min' => 'integer',
            'antecedencia_minima_horas' => 'integer',
            'antecedencia_maxima_dias' => 'integer',
        ];
    }

    /**
     * Configuração é um singleton: sempre há exatamente 1 linha.
     */
    public static function atual(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
