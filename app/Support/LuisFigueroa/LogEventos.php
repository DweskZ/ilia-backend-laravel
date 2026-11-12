<?php

namespace App\Support\LuisFigueroa;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/** Dedicated log channel of the event-routing pipeline: storage/logs/luisfigueroa-eventos.log */
class LogEventos
{
    public static function canal(): LoggerInterface
    {
        return Log::channel('luisfigueroa_eventos');
    }
}
