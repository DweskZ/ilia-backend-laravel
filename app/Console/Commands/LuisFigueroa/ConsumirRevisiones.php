<?php

namespace App\Console\Commands\LuisFigueroa;

use App\Services\LuisFigueroa\ManejadorRevisiones;
use App\Services\LuisFigueroa\Mensajeria\ConexionRabbitMq;
use App\Support\LuisFigueroa\LogRevisiones;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ConsumirRevisiones extends Command
{
    protected $signature = 'luisfigueroa:consumir-revisiones {--max=0 : Detenerse tras N mensajes (0 = sin límite)}';

    protected $description = 'Consume la cola ilia.revisiones y ejecuta la revisión automática de cada pedido (tema 3.1)';

    private bool $detener = false;

    public function handle(ManejadorRevisiones $manejador): int
    {
        $cola = config('luisfigueroa.revision.cola');
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
        $canal->queue_declare($cola, false, true, false, false); // durable, idempotent
        $canal->basic_qos(0, 1, false); // prefetch 1: one unacknowledged message at a time

        $canal->basic_consume($cola, '', false, false, false, false, function (AMQPMessage $mensaje) use ($manejador, &$procesados, $max) {
            $accion = $manejador->manejar($mensaje->getBody());

            // Manual acknowledgement, only after the work is finished.
            match ($accion) {
                ManejadorRevisiones::ACK => $mensaje->ack(),
                ManejadorRevisiones::REJECT => $mensaje->reject(false),
                default => $mensaje->nack(false), // requeue = false (see ManejadorRevisiones)
            };

            $this->line(sprintf('[%s] %s', now()->format('H:i:s'), $accion));
            if ($max > 0 && ++$procesados >= $max) {
                $this->detener = true;
            }
        });

        LogRevisiones::canal()->info('consumer: iniciado', ['cola' => $cola, 'prefetch' => 1]);
        $this->info("Consumiendo '{$cola}' (Ctrl+C para detener)...");

        while (! $this->detener && $canal->is_consuming()) {
            try {
                $canal->wait(null, false, 2); // wake up every 2 s to check the stop flag
            } catch (AMQPTimeoutException) {
                // nothing arrived; keep waiting
            } catch (Throwable $e) {
                if ($this->detener) {
                    break; // interrupted by a signal
                }
                LogRevisiones::canal()->error('consumer: conexión perdida', ['error' => $e->getMessage()]);
                $this->error('Conexión con RabbitMQ perdida: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        LogRevisiones::canal()->info('consumer: detenido');
        $this->info('Consumidor detenido.');
        try {
            $canal->close();
            $conexion->close();
        } catch (Throwable) {
        }

        return self::SUCCESS;
    }
}
