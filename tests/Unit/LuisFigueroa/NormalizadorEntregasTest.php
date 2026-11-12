<?php

namespace Tests\Unit\LuisFigueroa;

use App\Exceptions\LuisFigueroa\EntregaInvalidaException;
use App\Services\LuisFigueroa\NormalizadorEntregas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizadorEntregasTest extends TestCase
{
    private const ACT = '44444444-4444-4444-8444-444444444444';

    private const DOC = '22222222-2222-4222-8222-222222222222';

    private const PEDIDO = 'Actua como tutor y explica las fracciones a estudiantes de quinto grado.';

    private function web(array $o = []): array
    {
        return array_merge([
            'idActividad' => self::ACT, 'idDocente' => self::DOC, 'textoPedido' => self::PEDIDO,
            'numeroIntento' => '2', 'fechaEnvio' => '10/11/2026 14:30', 'comentario' => '',
        ], $o);
    }

    private function movil(array $o = [], array $sub = []): array
    {
        return array_merge([
            'submission' => array_merge([
                'activity_uuid' => self::ACT, 'teacher_uuid' => self::DOC, 'prompt_text' => self::PEDIDO,
                'attempt' => 2, 'sent_at_ms' => 1794339000000,
            ], $sub),
            'note' => null,
        ], $o);
    }

    private function errores(callable $fn): array
    {
        try {
            $fn();
        } catch (EntregaInvalidaException $e) {
            return $e->errores;
        }
        $this->fail('Se esperaba EntregaInvalidaException');
    }

    public function test_origenes_equivalentes_producen_el_mismo_arreglo(): void
    {
        $n = new NormalizadorEntregas;

        $a = $n->desdeFormularioWeb($this->web());
        $b = $n->desdeAppMovil($this->movil());

        $this->assertSame($a, $b);
        $this->assertSame([
            'actividad_id' => self::ACT,
            'docente_id' => self::DOC,
            'pedido' => self::PEDIDO,
            'intento' => 2,
            'enviado_en' => '2026-11-10T19:30:00+00:00',
            'observacion' => null,
        ], $a);
    }

    public function test_comentario_con_texto_se_conserva_en_ambos_origenes(): void
    {
        $n = new NormalizadorEntregas;

        $this->assertSame('Primera versión', $n->desdeFormularioWeb($this->web(['comentario' => ' Primera versión ']))['observacion']);
        $this->assertSame('Primera versión', $n->desdeAppMovil($this->movil(['note' => 'Primera versión']))['observacion']);
    }

    #[DataProvider('intentosInvalidos')]
    public function test_web_rechaza_intentos_ambiguos(mixed $valor): void
    {
        $e = $this->errores(fn () => (new NormalizadorEntregas)->desdeFormularioWeb($this->web(['numeroIntento' => $valor])));
        $this->assertArrayHasKey('numeroIntento', $e);
    }

    public static function intentosInvalidos(): array
    {
        return [['dos'], ['2.5'], ['2,5'], ['-1'], ['0'], ['4'], [' '], [2.5], [true]];
    }

    public function test_web_rechaza_fechas_invalidas(): void
    {
        foreach (['2026-11-10 14:30', '10/11/2026', '31/02/2026 10:00', '10/11/2026 25:00', 'ayer'] as $f) {
            $e = $this->errores(fn () => (new NormalizadorEntregas)->desdeFormularioWeb($this->web(['fechaEnvio' => $f])));
            $this->assertArrayHasKey('fechaEnvio', $e, $f);
        }
    }

    public function test_movil_rechaza_tipos_y_rangos_invalidos(): void
    {
        $n = new NormalizadorEntregas;
        $casos = [
            'submission.attempt' => ['attempt' => '2'],
            'submission.sent_at_ms' => ['sent_at_ms' => 1794339000],
            'submission.activity_uuid' => ['activity_uuid' => 'no-es-uuid'],
        ];
        foreach ($casos as $campo => $sub) {
            $e = $this->errores(fn () => $n->desdeAppMovil($this->movil([], $sub)));
            $this->assertArrayHasKey($campo, $e);
        }
    }

    public function test_campos_faltantes_se_reportan_todos(): void
    {
        $e = $this->errores(fn () => (new NormalizadorEntregas)->desdeFormularioWeb([]));
        $this->assertEqualsCanonicalizing(
            ['idActividad', 'idDocente', 'textoPedido', 'numeroIntento', 'fechaEnvio'],
            array_keys($e)
        );

        $e = $this->errores(fn () => (new NormalizadorEntregas)->desdeAppMovil(['note' => null]));
        $this->assertArrayHasKey('submission', $e);
    }
}
