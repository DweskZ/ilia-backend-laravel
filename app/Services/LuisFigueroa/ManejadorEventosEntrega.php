<?php

namespace App\Services\LuisFigueroa;

use App\Models\EventoRuta;
use App\Support\LuisFigueroa\LogEventos;
use Illuminate\Support\Facades\Validator;

/**
 * Consumer-side logic (independent of RabbitMQ). One handler for the three
 * consumers; `$destino` says which one is running (revision | notificaciones | intentos).
 *
 * It verifies the message really belongs to this queue (tipo vs destino), runs the
 * business action (here: a log line, since the actions are outside the scope of
 * the practice), records `consumido_por` in evento_ruta and answers ack / reject.
 * Unreadable or misplaced messages are recorded as `invalido` and rejected
 * without requeue (they can never become valid).
 */
class ManejadorEventosEntrega
{
    public const ACK = 'ack';

    public const REJECT = 'reject';

    private const ACCIONES = [
        'revision' => 'solicitar la revisión automática del pedido',
        'notificaciones' => 'avisar al docente de la calificación',
        'intentos' => 'habilitar un nuevo intento al docente',
    ];

    public function manejar(string $cuerpo, string $destino): string
    {
        $regla = EnrutadorEventosEntrega::reglaDeDestino($destino);
        $datos = json_decode($cuerpo, true);

        $validador = Validator::make(is_array($datos) ? $datos : [], [
            'id' => ['required', 'uuid'],
            'correlation_id' => ['required', 'string', 'max:64'],
            'tipo' => ['required', 'string'],
            'prioridad' => ['required', 'in:alta,media,baja'],
            'estado' => ['required', 'string'],
            'datos.entrega_id' => ['required', 'uuid'],
        ]);

        $problema = match (true) {
            $regla === null => "Destino de consumidor desconocido: '{$destino}'.",
            ! is_array($datos) || $validador->fails() => 'Mensaje malformado: '.(is_array($datos) ? implode(', ', array_keys($validador->errors()->toArray())) : 'JSON ilegible').'.',
            $datos['tipo'] !== $regla['tipo'] => "El tipo '{$datos['tipo']}' no corresponde a la cola de '{$destino}' (se esperaba '{$regla['tipo']}').",
            default => null,
        };

        if ($problema !== null) {
            $this->registrarInvalido($datos, $destino, $problema);
            LogEventos::canal()->error("consumidor[{$destino}]: mensaje inválido, rechazado", [
                'correlation_id' => is_array($datos) && is_string($datos['correlation_id'] ?? null) ? mb_substr($datos['correlation_id'], 0, 64) : null,
                'motivo' => $problema,
            ]);

            return self::REJECT;
        }

        $fila = EventoRuta::where('evento_id', $datos['id'])->first();
        if ($fila) {
            $fila->update(['consumido_por' => $destino, 'consumido_en' => now()]);
        }

        LogEventos::canal()->info("consumidor[{$destino}]: evento consumido, acción: ".self::ACCIONES[$destino], [
            'correlation_id' => $datos['correlation_id'], 'evento_id' => $datos['id'], 'tipo' => $datos['tipo'],
            'entrega_id' => $datos['datos']['entrega_id'],
        ]);

        return self::ACK;
    }

    private function registrarInvalido(mixed $datos, string $destino, string $motivo): void
    {
        $d = is_array($datos) ? $datos : [];
        $idValido = is_string($d['id'] ?? null) && Validator::make($d, ['id' => 'uuid'])->passes();

        // If the event is already logged (e.g. misplaced), keep its row and mark it; otherwise create one.
        $fila = $idValido ? EventoRuta::where('evento_id', $d['id'])->first() : null;
        if ($fila) {
            $fila->update(['decision' => EventoRuta::INVALIDO, 'motivo' => $motivo, 'consumido_por' => $destino, 'consumido_en' => now()]);

            return;
        }

        EventoRuta::create([
            'evento_id' => $idValido ? $d['id'] : null,
            'correlation_id' => is_string($d['correlation_id'] ?? null) ? mb_substr($d['correlation_id'], 0, 64) : null,
            'tipo' => is_string($d['tipo'] ?? null) ? mb_substr($d['tipo'], 0, 100) : null,
            'decision' => EventoRuta::INVALIDO,
            'destino' => EnrutadorEventosEntrega::reglaDeDestino($destino)['cola'] ?? null,
            'motivo' => mb_substr($motivo, 0, 500),
            'consumido_por' => $destino,
            'consumido_en' => now(),
        ]);
    }
}
