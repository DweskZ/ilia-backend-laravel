<?php

namespace App\Services\LuisFigueroa;

use App\Models\Revision;
use App\Support\LuisFigueroa\LogRevisiones;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Consumer-side logic, independent of RabbitMQ: it receives the raw body of a
 * message and returns what the consumer must tell the broker.
 *
 *  ACK    processed (or duplicate already processed) -> remove from the queue
 *  REJECT malformed message                           -> reject, no requeue
 *  NACK   processing error                            -> nack, no requeue
 *
 * Decision (no requeue): a malformed message can never become valid, and a
 * processing error would be redelivered forever (poison message) while blocking
 * the queue with prefetch 1. The failure is not lost: the row stays `fallida`
 * with its error and the log has the correlation_id. A production setup would
 * add a dead-letter exchange so these messages can be inspected and replayed.
 */
class ManejadorRevisiones
{
    public const ACK = 'ack';

    public const REJECT = 'reject';

    public const NACK = 'nack';

    public function __construct(private readonly ProcesadorRevisiones $procesador) {}

    public function manejar(string $cuerpo): string
    {
        $datos = json_decode($cuerpo, true);

        $validador = Validator::make(is_array($datos) ? $datos : [], [
            'message_id' => ['required', 'uuid'],
            'correlation_id' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{8,64}$/'],
            'tipo' => ['required', 'in:entrega.revision_solicitada'],
            'ocurrido_en' => ['required', 'date'],
            'payload' => ['required', 'array'],
            'payload.entrega_id' => ['required', 'uuid'],
            'payload.intento' => ['nullable', 'integer'],
        ]);

        if (! is_array($datos) || $validador->fails()) {
            LogRevisiones::canal()->error('consumer: mensaje malformado, rechazado', [
                'correlation_id' => is_array($datos) && is_string($datos['correlation_id'] ?? null) ? mb_substr($datos['correlation_id'], 0, 64) : null,
                'errores' => is_array($datos) ? array_keys($validador->errors()->toArray()) : ['json'],
            ]);

            return self::REJECT;
        }

        $revision = Revision::firstOrCreate(
            ['message_id' => $datos['message_id']],
            [
                'correlation_id' => $datos['correlation_id'],
                'entrega_id' => $datos['payload']['entrega_id'],
                'intento' => $datos['payload']['intento'] ?? null,
                'origen' => 'asincrona',
                'estado' => Revision::PENDIENTE,
            ],
        );

        LogRevisiones::canal()->info('consumer: mensaje recibido', [
            'correlation_id' => $revision->correlation_id,
            'message_id' => $revision->message_id,
            'entrega_id' => $revision->entrega_id,
        ]);

        // At-least-once delivery: a redelivered message that already finished is acknowledged, not redone.
        if ($revision->estado === Revision::COMPLETADA) {
            LogRevisiones::canal()->warning('consumer: duplicado ya completado, se confirma sin reprocesar', [
                'correlation_id' => $revision->correlation_id,
                'message_id' => $revision->message_id,
            ]);

            return self::ACK;
        }

        try {
            $this->procesador->ejecutar($revision);

            return self::ACK;
        } catch (Throwable) {
            return self::NACK; // already recorded as `fallida` and logged by the processor
        }
    }
}
