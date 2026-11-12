<?php

namespace App\Console\Commands\LuisFigueroa;

use App\Services\LuisFigueroa\EnrutadorEventosEntrega;
use App\Services\LuisFigueroa\ManejadorEventosEntrega;
use App\Services\LuisFigueroa\Mensajeria\ConexionRabbitMq;
use App\Support\LuisFigueroa\LogEventos;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ConsumirEventos extends Command
{
    protected $signature = 'luisfigueroa:consumir-eventos {--destino= : revision | notificaciones | intentos} {--max=0 : Detenerse tras N mensajes (0 = sin límite)}';

    protected $description = 'Consume una de las colas de eventos de entrega y registra la entrega en evento_ruta (tema 3.2)';

    private bool $detener = false;

    public function handle(ManejadorEventosEntrega $manejador): int
    {
        $destino = (string) $this->option('destino');
        $regla = EnrutadorEventosEntrega::reglaDeDestino($destino);
        if ($regla === null) {
            $this->error('Use --destino=revision, --destino=notificaciones o --destino=intentos.');

            return self::INVALID;
        }

        $max = (int) $this->option('max');
        $procesados = 0;

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->detener = true);
            pcntl_signal(SIGTERM, fn () => $this->detener = true);
        }

        try {
            $conexion = ConexionRabbitMq::abrir();
        } catch (Throwable $e) {
            $this->error('No se pudo conectar a RabbitMQ: '.$e->getMessage());

            return self::FAILURE;
        }

        $canal = $conexion->channel();
        $canal->basic_qos(0, 1, false);

        // The queue must already exist (php artisan luisfigueroa:topologia-eventos): consuming never creates it.
        try {
            $canal->queue_declare($regla['cola'], true); // passive
        } catch (Throwable) {
            $this->error("La cola {$regla['cola']} no existe. Ejecute: php artisan luisfigueroa:topologia-eventos");

            return self::FAILURE;
        }

        $canal->basic_consume($regla['cola'], '', false, false, false, false, function (AMQPMessage $m) use ($manejador, $destino, &$procesados, $max) {
            $accion = $manejador->manejar($m->getBody(), $destino);
            $accion === ManejadorEventosEntrega::ACK ? $m->ack() : $m->reject(false);

            $this->line(sprintf('[%s] %s %s', now()->format('H:i:s'), $destino, $accion));
            if ($max > 0 && ++$procesados >= $max) {
                $this->detener = true;
            }
        });

        LogEventos::canal()->info("consumidor[{$destino}]: iniciado", ['cola' => $regla['cola']]);
        $this->info("Consumidor '{$destino}' escuchando {$regla['cola']} (Ctrl+C para detener)...");

        while (! $this->detener && $canal->is_consuming()) {
            try {
                $canal->wait(null, false, 2);
            } catch (AMQPTimeoutException) {
            } catch (Throwable $e) {
                if ($this->detener) {
                    break;
                }
                $this->error('Conexión con RabbitMQ perdida: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('Consumidor detenido.');
        try {
            $canal->close();
            $conexion->close();
        } catch (Throwable) {
        }

        return self::SUCCESS;
    }
}
