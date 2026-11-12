<?php

namespace App\Http\Requests\LuisFigueroa;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class EventoEntregaRequest extends FormRequest
{
    /** Events are produced by internal services / the instructor (admin), not by docentes. */
    public function authorize(): bool
    {
        return $this->user()->esAdmin();
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'correlation_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9._-]{8,64}$/'],
            'tipo' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'prioridad' => ['required', 'in:alta,media,baja'],
            'estado' => ['required', 'in:activo,anulada,borrador'],
            'datos' => ['required', 'array'],
            'datos.entrega_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'uuid' => 'El campo :attribute debe ser un UUID válido.',
            'string' => 'El campo :attribute debe ser texto.',
            'array' => 'El campo :attribute debe ser un objeto.',
            'regex' => 'El campo :attribute tiene un formato inválido.',
            'prioridad.in' => 'La prioridad debe ser alta, media o baja.',
            'estado.in' => 'El estado debe ser activo, anulada o borrador.',
        ];
    }

    public function attributes(): array
    {
        return array_combine($k = ['id', 'correlation_id', 'tipo', 'prioridad', 'estado', 'datos', 'datos.entrega_id'], $k);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'mensaje' => 'Los datos enviados no cumplen el contrato.',
            'errores' => $validator->errors()->toArray(),
        ], 422, [], JSON_UNESCAPED_UNICODE));
    }
}
