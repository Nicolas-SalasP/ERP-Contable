<?php

namespace App\Domains\Sii\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevocarCafRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debe indicar el motivo de la revocacion.',
            'motivo.min'      => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max'      => 'El motivo no puede superar 200 caracteres.',
        ];
    }
}
