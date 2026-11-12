<?php

namespace App\Http\Controllers\api\LuisFigueroa;

use App\Http\Controllers\Controller;
use App\Http\Requests\LuisFigueroa\StoreEntregaRequest;
use App\Http\Resources\LuisFigueroa\EntregaResource;
use App\Models\Submission;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EntregaController extends Controller
{
    use AuthorizesRequests;

    // GET /api/entregas  (docente: only their own; admin: all)
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Submission::class);

        $usuario = $request->user();
        $consulta = Submission::query()->orderByDesc('sent_at');

        if (! $usuario->esAdmin()) {
            $consulta->where('user_id', $usuario->id);
        }

        return EntregaResource::collection($consulta->limit(100)->get())
            ->additional(['mensaje' => 'Listado de entregas'])
            ->response();
    }

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

        $this->authorize('view', $entrega);

        return (new EntregaResource($entrega))->response();
    }

    // DELETE /api/entregas/{id}  (admin only)
    public function destroy(string $id): JsonResponse
    {
        // Authorize before looking the row up, so a docente learns nothing about it.
        $this->authorize('delete', Submission::class);

        $entrega = Submission::find($id);

        if (! $entrega) {
            return response()->json(['mensaje' => 'Entrega no encontrada'], 404);
        }

        $entrega->delete();

        return response()->json(['mensaje' => 'Entrega eliminada']);
    }
}
