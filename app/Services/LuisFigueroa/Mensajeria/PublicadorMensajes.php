<?php

namespace App\Services\LuisFigueroa\Mensajeria;

/**
 * Port for publishing messages to the broker. The application depends on this
 * interface; the AMQP implementation is bound in AppServiceProvider and tests
 * swap it for an in-memory fake.
 */
interface PublicadorMensajes
{
    /** Declares a durable queue (idempotent). */
    public function asegurarCola(string $cola): void;

    /**
     * Publishes a persistent JSON message.
     *
     * @param  string  $exchange  '' = default exchange (routing key = queue name)
     * @param  array<string, mixed>  $mensaje
     *
     * @throws BrokerNoDisponibleException when the broker cannot be reached
     */
    public function publicar(string $exchange, string $routingKey, array $mensaje, ?int $prioridad = null): void;
}
