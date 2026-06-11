<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administración de la parametrización legal (tasas previsionales, indicadores
 * mensuales UF/UTM y tabla del impuesto único). Estos valores cambian
 * periódicamente y NUNCA están hardcodeados en el motor de cálculo.
 */
class ParametroPrevisionalController extends Controller
{
    public function indexParametros(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ParametroPrevisional::orderByDesc('vigente_desde')->get(),
        ]);
    }

    public function storeParametro(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'vigente_desde' => 'required|date',
            'vigente_hasta' => 'nullable|date|after:vigente_desde',
            'afp_cotizacion_pct' => 'required|numeric|min:0',
            'afp_comisiones_json' => 'required|array',
            'afp_sis_pct' => 'required|numeric|min:0',
            'tope_imponible_uf' => 'required|numeric|min:0',
            'salud_fonasa_pct' => 'required|numeric|min:0',
            'afc_indefinido_trabajador_pct' => 'required|numeric|min:0',
            'afc_indefinido_empleador_pct' => 'required|numeric|min:0',
            'afc_plazo_fijo_trabajador_pct' => 'required|numeric|min:0',
            'afc_plazo_fijo_empleador_pct' => 'required|numeric|min:0',
            'afc_tope_imponible_uf' => 'required|numeric|min:0',
            'imm' => 'required|numeric|min:0',
            'gratificacion_tope_mensual_factor' => 'nullable|numeric|min:0',
            'cotizacion_adicional_empleador_pct' => 'nullable|numeric|min:0',
            'mutual_cotizacion_basica_pct' => 'nullable|numeric|min:0',
            'asignacion_familiar_tramos_json' => 'nullable|array',
            'fuente' => 'nullable|string',
        ]);

        $parametro = ParametroPrevisional::create($datos);

        return response()->json([
            'success' => true,
            'message' => 'Parámetro previsional creado.',
            'data' => $parametro,
        ], 201);
    }

    public function indexIndicadores(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => IndicadorMensual::orderByDesc('anio')->orderByDesc('mes')->get(),
        ]);
    }

    public function storeIndicador(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'uf_valor' => 'required|numeric|min:0',
            'utm_valor' => 'required|numeric|min:0',
            'uta_valor' => 'nullable|numeric|min:0',
            'fuente' => 'nullable|string',
        ]);

        $datos['uta_valor'] = $datos['uta_valor'] ?? ($datos['utm_valor'] * 12);

        $indicador = IndicadorMensual::updateOrCreate(
            ['anio' => $datos['anio'], 'mes' => $datos['mes']],
            $datos,
        );

        return response()->json([
            'success' => true,
            'message' => 'Indicador mensual registrado.',
            'data' => $indicador,
        ], 201);
    }

    public function indexImpuesto(Request $request): JsonResponse
    {
        $anio = $request->integer('anio') ?: (int) date('Y');
        return response()->json([
            'success' => true,
            'data' => TablaImpuestoUnico::paraAnio($anio),
        ]);
    }
}
