<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Automatic review of the prompt of an entrega (tema 3.1). */
class Revision extends Model
{
    public const PENDIENTE = 'pendiente';

    public const PROCESANDO = 'procesando';

    public const COMPLETADA = 'completada';

    public const FALLIDA = 'fallida';

    protected $table = 'revision';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'intento' => 'integer',
            'puntaje' => 'integer',
            'solicitada_en' => 'datetime',
            'iniciada_en' => 'datetime',
            'finalizada_en' => 'datetime',
        ];
    }
}
