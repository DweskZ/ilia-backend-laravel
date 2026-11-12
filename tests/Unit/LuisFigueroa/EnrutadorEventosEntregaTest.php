<?php

namespace Tests\Unit\LuisFigueroa;

use App\Services\LuisFigueroa\EnrutadorEventosEntrega;
use App\Services\LuisFigueroa\FiltroEventosEntrega;
use PHPUnit\Framework\TestCase;

class EnrutadorEventosEntregaTest extends TestCase
{
    public function test_reglas_explicitas_tipo_a_destino(): void
    {
        $r = new EnrutadorEventosEntrega;

        $this->assertSame('ilia.entregas.revision', $r->enrutar(['tipo' => 'entrega.recibida'])['cola']);
        $this->assertSame('ilia.entregas.notificaciones', $r->enrutar(['tipo' => 'entrega.calificada'])['cola']);
        $this->assertSame('ilia.entregas.intentos', $r->enrutar(['tipo' => 'entrega.reintento_solicitado'])['cola']);
    }

    public function test_tipo_desconocido_o_ausente_es_no_enrutable(): void
    {
        $r = new EnrutadorEventosEntrega;

        foreach ([['tipo' => 'entrega.otra'], ['tipo' => ''], []] as $evento) {
            $ruta = $r->enrutar($evento);
            $this->assertFalse($ruta['reconocido']);
            $this->assertSame('ilia.entregas.no_enrutables', $ruta['cola']);
        }
    }

    public function test_el_filtro_solo_deja_pasar_entregas_activas(): void
    {
        $f = new FiltroEventosEntrega;

        $this->assertNull($f->motivoDeRechazo(['estado' => 'activo']));
        $this->assertStringContainsString("'anulada'", $f->motivoDeRechazo(['estado' => 'anulada']));
        $this->assertNotNull($f->motivoDeRechazo(['estado' => 'borrador']));
        $this->assertNotNull($f->motivoDeRechazo([]));
    }
}
