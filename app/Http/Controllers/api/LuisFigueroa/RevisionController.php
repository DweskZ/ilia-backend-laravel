<?php

namespace App\Http\Controllers\api\LuisFigueroa;

use App\Http\Controllers\Controller;
use App\Http\Requests\LuisFigueroa\SolicitarRevisionRequest;
use App\Models\Revision;
use App\Models\Submission;
use App\Services\LuisFigueroa\Mensajeria\BrokerNoDisponibleException;
use App\Services\LuisFigueroa\Mensajeria\PublicadorMensajes;
use App\Services\LuisFigueroa\ProcesadorRevisiones;
use App\Support\LuisFigueroa\LogRevisiones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class RevisionController extends Controller
{
    public const TIPO = 'entrega.revision_solicitada';

    public function __construct(
        private readonly PublicadorMensajes $publicador,
        private readonly ProcesadorRevisiones $procesador,
    ) {}

    // POST /api/revisiones  -> 202: the review is queued, NOT finished.
    public function store(SolicitarRevisionRequest $request): JsonResponse
    {
        $entrega = Submission::findOrFail($request->validated('entrega_id'));
        $correlationId = $this->correlationId($request);
        $cola = config('luisfigueroa.revision.cola');

        $mensaje = [
            'message_id' => (string) Str::uuid(),
            'correlation_id' => $correlationId,
            'tipo' => self::TIPO,
            'ocurrido_en' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'payload' => ['entrega_id' => $entrega->id, 'intento' => $entrega->attempt],
        ];

        // The row exists before publishing, so the consumer and GET always find it.
        $revision = $this->crearRevision($mensaje, $request, 'asincrona');

        LogRevisiones::canal()->info('producer: publicando mensaje', [
            'correlation_id' => $correlationId, 'message_id' => $mensaje['message_id'],
            'entrega_id' => $entrega->id, 'cola' => $cola,
        ]);

        try {
            $this->publicador->asegurarCola($cola);
            $this->publicador->publicar('', $cola, $mensaje);
        } catch (BrokerNoDisponibleException $e) {
            $revision->delete(); // nothing was queued: do not leave a "pendiente" that will never run
            LogRevisiones::canal()->error('producer: broker no disponible', ['correlation_id' => $correlationId, 'error' => $e->getMessage()]);

            return response()->json(['mensaje' => 'El servicio de mensajería no está disponible. Intente más tarde.'], 503, [], JSON_UNESCAPED_UNICODE);
        }

        LogRevisiones::canal()->info('producer: mensaje publicado y aceptado por el broker', [
            'correlation_id' => $correlationId, 'message_id' => $mensaje['message_id'],
            'entrega_id' => $entrega->id, 'cola' => $cola,
        ]);

        return response()->json([
            'mensaje' => 'Revisión en cola',
            'message_id' => $mensaje['message_id'],
            'correlation_id' => $correlationId,
            'estado' => Revision::PENDIENTE,
        ], 202, ['X-Correlation-ID' => $correlationId, 'Location' => "/api/revisiones/{$correlationId}"], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/revisiones/sincronas  -> 200: same review, run inline (for timing comparison).
    public function sincrona(SolicitarRevisionRequest $request): JsonResponse
    {
        $entrega = Submission::findOrFail($request->validated('entrega_id'));
        $correlationId = $this->correlationId($request);

        $revision = $this->crearRevision([
            'message_id' => (string) Str::uuid(),
            'correlation_id' => $correlationId,
            'payload' => ['entrega_id' => $entrega->id, 'intento' => $entrega->attempt],
        ], $request, 'sincrona');

        LogRevisiones::canal()->info('sync: revisión solicitada', ['correlation_id' => $correlationId, 'entrega_id' => $entrega->id]);

        try {
            $this->procesador->ejecutar($revision);
        } catch (Throwable) {
            return response()->json(['mensaje' => 'La revisión falló.', 'correlation_id' => $correlationId, 'estado' => Revision::FALLIDA], 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'mensaje' => 'Revisión completada',
            'correlation_id' => $correlationId,
            'message_id' => $revision->message_id,
            'estado' => $revision->estado,
            'puntaje' => $revision->puntaje,
            'retroalimentacion' => $revision->retroalimentacion,
        ], 200, ['X-Correlation-ID' => $correlationId], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/revisiones/{correlation_id}
    public function show(Request $request, string $correlation_id): JsonResponse
    {
        $revision = Revision::where('correlation_id', $correlation_id)->orderByDesc('id')->first();

        if (! $revision) {
            return response()->json(['mensaje' => 'Revisión no encontrada'], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $usuario = $request->user();
        if (! $usuario->esAdmin() && $revision->solicitante_id !== $usuario->id) {
            abort(403);
        }

        return response()->json(['data' => [
            'correlation_id' => $revision->correlation_id,
            'message_id' => $revision->message_id,
            'entrega_id' => $revision->entrega_id,
            'intento' => $revision->intento,
            'origen' => $revision->origen,
            'estado' => $revision->estado,
            'puntaje' => $revision->puntaje,
            'retroalimentacion' => $revision->retroalimentacion,
            'error' => $revision->error,
            'solicitada_en' => $revision->solicitada_en?->toIso8601String(),
            'finalizada_en' => $revision->finalizada_en?->toIso8601String(),
        ]], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Accepts a well-formed X-Correlation-ID from the client; otherwise generates one. */
    private function correlationId(Request $request): string
    {
        $recibido = $request->header('X-Correlation-ID');

        // Strict charset/length: the value goes into logs and a queue, so no free text.
        if (is_string($recibido) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $recibido)) {
            return $recibido;
        }

        return (string) Str::uuid();
    }

    private function crearRevision(array $mensaje, Request $request, string $origen): Revision
    {
        return Revision::create([
            'message_id' => $mensaje['message_id'],
            'correlation_id' => $mensaje['correlation_id'],
            'entrega_id' => $mensaje['payload']['entrega_id'],
            'intento' => $mensaje['payload']['intento'],
            'solicitante_id' => $request->user()->id,
            'origen' => $origen,
            'estado' => Revision::PENDIENTE,
        ]);
    }
}
