<?php

namespace App\Services\LuisFigueroa;

/**
 * Message Filter: decides, from the CONTENT of the event, whether it continues in
 * the pipeline. Business rule: only events of entregas with estado "activo" are
 * routed; events of "anulada" or "borrador" entregas are discarded on purpose.
 * A filtered event is a business decision (recorded), not a transport failure.
 */
class FiltroEventosEntrega
{
    public const ESTADO_PERMITIDO = 'activo';

    /** @return string|null reason for rejecting the event, or null when it passes */
    public function motivoDeRechazo(array $evento): ?string
    {
        $estado = $evento['estado'] ?? null;

        if ($estado !== self::ESTADO_PERMITIDO) {
            return "La entrega está en estado '{$estado}'; solo se enrutan eventos de entregas '".self::ESTADO_PERMITIDO."'.";
        }

        return null;
    }
}
