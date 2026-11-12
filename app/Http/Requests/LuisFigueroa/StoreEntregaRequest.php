<?php

namespace App\Http\Requests\LuisFigueroa;

use App\Models\Activity;
use App\Models\Submission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEntregaRequest extends FormRequest
{
    /** 403 (before validation) when a docente tries to submit on behalf of someone else. */
    public function authorize(): bool
    {
        $docenteId = $this->input('docente_id');

        return $this->user()->can('create', [Submission::class, is_string($docenteId) ? $docenteId : null]);
    }

    public function rules(): array
    {
        return [
            'actividad_id' => ['required', 'uuid', 'exists:activity,id'],
            'docente_id' => ['required', 'uuid', 'exists:app_user,id'],
            'pedido' => ['required', 'string', 'min:20', 'max:4000'],
            'intento' => ['required', 'integer:strict', 'between:1,3'],
            'enviado_en' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Business rules that depend on the database. They run only when the
     * basic rules passed, and report on the `intento` field (422, never 500).
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $actividad = Activity::find($this->input('actividad_id'));
                $intento = $this->input('intento');

                if ($actividad && $intento > $actividad->max_attempts) {
                    $validator->errors()->add(
                        'intento',
                        "El intento supera el máximo permitido para la actividad ({$actividad->max_attempts})."
                    );

                    return;
                }

                $existe = Submission::where('activity_id', $this->input('actividad_id'))
                    ->where('user_id', $this->input('docente_id'))
                    ->where('attempt', $intento)
                    ->exists();

                if ($existe) {
                    $validator->errors()->add('intento', 'Ya existe una entrega con ese intento para la actividad y el docente.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'uuid' => 'El campo :attribute debe ser un UUID válido.',
            'exists' => 'El valor de :attribute no existe.',
            'string' => 'El campo :attribute debe ser texto.',
            'pedido.min' => 'El pedido debe tener al menos :min caracteres.',
            'pedido.max' => 'El pedido no puede superar los :max caracteres.',
            'observacion.max' => 'La observación no puede superar los :max caracteres.',
            'intento.integer' => 'El intento debe ser un número entero (no texto).',
            'intento.between' => 'El intento debe estar entre :min y :max.',
            'enviado_en.date_format' => 'El campo enviado_en debe usar el formato ISO 8601 con zona horaria (ej. 2026-11-10T14:30:00-05:00).',
        ];
    }

    public function attributes(): array
    {
        return [
            'actividad_id' => 'actividad_id',
            'docente_id' => 'docente_id',
            'pedido' => 'pedido',
            'intento' => 'intento',
            'enviado_en' => 'enviado_en',
            'observacion' => 'observacion',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'mensaje' => 'Los datos enviados no cumplen el contrato.',
            'errores' => $validator->errors()->toArray(),
        ], 422));
    }
}
