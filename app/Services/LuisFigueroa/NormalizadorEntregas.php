<?php

namespace App\Services\LuisFigueroa;

use App\Exceptions\LuisFigueroa\EntregaInvalidaException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Translator / normalizer: converts the payload of each client into the common
 * "entrega" contract of tema 2.2 (actividad_id, docente_id, pedido, intento,
 * enviado_en, observacion). It is independent of HTTP and of the database.
 *
 * Strict by design: a value that is ambiguous or malformed is reported, never
 * coerced. Every public method either returns the normalized array or throws
 * EntregaInvalidaException with all the problems found (field => messages).
 */
class NormalizadorEntregas
{
    public const ZONA_WEB = 'America/Guayaquil';

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /** @var array<string, list<string>> */
    private array $errores = [];

    /**
     * Origen A: Expo web form (camelCase, human formats, local Ecuador time).
     *
     * @param  array<string, mixed>  $datos
     * @return array{actividad_id: string, docente_id: string, pedido: string, intento: int, enviado_en: string, observacion: ?string}
     */
    public function desdeFormularioWeb(array $datos): array
    {
        $this->errores = [];

        $actividad = $this->uuid($datos, 'idActividad', 'idActividad');
        $docente = $this->uuid($datos, 'idDocente', 'idDocente');
        $pedido = $this->pedido($datos, 'textoPedido', 'textoPedido');
        $intento = $this->intentoDesdeTexto($datos, 'numeroIntento', 'numeroIntento');
        $enviado = $this->fechaWeb($datos, 'fechaEnvio', 'fechaEnvio');
        $observacion = $this->observacion($datos, 'comentario', 'comentario');

        return $this->resultado($actividad, $docente, $pedido, $intento, $enviado, $observacion);
    }

    /**
     * Origen B: mobile app offline sync (snake_case English, nested, epoch ms UTC).
     *
     * @param  array<string, mixed>  $datos
     * @return array{actividad_id: string, docente_id: string, pedido: string, intento: int, enviado_en: string, observacion: ?string}
     */
    public function desdeAppMovil(array $datos): array
    {
        $this->errores = [];

        $sub = $datos['submission'] ?? null;
        if (! is_array($sub) || array_is_list($sub)) {
            $this->errores['submission'][] = 'El campo submission es obligatorio y debe ser un objeto.';
            $sub = [];
        }

        $actividad = $this->uuid($sub, 'activity_uuid', 'submission.activity_uuid');
        $docente = $this->uuid($sub, 'teacher_uuid', 'submission.teacher_uuid');
        $pedido = $this->pedido($sub, 'prompt_text', 'submission.prompt_text');
        $intento = $this->intentoEntero($sub, 'attempt', 'submission.attempt');
        $enviado = $this->fechaEpochMs($sub, 'sent_at_ms', 'submission.sent_at_ms');
        $observacion = $this->observacion($datos, 'note', 'note');

        return $this->resultado($actividad, $docente, $pedido, $intento, $enviado, $observacion);
    }

    private function resultado(mixed $actividad, mixed $docente, mixed $pedido, mixed $intento, mixed $enviado, mixed $observacion): array
    {
        if ($this->errores !== []) {
            throw new EntregaInvalidaException($this->errores);
        }

        return [
            'actividad_id' => $actividad,
            'docente_id' => $docente,
            'pedido' => $pedido,
            'intento' => $intento,
            'enviado_en' => $enviado,
            'observacion' => $observacion,
        ];
    }

    private function error(string $campo, string $mensaje): null
    {
        $this->errores[$campo][] = $mensaje;

        return null;
    }

    private function uuid(array $d, string $clave, string $campo): ?string
    {
        $v = $d[$clave] ?? null;
        if ($v === null || $v === '') {
            return $this->error($campo, "El campo {$campo} es obligatorio.");
        }
        if (! is_string($v) || ! preg_match(self::UUID, $v)) {
            return $this->error($campo, "El campo {$campo} debe ser un UUID válido.");
        }

        return strtolower($v);
    }

    private function pedido(array $d, string $clave, string $campo): ?string
    {
        $v = $d[$clave] ?? null;
        if ($v === null || (is_string($v) && trim($v) === '')) {
            return $this->error($campo, "El campo {$campo} es obligatorio.");
        }
        if (! is_string($v)) {
            return $this->error($campo, "El campo {$campo} debe ser texto.");
        }
        $v = trim($v);
        if (mb_strlen($v) < 20) {
            return $this->error($campo, 'El pedido debe tener al menos 20 caracteres.');
        }
        if (mb_strlen($v) > 4000) {
            return $this->error($campo, 'El pedido no puede superar los 4000 caracteres.');
        }

        return $v;
    }

