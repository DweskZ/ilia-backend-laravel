<?php

namespace App\Http\Resources\LuisFigueroa;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Submission */
class EntregaResource extends JsonResource
{
    public static $wrap = 'data';

    public function toArray(Request $request): array
    {
        return [
            'entrega_id' => $this->id,
            'actividad_id' => $this->activity_id,
            'docente_id' => $this->user_id,
            'intento' => $this->attempt,
            'estado' => $this->status,
            'pedido_caracteres' => mb_strlen($this->prompt),
            'observacion' => $this->observation,
            'enviado_en' => $this->sent_at->toIso8601String(),
            'registrado_en' => $this->created_at->toIso8601String(),
        ];
    }

    public function with(Request $request): array
    {
        return ['mensaje' => 'Entrega recibida'];
    }
}
