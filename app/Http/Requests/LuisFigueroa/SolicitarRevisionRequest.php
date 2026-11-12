<?php

namespace App\Http\Requests\LuisFigueroa;

use App\Models\Submission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SolicitarRevisionRequest extends FormRequest
{
    /** Only the owner of the entrega (or an admin) can ask for its review. */
    public function authorize(): bool
    {
        $id = $this->input('entrega_id');
        $entrega = is_string($id) ? Submission::find($id) : null;

        return ! $entrega || $this->user()->can('view', $entrega);
    }

    public function rules(): array
    {
        return [
            'entrega_id' => ['required', 'uuid', 'exists:submission,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'uuid' => 'El campo :attribute debe ser un UUID válido.',
            'exists' => 'La entrega indicada no existe.',
        ];
    }

    public function attributes(): array
    {
        return ['entrega_id' => 'entrega_id'];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'mensaje' => 'Los datos enviados no cumplen el contrato.',
            'errores' => $validator->errors()->toArray(),
        ], 422, [], JSON_UNESCAPED_UNICODE));
    }
}
