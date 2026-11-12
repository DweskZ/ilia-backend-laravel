<?php

namespace App\Services\LuisFigueroa\Mensajeria;

use PhpAmqpLib\Connection\AMQPStreamConnection;

/** Opens AMQP connections from config('luisfigueroa.rabbitmq'). */
class ConexionRabbitMq
{
    public static function abrir(): AMQPStreamConnection
    {
        $c = config('luisfigueroa.rabbitmq');

        return new AMQPStreamConnection(
            $c['host'], $c['port'], $c['user'], $c['password'], $c['vhost'],
            connection_timeout: 3.0, read_write_timeout: 10.0, heartbeat: 30,
        );
    }
}
