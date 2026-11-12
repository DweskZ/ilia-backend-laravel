<?php

namespace App\Services\LuisFigueroa\Mensajeria;

use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/** RabbitMQ implementation (php-amqplib). One short connection per publication. */
class PublicadorAmqp implements PublicadorMensajes
{
    public function asegurarCola(string $cola): void
    {
        $this->conCanal(function ($canal) use ($cola) {
            // durable = true: the queue survives a broker restart.
            $canal->queue_declare($cola, false, true, false, false);
        });
    }

    public function publicar(string $exchange, string $routingKey, array $mensaje, ?int $prioridad = null): void
    {
        $this->conCanal(function ($canal) use ($exchange, $routingKey, $mensaje, $prioridad) {
            $propiedades = [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // survives a broker restart
                'message_id' => $mensaje['message_id'] ?? $mensaje['id'] ?? null,
                'correlation_id' => $mensaje['correlation_id'] ?? null,
                'type' => $mensaje['tipo'] ?? null,
            ];
            if ($prioridad !== null) {
                $propiedades['priority'] = $prioridad;
            }

            $canal->basic_publish(
                new AMQPMessage(json_encode($mensaje, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), array_filter($propiedades, fn ($v) => $v !== null)),
                $exchange,
                $routingKey,
            );
        });
    }

    private function conCanal(callable $accion): void
    {
        try {
            $conexion = ConexionRabbitMq::abrir();
            $canal = $conexion->channel();
            $accion($canal);
            $canal->close();
            $conexion->close();
        } catch (Throwable $e) {
            throw new BrokerNoDisponibleException('RabbitMQ no disponible: '.$e->getMessage(), 0, $e);
        }
    }
}
