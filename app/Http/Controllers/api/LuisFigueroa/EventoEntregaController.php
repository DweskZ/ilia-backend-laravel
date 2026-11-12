<?php

namespace App\Http\Controllers\api\LuisFigueroa;

use App\Http\Controllers\Controller;
use App\Http\Requests\LuisFigueroa\EventoEntregaRequest;
use App\Models\EventoRuta;
use App\Services\LuisFigueroa\Mensajeria\BrokerNoDisponibleException;
use App\Services\LuisFigueroa\ProcesadorEventosEntrega;
use Illuminate\Http\JsonResponse;

class EventoEntregaController extends Controller
{
    // POST /api/eventos/entregas
    public function store(EventoEntregaRequest $request, ProcesadorEventosEntrega $procesador): JsonResponse
    {
        $evento = $request->validated();

        if (EventoRuta::where('evento_id', $evento['id'])->exists()) {
            return response()->json(['mensaje' => 'Evento ya recibido (id duplicado).', 'evento_id' => $evento['id']], 409, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            $r = $procesador->procesar($evento);
        } catch (BrokerNoDisponibleException) {
            return response()->json(['mensaje' => 'El servicio de mensajería no está disponible o la topología no está declarada.'], 503, [], JSON_UNESCAPED_UNICODE);
        }

        [$estado, $mensaje] = match ($r['decision']) {
            EventoRuta::ENRUTADO => [202, 'Evento enrutado'],
            EventoRuta::NO_ENRUTABLE => [202, 'Evento sin ruta conocida; enviado a no_enrutables'],
            default => [200, 'Evento filtrado; no se enruta'], // filtrado: a business decision, not an error
        };

        return response()->json(['mensaje' => $mensaje] + $r, $estado, ['X-Correlation-ID' => $r['correlation_id']], JSON_UNESCAPED_UNICODE);
    }
}
