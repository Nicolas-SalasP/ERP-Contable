<?php

namespace App\Domains\Sii\Http\Controllers;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Http\Requests\ActualizarConfiguracionSiiRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiiConfiguracionController extends Controller
{
    private const CAMPOS_SII = [
        'giro_emisor',
        'codigo_actividad_sii',
        'comuna',
        'ciudad',
        'resolucion_sii_numero',
        'resolucion_sii_fecha',
        'ambiente_sii',
        'email_intercambio_sii',
        'rut_representante_legal',
    ];

    public function show(Request $request): JsonResponse
    {
        $empresa = Empresa::findOrFail($request->user()->empresa_id);

        return response()->json($empresa->only(self::CAMPOS_SII));
    }

    public function update(ActualizarConfiguracionSiiRequest $request): JsonResponse
    {
        $empresa = Empresa::findOrFail($request->user()->empresa_id);

        // validated() solo contiene las claves declaradas en rules(): empresa_id
        // inyectado en el payload por un atacante NO entra aqui (mass-assignment safe).
        $empresa->fill($request->validated());
        $empresa->save();

        return response()->json($empresa->only(self::CAMPOS_SII));
    }
}
