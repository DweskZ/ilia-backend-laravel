<?php

namespace App\Services\LuisFigueroa;

use App\Models\Revision;
use App\Models\Submission;
use App\Support\LuisFigueroa\LogRevisiones;
use RuntimeException;
use Throwable;

/**
 * Runs one review and records its life cycle: procesando -> completada | fallida.
 * Shared by the consumer (async) and by POST /api/revisiones/sincronas (sync).
 */
class ProcesadorRevisiones
{
    public function __construct(private readonly RevisorPedidos $revisor) {}

    public function ejecutar(Revision $revision): Revision
    {
        $rol = $revision->origen === 'asincrona' ? 'consumer' : 'sync';

        $revision->update(['estado' => Revision::PROCESANDO, 'iniciada_en' => now()]);
        LogRevisiones::canal()->info("{$rol}: procesando revisión", $this->ctx($revision));

        try {
            $entrega = Submission::find($revision->entrega_id)
                ?? throw new RuntimeException('La entrega no existe.');

            $resultado = $this->revisor->revisar($entrega->prompt);

            $revision->update([
                'estado' => Revision::COMPLETADA,
                'puntaje' => $resultado['puntaje'],
                'retroalimentacion' => $resultado['retroalimentacion'],
                'finalizada_en' => now(),
            ]);
            LogRevisiones::canal()->info("{$rol}: revisión completada", $this->ctx($revision) + ['puntaje' => $resultado['puntaje']]);
        } catch (Throwable $e) {
            $revision->update(['estado' => Revision::FALLIDA, 'error' => $e->getMessage(), 'finalizada_en' => now()]);
            LogRevisiones::canal()->error("{$rol}: revisión fallida", $this->ctx($revision) + ['error' => $e->getMessage()]);

            throw $e;
        }

        return $revision;
    }

    /** @return array<string, mixed> */
    private function ctx(Revision $r): array
    {
        return ['correlation_id' => $r->correlation_id, 'message_id' => $r->message_id, 'entrega_id' => $r->entrega_id];
    }
}
