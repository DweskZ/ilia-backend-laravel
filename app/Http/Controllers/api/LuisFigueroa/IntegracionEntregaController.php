<?php

namespace App\Http\Controllers\api\LuisFigueroa;

use App\Http\Controllers\Controller;
use App\Services\LuisFigueroa\NormalizadorEntregas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin adapters: they only hand the raw body to the translator and wrap the
 * result. All mapping and validation rules live in NormalizadorEntregas.
 */
class IntegracionEntregaController extends Controller
{
    public function __construct(private readonly NormalizadorEntregas $normalizador) {}

    // POST /api/integracion/entregas/web
    public function web(Request $request): JsonResponse
    {
        return $this->respuesta('formulario_web', $this->normalizador->desdeFormularioWeb($request->json()->all()));
    }

    // POST /api/integracion/entregas/movil
    public function movil(Request $request): JsonResponse
    {
        return $this->respuesta('app_movil', $this->normalizador->desdeAppMovil($request->json()->all()));
    }

    private function respuesta(string $origen, array $contrato): JsonResponse
    {
        return response()->json([
            'mensaje' => 'Entrega normalizada',
            'origen' => $origen,
            'data' => $contrato,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
