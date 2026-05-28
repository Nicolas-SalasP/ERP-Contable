<?php

namespace App\Domains\Sii\Http\Requests;

use App\Domains\Sii\Rules\RutChileno;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarConfiguracionSiiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'giro_emisor'             => ['nullable', 'string', 'max:80'],
            'codigo_actividad_sii'    => ['nullable', 'integer', 'min:1'],
            'comuna'                  => ['nullable', 'string', 'max:20'],
            'ciudad'                  => ['nullable', 'string', 'max:20'],
            'resolucion_sii_numero'   => ['nullable', 'integer', 'min:0'],
            'resolucion_sii_fecha'    => ['nullable', 'date'],
            'ambiente_sii'            => ['required', 'in:certificacion,produccion'],
            'email_intercambio_sii'   => ['nullable', 'email', 'max:80'],
            'rut_representante_legal' => ['nullable', 'string', 'max:10', new RutChileno()],
        ];
    }
}
