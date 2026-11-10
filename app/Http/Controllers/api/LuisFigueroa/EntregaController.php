<?php

namespace App\Http\Controllers\api\LuisFigueroa;

use App\Http\Controllers\Controller;
use App\Http\Requests\LuisFigueroa\StoreEntregaRequest;
use App\Http\Resources\LuisFigueroa\EntregaResource;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class EntregaController extends Controller
{
    // POST /api/entregas
    public function store(StoreEntregaRequest $request): JsonResponse
    {
        // Deserialization: validated JSON body -> PHP array (Spanish contract names).
        $datos = $request->validated();

        // Contract (Spanish) -> database columns (English).
        $entrega = Submission::create([
            'activity_id' => $datos['actividad_id'],
            'user_id' => $datos['docente_id'],
            'prompt' => $datos['pedido'],
            'attempt' => $datos['intento'],
            'observation' => $datos['observacion'] ?? null,
            'status' => 'recibida',
            'sent_at' => Carbon::parse($datos['enviado_en'])->utc(),
        ])->refresh();

        // Serialization: model -> EntregaResource -> JSON.
        return (new EntregaResource($entrega))->response()->setStatusCode(201);
    }

    // GET /api/entregas/{id}
    public function show(string $id): JsonResponse
    {
        $entrega = Submission::find($id);

        if (! $entrega) {
            return response()->json(['mensaje' => 'Entrega no encontrada'], 404);
        }

        return (new EntregaResource($entrega))->response();
    }
}
