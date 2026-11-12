<?php

namespace App\Exceptions\LuisFigueroa;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Raised by the translators when a source payload cannot be normalized.
 * It carries field => messages and renders as the module's 422 shape.
 */
class EntregaInvalidaException extends RuntimeException
{
    /** @param array<string, list<string>> $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('Los datos enviados no cumplen el contrato.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'mensaje' => $this->getMessage(),
            'errores' => $this->errores,
        ], 422, [], JSON_UNESCAPED_UNICODE);
    }
}
