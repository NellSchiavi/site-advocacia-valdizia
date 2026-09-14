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
     * Fase 5: nome como o site deve apresentar ao visitante/cliente final —
     * a cliente pediu "Direito Cível" na comunicação pública, mas o nome
     * interno (banco, painel administrativo, e-mails internos) permanece
     * "Direito Civil" (é a grafia já usada em toda a arquitetura desde a
     * Fase 0/2). Não altera o dado persistido — só a apresentação nas
     * páginas/e-mails voltados ao cliente final.
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
