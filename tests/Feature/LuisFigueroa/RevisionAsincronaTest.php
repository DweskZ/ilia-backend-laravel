<?php

namespace Tests\Feature\LuisFigueroa;

use App\Models\Activity;
use App\Models\AppUser;
use App\Models\Revision;
use App\Models\Submission;
use App\Services\LuisFigueroa\Mensajeria\PublicadorMensajes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\PublicadorFalso;
use Tests\TestCase;

class RevisionAsincronaTest extends TestCase
{
    use RefreshDatabase;

    private PublicadorFalso $publicador;

    private AppUser $docente;

    private Submission $entrega;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publicador = new PublicadorFalso;
        $this->app->instance(PublicadorMensajes::class, $this->publicador);

        $this->docente = AppUser::factory()->create();
        $this->entrega = Submission::create([
            'activity_id' => Activity::factory()->create()->id, 'user_id' => $this->docente->id,
            'prompt' => 'Actua como tutor y explica las fracciones a estudiantes de quinto grado.',
            'attempt' => 1, 'status' => 'recibida', 'sent_at' => now(),
        ]);
        Sanctum::actingAs($this->docente);
    }

    public function test_p1_mensaje_valido_responde_202_y_publica_en_la_cola_durable(): void
    {
        $r = $this->postJson('/api/revisiones', ['entrega_id' => $this->entrega->id])
            ->assertStatus(202)
            ->assertJsonPath('mensaje', 'Revisión en cola')
            ->assertJsonPath('estado', 'pendiente')
            ->assertJsonStructure(['message_id', 'correlation_id']);

        $this->assertCount(1, $this->publicador->publicados);
        $pub = $this->publicador->publicados[0];
        $this->assertSame('', $pub['exchange']);
        $this->assertSame('ilia.revisiones', $pub['routing_key']);
        $this->assertContains('ilia.revisiones', $this->publicador->colas);
        $this->assertSame([
            'message_id' => $r->json('message_id'),
            'correlation_id' => $r->json('correlation_id'),
            'tipo' => 'entrega.revision_solicitada',
            'ocurrido_en' => $pub['mensaje']['ocurrido_en'],
            'payload' => ['entrega_id' => $this->entrega->id, 'intento' => 1],
        ], $pub['mensaje']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $pub['mensaje']['ocurrido_en']);

        // The response does not claim the review finished.
        $this->assertDatabaseHas('revision', ['message_id' => $r->json('message_id'), 'estado' => 'pendiente', 'origen' => 'asincrona']);
    }

    public function test_p4_correlation_id_recibido_se_propaga(): void
    {
        $r = $this->withHeader('X-Correlation-ID', 'corr-demo-0001')
            ->postJson('/api/revisiones', ['entrega_id' => $this->entrega->id])
            ->assertStatus(202)
            ->assertHeader('X-Correlation-ID', 'corr-demo-0001')
            ->assertJsonPath('correlation_id', 'corr-demo-0001');

        $this->assertSame('corr-demo-0001', $this->publicador->publicados[0]['mensaje']['correlation_id']);
        $this->getJson('/api/revisiones/corr-demo-0001')->assertOk()->assertJsonPath('data.estado', 'pendiente');
        $this->assertNotEmpty($r->json('message_id'));
    }

    public function test_correlation_id_ausente_o_sospechoso_se_genera_uno_nuevo(): void
    {
        $r = $this->withHeader('X-Correlation-ID', "x\nforjado")
            ->postJson('/api/revisiones', ['entrega_id' => $this->entrega->id])->assertStatus(202);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $r->json('correlation_id'));
    }

    public function test_p3_mensaje_invalido_devuelve_422_y_no_publica(): void
    {
        $this->postJson('/api/revisiones', [])->assertStatus(422)->assertJsonStructure(['mensaje', 'errores' => ['entrega_id']]);
        $this->postJson('/api/revisiones', ['entrega_id' => 'no-es-uuid'])->assertStatus(422);
        $this->postJson('/api/revisiones', ['entrega_id' => '99999999-9999-4999-8999-999999999999'])->assertStatus(422);

        $this->assertSame([], $this->publicador->publicados);
        $this->assertSame(0, Revision::count());
    }

    public function test_sin_token_401_y_entrega_ajena_403(): void
    {
        $ajena = Submission::create([
            'activity_id' => $this->entrega->activity_id, 'user_id' => AppUser::factory()->create()->id,
            'prompt' => 'Otro pedido de otro docente para la misma actividad.', 'attempt' => 1, 'status' => 'recibida', 'sent_at' => now(),
        ]);
        $this->postJson('/api/revisiones', ['entrega_id' => $ajena->id])->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->app->make('auth')->guard('sanctum')->forgetUser();
        $this->refreshApplication();
        $this->postJson('/api/revisiones', ['entrega_id' => $this->entrega->id])->assertStatus(401);
    }

    public function test_broker_caido_devuelve_503_y_no_deja_pendientes(): void
    {
        $this->publicador->caido = true;

        $this->postJson('/api/revisiones', ['entrega_id' => $this->entrega->id])
            ->assertStatus(503)->assertJsonPath('mensaje', 'El servicio de mensajería no está disponible. Intente más tarde.');
        $this->assertSame(0, Revision::count());
    }

    public function test_sincrona_devuelve_200_con_el_resultado(): void
    {
        $this->postJson('/api/revisiones/sincronas', ['entrega_id' => $this->entrega->id])
            ->assertOk()
            ->assertJsonPath('estado', 'completada')
            ->assertJsonPath('puntaje', 60)
            ->assertJsonStructure(['retroalimentacion', 'correlation_id']);

        $this->assertSame([], $this->publicador->publicados);
        $this->assertDatabaseHas('revision', ['origen' => 'sincrona', 'estado' => 'completada']);
    }

    public function test_get_de_correlation_desconocido_404_y_de_otro_usuario_403(): void
    {
        $this->getJson('/api/revisiones/no-existe-123')->assertStatus(404);

        $this->postJson('/api/revisiones', ['entrega_id' => $this->entrega->id], ['X-Correlation-ID' => 'corr-privada-1'])->assertStatus(202);
        Sanctum::actingAs(AppUser::factory()->create());
        $this->getJson('/api/revisiones/corr-privada-1')->assertStatus(403);
        Sanctum::actingAs(AppUser::factory()->admin()->create());
        $this->getJson('/api/revisiones/corr-privada-1')->assertOk();
    }
}
