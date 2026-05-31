<?php

namespace App\Domains\Sii\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubirCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // 'extensions' valida la extension del archivo subido (Laravel 11+).
            // No usamos 'mimes' porque los .pfx/.p12 no tienen un MIME estandar
            // reconocido por la libreria de mime types de Laravel.
            // max:50 = 50 KB (regla file de Laravel mide en kilobytes).
            // Cubre certs reales del SII (~3-5 KB) con margen para algoritmos largos.
            'archivo'  => ['required', 'file', 'extensions:p12,pfx', 'max:50'],
            'password' => ['required', 'string', 'min:1', 'max:256'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required'   => 'Debe adjuntar el archivo .pfx o .p12 del certificado.',
            'archivo.file'       => 'El campo archivo debe ser un archivo subido.',
            'archivo.extensions' => 'El archivo debe tener extension .pfx o .p12.',
            'archivo.max'        => 'El archivo no puede superar 50 KB.',
            'password.required'  => 'Debe proveer la contrasena del certificado.',
        ];
    }
}
