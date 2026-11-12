<?php

namespace App\Services\LuisFigueroa;

/**
 * Content-Based Router: picks the destination of an event by looking at its
 * `tipo` (the content), using the explicit rules below. It does not publish;
 * it only answers "where does this go?", which keeps it testable on its own.
 *
 * Real topology (declared by `luisfigueroa:topologia-eventos`): direct exchange
 * `ilia.entregas`; each routing key is bound to one durable queue.
 */
class EnrutadorEventosEntrega
{
    public const EXCHANGE = 'ilia.entregas';

    public const NO_ENRUTABLE = ['destino' => 'no_enrutable', 'routing_key' => 'no_enrutable', 'cola' => 'ilia.entregas.no_enrutables'];

    /** tipo => destination (the short name is also the value of `--destino` of the consumer). */
    public const REGLAS = [
        'entrega.recibida' => ['destino' => 'revision', 'routing_key' => 'revision', 'cola' => 'ilia.entregas.revision'],
        'entrega.calificada' => ['destino' => 'notificaciones', 'routing_key' => 'notificaciones', 'cola' => 'ilia.entregas.notificaciones'],
        'entrega.reintento_solicitado' => ['destino' => 'intentos', 'routing_key' => 'intentos', 'cola' => 'ilia.entregas.intentos'],
    ];

    /** AMQP priority (queues are declared with x-max-priority = 10). */
    public const PRIORIDAD_AMQP = ['alta' => 9, 'media' => 5, 'baja' => 1];

    public const MAX_PRIORIDAD = 10;

    /**
     * @return array{destino: string, routing_key: string, cola: string, reconocido: bool, prioridad_amqp: int}
     */
    public function enrutar(array $evento): array
    {
        $regla = self::REGLAS[$evento['tipo'] ?? ''] ?? null;

        return ($regla ?? self::NO_ENRUTABLE) + [
            'reconocido' => $regla !== null,
            'prioridad_amqp' => self::PRIORIDAD_AMQP[$evento['prioridad'] ?? 'media'] ?? 5,
        ];
    }

    /** Queue and expected tipo of each consumer, used to verify a message reached the right place. */
    public static function reglaDeDestino(string $destino): ?array
    {
        foreach (self::REGLAS as $tipo => $regla) {
            if ($regla['destino'] === $destino) {
                return $regla + ['tipo' => $tipo];
            }
        }

        return null;
    }
}
