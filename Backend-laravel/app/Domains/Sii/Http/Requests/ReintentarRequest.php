<?php

namespace App\Domains\Sii\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReintentarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'razon' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'razon.max' => 'La razon no puede superar 200 caracteres.',
        ];
    }
}
