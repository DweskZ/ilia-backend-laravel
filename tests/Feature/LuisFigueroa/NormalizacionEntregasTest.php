<?php

namespace Tests\Feature\LuisFigueroa;

use Tests\TestCase;

class NormalizacionEntregasTest extends TestCase
{
    private const PEDIDO = 'Actua como tutor y explica las fracciones a estudiantes de quinto grado.';

    private function web(array $o = []): array
    {
        return array_merge([
            'idActividad' => '44444444-4444-4444-8444-444444444444',
            'idDocente' => '22222222-2222-4222-8222-222222222222',
            'textoPedido' => self::PEDIDO,
            'numeroIntento' => '2',
            'fechaEnvio' => '10/11/2026 14:30',
            'comentario' => '',
        ], $o);
    }

    private function movil(array $sub = []): array
    {
        return [
            'submission' => array_merge([
                'activity_uuid' => '44444444-4444-4444-8444-444444444444',
                'teacher_uuid' => '22222222-2222-4222-8222-222222222222',
                'prompt_text' => self::PEDIDO,
                'attempt' => 2,
                'sent_at_ms' => 1794339000000,
            ], $sub),
            'note' => null,
        ];
    }

    public function test_p1_formulario_web_valido_devuelve_contrato(): void
    {
        $this->postJson('/api/integracion/entregas/web', $this->web())
            ->assertOk()
            ->assertJsonPath('origen', 'formulario_web')
            ->assertJsonPath('data.intento', 2)
            ->assertJsonPath('data.enviado_en', '2026-11-10T19:30:00+00:00')
            ->assertJsonPath('data.observacion', null)
            ->assertJsonStructure(['data' => ['actividad_id', 'docente_id', 'pedido', 'intento', 'enviado_en', 'observacion']]);
    }

    public function test_p2_app_movil_equivalente_devuelve_el_mismo_contrato(): void
    {
        $a = $this->postJson('/api/integracion/entregas/web', $this->web())->json('data');
        $b = $this->postJson('/api/integracion/entregas/movil', $this->movil())
            ->assertOk()
            ->assertJsonPath('origen', 'app_movil')
            ->json('data');

        $this->assertSame($a, $b);
    }

    public function test_p3_web_con_campo_faltante_y_valores_invalidos(): void
    {
        $payload = $this->web(['numeroIntento' => 'dos', 'fechaEnvio' => '31/02/2026 10:00']);
        unset($payload['textoPedido']);

        $this->postJson('/api/integracion/entregas/web', $payload)
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Los datos enviados no cumplen el contrato.')
            ->assertJsonStructure(['errores' => ['textoPedido', 'numeroIntento', 'fechaEnvio']]);
    }

    public function test_p3_movil_con_objeto_anidado_incompleto(): void
    {
        $this->postJson('/api/integracion/entregas/movil', ['note' => null])
            ->assertStatus(422)
            ->assertJsonStructure(['mensaje', 'errores' => ['submission']]);

        $this->postJson('/api/integracion/entregas/movil', $this->movil(['attempt' => '2', 'sent_at_ms' => 1794339000]))
            ->assertStatus(422)
            ->assertJsonStructure(['errores' => ['submission.attempt', 'submission.sent_at_ms']]);
    }
}
