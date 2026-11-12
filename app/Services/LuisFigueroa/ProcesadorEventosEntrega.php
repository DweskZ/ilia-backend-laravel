<?php

namespace App\Services\LuisFigueroa;

use App\Models\EventoRuta;
use App\Services\LuisFigueroa\Mensajeria\PublicadorMensajes;
use App\Support\LuisFigueroa\LogEventos;
use Illuminate\Support\Str;

/**
 * Producer pipeline: (already validated event) -> Message Filter -> Content-Based
 * Router -> publish -> routing log. Throws BrokerNoDisponibleException on transport failure.
 */
class ProcesadorEventosEntrega
{
    public function __construct(
        private readonly FiltroEventosEntrega $filtro,
        private readonly EnrutadorEventosEntrega $enrutador,
        private readonly PublicadorMensajes $publicador,
    ) {}

    /** @return array<string, mixed> decision payload for the HTTP response */
    public function procesar(array $evento): array
    {
        $evento['correlation_id'] = $evento['correlation_id'] ?? (string) Str::uuid();
        $base = [
            'evento_id' => $evento['id'],
            'correlation_id' => $evento['correlation_id'],
            'tipo' => $evento['tipo'],
            'prioridad' => $evento['prioridad'],
            'estado_entrega' => $evento['estado'],
        ];
        $ctx = ['correlation_id' => $evento['correlation_id'], 'evento_id' => $evento['id'], 'tipo' => $evento['tipo']];

        // 1) Message Filter
        if ($motivo = $this->filtro->motivoDeRechazo($evento)) {
            EventoRuta::create($base + ['decision' => EventoRuta::FILTRADO, 'motivo' => $motivo]);
            LogEventos::canal()->info('productor: evento filtrado, no se publica', $ctx + ['motivo' => $motivo]);

            return [
                'decision' => EventoRuta::FILTRADO,
                'motivo' => $motivo,
                'evento_id' => $evento['id'],
                'correlation_id' => $evento['correlation_id'],
            ];
        }

        // 2) Content-Based Router
        $ruta = $this->enrutador->enrutar($evento);
        $decision = $ruta['reconocido'] ? EventoRuta::ENRUTADO : EventoRuta::NO_ENRUTABLE;
        $motivo = $ruta['reconocido'] ? null : "Tipo de evento no reconocido: '{$evento['tipo']}'.";

        $fila = EventoRuta::create($base + [
            'decision' => $decision, 'destino' => $ruta['cola'], 'routing_key' => $ruta['routing_key'], 'motivo' => $motivo,
        ]);

        // 3) Publish to the direct exchange with the chosen routing key
        $mensaje = $evento + ['enrutado_en' => now()->utc()->format('Y-m-d\TH:i:s\Z')];

        try {
            $this->publicador->publicar(
                EnrutadorEventosEntrega::EXCHANGE,
                $ruta['routing_key'],
                $mensaje,
                $ruta['reconocido'] ? $ruta['prioridad_amqp'] : null,
            );
        } catch (\Throwable $e) {
            $fila->delete(); // nothing was queued: do not record a route that does not exist
            LogEventos::canal()->error('productor: broker no disponible', $ctx + ['error' => $e->getMessage()]);

            throw $e;
        }

        LogEventos::canal()->info('productor: evento '.($ruta['reconocido'] ? 'enrutado' : 'sin ruta, a no_enrutables'), $ctx + [
            'exchange' => EnrutadorEventosEntrega::EXCHANGE, 'routing_key' => $ruta['routing_key'], 'cola' => $ruta['cola'],
        ]);

        return [
            'decision' => $decision,
            'destino' => $ruta['cola'],
            'routing_key' => $ruta['routing_key'],
            'prioridad_amqp' => $ruta['reconocido'] ? $ruta['prioridad_amqp'] : null,
            'motivo' => $motivo,
            'evento_id' => $evento['id'],
            'correlation_id' => $evento['correlation_id'],
        ];
    }
}
