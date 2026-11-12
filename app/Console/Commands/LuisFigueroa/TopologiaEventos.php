<?php

namespace App\Console\Commands\LuisFigueroa;

use App\Services\LuisFigueroa\Mensajeria\ConexionRabbitMq;
use App\Services\LuisFigueroa\Mensajeria\TopologiaEventosEntrega;
use Illuminate\Console\Command;
use Throwable;

class TopologiaEventos extends Command
{
    protected $signature = 'luisfigueroa:topologia-eventos';

    protected $description = 'Declara (de forma idempotente) el exchange ilia.entregas y sus 4 colas durables (tema 3.2)';

    public function handle(TopologiaEventosEntrega $topologia): int
    {
        try {
            $conexion = ConexionRabbitMq::abrir();
            $canal = $conexion->channel();
            foreach ($topologia->declarar($canal) as $linea) {
                $this->line('  '.$linea);
            }
            $canal->close();
            $conexion->close();
        } catch (Throwable $e) {
            $this->error('No se pudo declarar la topología: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Topología lista.');

        return self::SUCCESS;
    }
}
