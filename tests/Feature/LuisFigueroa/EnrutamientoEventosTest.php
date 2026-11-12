<?php

namespace Tests\Feature\LuisFigueroa;

use App\Models\AppUser;
use App\Models\EventoRuta;
use App\Services\LuisFigueroa\Mensajeria\PublicadorMensajes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PublicadorFalso;
use Tests\TestCase;

class EnrutamientoEventosTest extends TestCase
{
    use RefreshDatabase;

    private PublicadorFalso $publicador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publicador = new PublicadorFalso;
        $this->app->instance(PublicadorMensajes::class, $this->publicador);
        Sanctum::actingAs(AppUser::factory()->admin()->create());
    }

    private function evento(array $o = []): array
    {
        return array_replace_recursive([
            'id' => (string) Str::uuid(),
            'tipo' => 'entrega.recibida',
            'prioridad' => 'media',
            'estado' => 'activo',
            'datos' => ['entrega_id' => '66666666-6666-4666-8666-666666666666'],
        ], $o);
    }

    /** @return array<string, array{string, string, string}> */
    public static function rutas(): array
    {
        return [
            'P1 recibida → revisión' => ['entrega.recibida', 'revision', 'ilia.entregas.revision'],
            'P2 calificada → notificaciones' => ['entrega.calificada', 'notificaciones', 'ilia.entregas.notificaciones'],
            'P3 reintento → intentos' => ['entrega.reintento_solicitado', 'intentos', 'ilia.entregas.intentos'],
        ];
    }

    #[DataProvider('rutas')]
    public function test_p1_p3_cada_tipo_va_a_su_destino(string $tipo, string $clave, string $cola): void
    {
        $evento = $this->evento(['tipo' => $tipo]);

        $this->postJson('/api/eventos/entregas', $evento)
            ->assertStatus(202)
            ->assertJsonPath('decision', 'enrutado')
            ->assertJsonPath('destino', $cola)
            ->assertJsonPath('routing_key', $clave);

        $this->assertCount(1, $this->publicador->publicados);
        $pub = $this->publicador->publicados[0];
        $this->assertSame('ilia.entregas', $pub['exchange']);
        $this->assertSame($clave, $pub['routing_key']);
        $this->assertSame($tipo, $pub['mensaje']['tipo']);
        $this->assertNotEmpty($pub['mensaje']['correlation_id']);
        $this->assertDatabaseHas('evento_ruta', ['evento_id' => $evento['id'], 'decision' => 'enrutado', 'destino' => $cola, 'consumido_por' => null]);
    }

    public function test_p4_tipo_desconocido_va_a_no_enrutables(): void
    {
        $this->postJson('/api/eventos/entregas', $this->evento(['tipo' => 'entrega.algo_raro']))
            ->assertStatus(202)
            ->assertJsonPath('decision', 'no_enrutable')
            ->assertJsonPath('destino', 'ilia.entregas.no_enrutables')
            ->assertJsonPath('routing_key', 'no_enrutable');

        $this->assertSame('no_enrutable', $this->publicador->publicados[0]['routing_key']);
        $this->assertDatabaseHas('evento_ruta', ['decision' => 'no_enrutable', 'tipo' => 'entrega.algo_raro']);
    }

    public function test_p5_evento_de_entrega_anulada_o_borrador_se_filtra_y_no_se_publica(): void
    {
        foreach (['anulada', 'borrador'] as $estado) {
            $this->postJson('/api/eventos/entregas', $this->evento(['estado' => $estado]))
                ->assertStatus(200)
                ->assertJsonPath('decision', 'filtrado')
                ->assertJsonStructure(['motivo', 'evento_id', 'correlation_id'])
                ->assertJsonMissingPath('destino');
        }

        $this->assertSame([], $this->publicador->publicados); // never reaches the broker
        $this->assertSame(2, EventoRuta::where('decision', 'filtrado')->count());
        $this->assertStringContainsString('anulada', EventoRuta::where('estado_entrega', 'anulada')->value('motivo'));
    }

    public function test_p6_campo_obligatorio_faltante_devuelve_422(): void
    {
        foreach (['id', 'tipo', 'prioridad', 'estado', 'datos'] as $campo) {
            $evento = $this->evento();
            unset($evento[$campo]);

            $this->postJson('/api/eventos/entregas', $evento)
                ->assertStatus(422)
                ->assertJsonStructure(['mensaje', 'errores' => [$campo]]);
        }
        $this->postJson('/api/eventos/entregas', $this->evento(['prioridad' => 'urgente']))->assertStatus(422);
        $this->postJson('/api/eventos/entregas', $this->evento(['datos' => ['entrega_id' => 'x']]))->assertStatus(422);

        $this->assertSame([], $this->publicador->publicados);
        $this->assertSame(0, EventoRuta::count());
    }

    public function test_prioridad_alta_agrega_prioridad_al_mensaje_amqp(): void
    {
        $this->postJson('/api/eventos/entregas', $this->evento(['prioridad' => 'alta']))->assertStatus(202)->assertJsonPath('prioridad_amqp', 9);
        $this->postJson('/api/eventos/entregas', $this->evento(['prioridad' => 'baja']))->assertStatus(202);

        $this->assertSame(9, $this->publicador->publicados[0]['prioridad']);
        $this->assertSame(1, $this->publicador->publicados[1]['prioridad']);
    }

    public function test_correlation_id_del_cuerpo_se_conserva_y_si_falta_se_genera(): void
    {
        $this->postJson('/api/eventos/entregas', $this->evento(['correlation_id' => 'corr-evento-001']))
            ->assertStatus(202)->assertHeader('X-Correlation-ID', 'corr-evento-001');
        $r = $this->postJson('/api/eventos/entregas', $this->evento())->assertStatus(202);

        $this->assertSame('corr-evento-001', $this->publicador->publicados[0]['mensaje']['correlation_id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $r->json('correlation_id'));
    }

    public function test_id_duplicado_devuelve_409_y_no_republica(): void
    {
        $evento = $this->evento();
        $this->postJson('/api/eventos/entregas', $evento)->assertStatus(202);
        $this->postJson('/api/eventos/entregas', $evento)->assertStatus(409);

        $this->assertCount(1, $this->publicador->publicados);
    }

    public function test_broker_caido_devuelve_503_sin_dejar_ruta_registrada(): void
    {
        $this->publicador->caido = true;

        $this->postJson('/api/eventos/entregas', $this->evento())->assertStatus(503);
        $this->assertSame(0, EventoRuta::count());
    }

    public function test_solo_admin_puede_publicar_y_se_exige_token(): void
    {
        Sanctum::actingAs(AppUser::factory()->create()); // docente
        $this->postJson('/api/eventos/entregas', $this->evento())->assertStatus(403);
        $this->assertSame([], $this->publicador->publicados);

        $this->app['auth']->forgetGuards();
        $this->refreshApplication();
        $this->postJson('/api/eventos/entregas', $this->evento())->assertStatus(401);
    }
}