    /** Origen A: the form sends the attempt as text ("2"). Only plain digits are accepted. */
    private function intentoDesdeTexto(array $d, string $clave, string $campo): ?int
    {
        $v = $d[$clave] ?? null;
        if ($v === null || $v === '') {
            return $this->error($campo, "El campo {$campo} es obligatorio.");
        }
        if (is_int($v)) {
            return $this->rangoIntento($v, $campo);
        }
        if (! is_string($v) || ! preg_match('/^[0-9]+$/', trim($v))) {
            return $this->error($campo, "El campo {$campo} debe ser un número entero escrito con dígitos (ej. \"2\"); no se aceptan palabras ni decimales.");
        }

        return $this->rangoIntento((int) trim($v), $campo);
    }

    /** Origen B: the attempt must be a JSON integer (not text, not float). */
    private function intentoEntero(array $d, string $clave, string $campo): ?int
    {
        $v = $d[$clave] ?? null;
        if ($v === null) {
            return $this->error($campo, "El campo {$campo} es obligatorio.");
        }
        if (! is_int($v)) {
            return $this->error($campo, "El campo {$campo} debe ser un entero JSON (no texto ni decimal).");
        }

        return $this->rangoIntento($v, $campo);
    }

    private function rangoIntento(int $v, string $campo): ?int
    {
        if ($v < 1 || $v > 3) {
            return $this->error($campo, 'El intento debe estar entre 1 y 3.');
        }

        return $v;
    }

    /** Origen A: "dd/mm/yyyy HH:mm" in Ecuador local time -> ISO 8601 UTC. */
    private function fechaWeb(array $d, string $clave, string $campo): ?string
    {
        $v = $d[$clave] ?? null;
        if ($v === null || $v === '') {
            return $this->error($campo, "El campo {$campo} es obligatorio.");
        }
        $formato = 'd/m/Y H:i';
        $mensaje = "El campo {$campo} debe tener el formato dd/mm/aaaa HH:mm (ej. 10/11/2026 14:30), hora de Ecuador.";
        if (! is_string($v) || ! preg_match('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#', $v)) {
            return $this->error($campo, $mensaje);
        }
        $fecha = Carbon::createFromFormat($formato, $v, self::ZONA_WEB);
        // Round trip rejects overflow dates such as 31/02/2026 or 25:61.
        if ($fecha === false || $fecha->format($formato) !== $v) {
            return $this->error($campo, "El campo {$campo} no es una fecha u hora real.");
        }

        return $fecha->utc()->format('Y-m-d\TH:i:sP');
    }

    /** Origen B: epoch milliseconds (UTC) -> ISO 8601 UTC, second precision. */
    private function fechaEpochMs(array $d, string $clave, string $campo): ?string
    {
        $v = $d[$clave] ?? null;
        if ($v === null) {
            return $this->error($campo, "El campo {$campo} es obligatorio.");
        }
        if (! is_int($v)) {
            return $this->error($campo, "El campo {$campo} debe ser un entero JSON con milisegundos desde 1970 (UTC).");
        }
        // 2000-01-01 .. 2100-01-01: guards against seconds sent instead of milliseconds.
        if ($v < 946684800000 || $v > 4102444800000) {
            return $this->error($campo, "El campo {$campo} está fuera de rango; se esperan milisegundos (no segundos).");
        }
        try {
            return Carbon::createFromTimestampMsUTC($v)->setMillisecond(0)->format('Y-m-d\TH:i:sP');
        } catch (Throwable) {
            return $this->error($campo, "El campo {$campo} no es una fecha válida.");
        }
    }

    /** null and blank text both mean "no comment" and normalize to null. */
    private function observacion(array $d, string $clave, string $campo): ?string
    {
        $v = $d[$clave] ?? null;
        if ($v === null) {
            return null;
        }
        if (! is_string($v)) {
            return $this->error($campo, "El campo {$campo} debe ser texto o null.");
        }
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (mb_strlen($v) > 500) {
            return $this->error($campo, 'La observación no puede superar los 500 caracteres.');
        }

        return $v;
    }
}
