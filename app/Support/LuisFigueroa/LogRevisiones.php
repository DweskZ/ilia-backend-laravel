<?php

namespace App\Support\LuisFigueroa;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/** Dedicated log channel of the review pipeline: storage/logs/luisfigueroa-revisiones.log */
class LogRevisiones
{
    public static function canal(): LoggerInterface
    {
        return Log::channel('luisfigueroa_revisiones');
    }
}
