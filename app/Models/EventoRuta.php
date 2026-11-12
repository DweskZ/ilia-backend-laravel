<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Routing log of entrega events (tema 3.2): one row per decision taken. */
class EventoRuta extends Model
{
    public const ENRUTADO = 'enrutado';

    public const FILTRADO = 'filtrado';

    public const NO_ENRUTABLE = 'no_enrutable';

    public const INVALIDO = 'invalido';

    protected $table = 'evento_ruta';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['recibido_en' => 'datetime', 'consumido_en' => 'datetime'];
    }
}
