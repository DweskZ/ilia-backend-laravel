<?php

namespace Tests\Unit\LuisFigueroa;

use App\Models\EventoRuta;
use App\Services\LuisFigueroa\ManejadorEventosEntrega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Consumer logic of tema 3.2 without RabbitMQ. */
class ManejadorEventosEntregaTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

    private function cuerpo(array $o = []): string
    {
        return json_encode(array_replace_recursive([
            'id' => self::ID, 'correlation_id' => 'corr-evt-0001', 'tipo' => 'entrega.calificada',
            'prioridad' => 'alta', 'estado' => 'activo', 'datos' => ['entrega_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'],
        ], $o));
    }

    private function filaPrevia(): EventoRuta
    {
        return EventoRuta::create(['evento_id' => self::ID, 'correlation_id' => 'corr-evt-0001', 'tipo' => 'entrega.calificada', 'decision' => 'enrutado', 'destino' => 'ilia.entregas.notificaciones']);
    }

    public function test_consumidor_correcto_confirma_y_registra_quien_consumio(): void
    {
        $this->filaPrevia();

        $this->assertSame(ManejadorEventosEntrega::ACK, (new ManejadorEventosEntrega)->manejar($this->cuerpo(), 'notificaciones'));

        $fila = EventoRuta::first();
        $this->assertSame('notificaciones', $fila->consumido_por);
        $this->assertNotNull($fila->consumido_en);
        $this->assertSame('enrutado', $fila->decision);
    }

    public function test_mensaje_en_la_cola_equivocada_se_rechaza_y_queda_invalido(): void
    {
        $this->filaPrevia();

        $this->assertSame(ManejadorEventosEntrega::REJECT, (new ManejadorEventosEntrega)->manejar($this->cuerpo(), 'revision'));

        $fila = EventoRuta::first();
        $this->assertSame('invalido', $fila->decision);
        $this->assertStringContainsString('no corresponde', $fila->motivo);
    }

    public function test_mensaje_malformado_se_registra_como_invalido_y_se_rechaza(): void
    {
        foreach (['esto no es json', '{"foo":1}'] as $malo) {
            $this->assertSame(ManejadorEventosEntrega::REJECT, (new ManejadorEventosEntrega)->manejar($malo, 'intentos'));
        }

        $this->assertSame(2, EventoRuta::where('decision', 'invalido')->count());
        $this->assertSame('intentos', EventoRuta::first()->consumido_por);
    }

    public function test_destino_desconocido_se_rechaza(): void
    {
        $this->assertSame(ManejadorEventosEntrega::REJECT, (new ManejadorEventosEntrega)->manejar($this->cuerpo(), 'otro'));
    }
}
