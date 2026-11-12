<?php

namespace Tests\Support;

use App\Services\LuisFigueroa\Mensajeria\BrokerNoDisponibleException;
use App\Services\LuisFigueroa\Mensajeria\PublicadorMensajes;

/** In-memory publisher used by the tests: no RabbitMQ needed. */
class PublicadorFalso implements PublicadorMensajes
{
    /** @var list<array{exchange: string, routing_key: string, mensaje: array, prioridad: ?int}> */
    public array $publicados = [];

    /** @var list<string> */
    public array $colas = [];

    public bool $caido = false;

    public function asegurarCola(string $cola): void
    {
        $this->falloSiCaido();
        $this->colas[] = $cola;
    }

    public function publicar(string $exchange, string $routingKey, array $mensaje, ?int $prioridad = null): void
    {
        $this->falloSiCaido();
        $this->publicados[] = compact('exchange', 'mensaje', 'prioridad') + ['routing_key' => $routingKey];
    }

    private function falloSiCaido(): void
    {
        if ($this->caido) {
            throw new BrokerNoDisponibleException('broker simulado caído');
        }
    }
}
