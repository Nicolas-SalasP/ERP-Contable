<?php

namespace App\Domains\Sii\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubirCafRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // 'extensions' valida la extension del archivo subido (Laravel 11+).
            // Mas predecible que 'mimes:xml' porque PHP a veces reporta XML
            // como text/xml y otras como application/xml segun OS/finfo.
            // max:100 = 100 KB (CAFs reales pesan 5-15 KB).
            'archivo' => ['required', 'file', 'extensions:xml', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required'   => 'Debe adjuntar el archivo XML del CAF.',
            'archivo.file'       => 'El campo archivo debe ser un archivo subido.',
            'archivo.extensions' => 'El archivo debe tener extension .xml.',
            'archivo.max'        => 'El archivo no puede superar 100 KB.',
        ];
    }
}
