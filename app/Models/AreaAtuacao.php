<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'nome', 'descricao', 'icone', 'ordem', 'ativo'])]
class AreaAtuacao extends Model
{
    protected $table = 'areas_atuacao';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    /**
     * Nome apresentado ao público. A comunicação pública usa "Direito
     * Cível", enquanto o nome interno (banco, painel administrativo)
     * permanece "Direito Civil". Não altera o dado persistido.
     */
    protected function nomePublico(): Attribute
    {
        return Attribute::get(fn () => $this->nome === 'Direito Civil' ? 'Direito Cível' : $this->nome);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('ordem');
    }
}
