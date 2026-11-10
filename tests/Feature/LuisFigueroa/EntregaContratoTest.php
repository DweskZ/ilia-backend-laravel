<?php

namespace Tests\Feature\LuisFigueroa;

use App\Models\Activity;
use App\Models\AppUser;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EntregaContratoTest extends TestCase
{
    use RefreshDatabase;

    private Activity $actividad;

    private AppUser $docente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actividad = Activity::factory()->create();
        $this->docente = AppUser::factory()->create();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'actividad_id' => $this->actividad->id,
            'docente_id' => $this->docente->id,
            'pedido' => 'Actua como tutor y explica las fracciones a estudiantes de quinto grado.',
            'intento' => 1,
            'enviado_en' => '2026-11-10T14:30:00-05:00',
            'observacion' => null,
        ], $override);
    }

    private function assertRechazo(array $payload, string $campo): void
    {
        $this->postJson('/api/entregas', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['mensaje', 'errores' => [$campo]])
            ->assertJsonPath('mensaje', 'Los datos enviados no cumplen el contrato.');
        $this->assertSame(0, Submission::count());
    }

    public function test_p1_json_valido_devuelve_201(): void
    {
        $payload = $this->payload(['observacion' => 'Primera version']);

        $this->postJson('/api/entregas', $payload)
            ->assertStatus(201)
            ->assertJsonStructure(['data' => [
                'entrega_id', 'actividad_id', 'docente_id', 'intento', 'estado',
                'pedido_caracteres', 'observacion', 'enviado_en', 'registrado_en',
            ], 'mensaje'])
            ->assertJsonPath('data.estado', 'recibida')
            ->assertJsonPath('data.pedido_caracteres', mb_strlen($payload['pedido']))
            ->assertJsonPath('data.intento', 1)
            ->assertJsonPath('mensaje', 'Entrega recibida');

        $this->assertSame(1, Submission::count());
        // The offset is normalized to UTC without changing the instant.
        $this->assertTrue(
            Carbon::parse($payload['enviado_en'])->equalTo(Submission::first()->sent_at)
        );
    }

    public function test_p2_falta_campo_obligatorio_devuelve_422(): void
    {
        $payload = $this->payload();
        unset($payload['pedido']);

        $this->assertRechazo($payload, 'pedido');
    }

    public function test_p3a_intento_texto_no_numerico(): void
    {
        $this->assertRechazo($this->payload(['intento' => 'dos']), 'intento');
    }

    public function test_p3b_intento_numero_como_texto(): void
    {
        $this->assertRechazo($this->payload(['intento' => '2']), 'intento');
    }

    public function test_p3c_fecha_con_formato_invalido(): void
    {
        $this->assertRechazo($this->payload(['enviado_en' => '10/11/2026']), 'enviado_en');
    }

    public function test_intento_duplicado_devuelve_422(): void
    {
        $this->postJson('/api/entregas', $this->payload())->assertStatus(201);

        $this->postJson('/api/entregas', $this->payload())
            ->assertStatus(422)
            ->assertJsonStructure(['errores' => ['intento']]);
        $this->assertSame(1, Submission::count());
    }

    public function test_intento_sobre_el_maximo_de_la_actividad(): void
    {
        $this->actividad->update(['max_attempts' => 2]);

        $this->assertRechazo($this->payload(['intento' => 3]), 'intento');
    }

    public function test_actividad_inexistente_devuelve_422(): void
    {
        $this->assertRechazo(
            $this->payload(['actividad_id' => '99999999-9999-4999-8999-999999999999']),
            'actividad_id'
        );
    }

    public function test_422_sin_cabecera_accept_json(): void
    {
        $this->post('/api/entregas', ['intento' => 'dos'])
            ->assertStatus(422)
            ->assertJsonStructure(['mensaje', 'errores']);
    }

    public function test_get_devuelve_200_con_el_mismo_contrato(): void
    {
        $id = $this->postJson('/api/entregas', $this->payload())->json('data.entrega_id');

        $this->getJson("/api/entregas/{$id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'entrega_id', 'actividad_id', 'docente_id', 'intento', 'estado',
                'pedido_caracteres', 'observacion', 'enviado_en', 'registrado_en',
            ], 'mensaje'])
            ->assertJsonPath('data.entrega_id', $id);
    }

    public function test_get_de_uuid_desconocido_devuelve_404(): void
    {
        $this->getJson('/api/entregas/99999999-9999-4999-8999-999999999999')
            ->assertStatus(404)
            ->assertExactJson(['mensaje' => 'Entrega no encontrada']);
    }
}
