<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Não é dado de negócio: existe só como mecanismo de lock transacional por
 * data para prevenir concorrência em agendamentos (ver AppointmentService).
 */
class AgendaLock extends Model
{
    protected $primaryKey = 'data';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['data'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
