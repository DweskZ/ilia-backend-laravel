<?php

namespace Tests\Unit\LuisFigueroa;

use App\Models\Activity;
use App\Models\AppUser;
use App\Models\Revision;
use App\Models\Submission;
use App\Services\LuisFigueroa\ManejadorRevisiones;
use App\Services\LuisFigueroa\ProcesadorRevisiones;
use App\Services\LuisFigueroa\RevisorPedidos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Consumer logic without RabbitMQ: the handler receives the raw body and returns ack/nack/reject. */
class ManejadorRevisionesTest extends TestCase
{
    use RefreshDatabase;

    private ManejadorRevisiones $manejador;

    private Submission $entrega;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manejador = new ManejadorRevisiones(new ProcesadorRevisiones(new RevisorPedidos(0)));
        $this->entrega = Submission::create([
            'activity_id' => Activity::factory()->create()->id, 'user_id' => AppUser::factory()->create()->id,
            'prompt' => 'Actua como tutor y explica las fracciones a estudiantes de quinto grado con una lista de ejemplos.',
            'attempt' => 1, 'status' => 'recibida', 'sent_at' => now(),
        ]);
    }

    private function cuerpo(array $o = []): string
    {
        return json_encode(array_replace_recursive([
            'message_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'correlation_id' => 'corr-unit-0001',
            'tipo' => 'entrega.revision_solicitada',
            'ocurrido_en' => '2026-11-12T10:00:00Z',
            'payload' => ['entrega_id' => $this->entrega->id, 'intento' => 1],
        ], $o));
    }

    public function test_mensaje_valido_se_procesa_y_confirma(): void
    {
        $this->assertSame(ManejadorRevisiones::ACK, $this->manejador->manejar($this->cuerpo()));

        $rev = Revision::where('correlation_id', 'corr-unit-0001')->first();
        $this->assertSame('completada', $rev->estado);
        $this->assertSame(100, $rev->puntaje); // nivel, materia, tarea, salida y longitud
        $this->assertNotNull($rev->finalizada_en);
    }

    public function test_mensaje_duplicado_ya_completado_se_confirma_sin_reprocesar(): void
    {
        $this->manejador->manejar($this->cuerpo());
        $primero = Revision::first()->finalizada_en;

        $this->assertSame(ManejadorRevisiones::ACK, $this->manejador->manejar($this->cuerpo()));
        $this->assertSame(1, Revision::count());
        $this->assertEquals($primero, Revision::first()->finalizada_en);
    }

    public function test_mensaje_malformado_se_rechaza_sin_crear_filas(): void
    {
        foreach (['no es json', '{}', $this->cuerpo(['tipo' => 'otro.tipo']), $this->cuerpo(['payload' => ['entrega_id' => 'xyz']])] as $malo) {
            $this->assertSame(ManejadorRevisiones::REJECT, $this->manejador->manejar($malo));
        }
        $this->assertSame(0, Revision::count());
    }

    public function test_entrega_inexistente_marca_fallida_y_hace_nack(): void
    {
        $cuerpo = $this->cuerpo(['payload' => ['entrega_id' => '99999999-9999-4999-8999-999999999999']]);

        $this->assertSame(ManejadorRevisiones::NACK, $this->manejador->manejar($cuerpo));
        $rev = Revision::first();
        $this->assertSame('fallida', $rev->estado);
        $this->assertSame('La entrega no existe.', $rev->error);
    }

    public function test_rubrica_es_determinista_y_explica_lo_que_falta(): void
    {
        $r = new RevisorPedidos(0);

        $corto = $r->revisar('Hazme algo bonito para mis alumnos.');
        $this->assertSame($corto, $r->revisar('Hazme algo bonito para mis alumnos.'));
        $this->assertSame(0, $corto['puntaje']);
        $this->assertStringContainsString('nivel', $corto['retroalimentacion']);

        $completo = $r->revisar('Actua como tutor de matematica y redacta una lista de tres ejercicios de fracciones para estudiantes de quinto grado, con pasos de solucion.');
        $this->assertSame(100, $completo['puntaje']);
    }
}
