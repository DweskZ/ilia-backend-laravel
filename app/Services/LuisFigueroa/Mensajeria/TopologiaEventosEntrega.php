<?php

namespace App\Services\LuisFigueroa\Mensajeria;

use App\Services\LuisFigueroa\EnrutadorEventosEntrega as R;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

/** Declares (idempotently) the exchange, the four queues and their bindings. */
class TopologiaEventosEntrega
{
    /** @return list<string> human-readable lines describing what was declared */
    public function declarar(AMQPChannel $canal): array
    {
        // direct exchange, durable, not auto-delete
        $canal->exchange_declare(R::EXCHANGE, 'direct', false, true, false);
        $lineas = ['exchange '.R::EXCHANGE.' (direct, durable)'];

        $destinos = [...array_values(R::REGLAS), R::NO_ENRUTABLE];

        foreach ($destinos as $d) {
            // x-max-priority lets `prioridad: alta` jump ahead inside the functional queues.
            $args = $d === R::NO_ENRUTABLE ? [] : ['x-max-priority' => R::MAX_PRIORIDAD];
            $canal->queue_declare($d['cola'], false, true, false, false, false, new AMQPTable($args));
            $canal->queue_bind($d['cola'], R::EXCHANGE, $d['routing_key']);
            $lineas[] = "queue {$d['cola']} (durable) <- routing key '{$d['routing_key']}'";
        }

        return $lineas;
    }
}
