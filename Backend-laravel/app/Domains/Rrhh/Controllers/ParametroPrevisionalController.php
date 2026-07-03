<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        // Tablas globales: solo staff interno puede escribirlas.
        if ((int) ($request->user()?->rol->jerarquia ?? 0) < 100) {
            abort(403, 'Solo el equipo de soporte puede modificar parámetros legales globales.');
        }

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

        // Validar solapamiento: vigente_desde debe ser posterior al del parámetro actual
        $actual = ParametroPrevisional::whereNull('vigente_hasta')
            ->orderByDesc('vigente_desde')
            ->first();

        if ($actual) {
            if ($datos['vigente_desde'] <= $actual->vigente_desde->toDateString()) {
                abort(422, 'La fecha vigente_desde debe ser posterior a la del parámetro actual (' .
                    $actual->vigente_desde->toDateString() . ').');
            }
        }

        $parametro = DB::transaction(function () use ($datos, $actual): ParametroPrevisional {
            // Cerrar vigencia del parámetro anterior
            if ($actual) {
                $actual->update([
                    'vigente_hasta' => Carbon::parse($datos['vigente_desde'])
                        ->subDay()
                        ->toDateString(),
                ]);
            }

            return ParametroPrevisional::create($datos);
        });

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
        // Tablas globales: solo staff interno puede escribirlas.
        if ((int) ($request->user()?->rol->jerarquia ?? 0) < 100) {
            abort(403, 'Solo el equipo de soporte puede modificar parámetros legales globales.');
        }

        $datos = $request->validate([
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'uf_valor' => 'required|numeric|min:0',
            'utm_valor' => 'required|numeric|min:0',
            'uta_valor' => 'nullable|numeric|min:0',
            'fuente' => 'nullable|string',
        ]);

        $datos['uta_valor'] = $datos['uta_valor'] ?? ($datos['utm_valor'] * 12);

        // Si ya existen liquidaciones EMITIDAS/PAGADAS de este período, fueron
        // calculadas con el valor UF/UTM anterior -- sobrescribirlo las
        // desincroniza en silencio. Solo staff interno llega aquí (jerarquia
        // 100), asi que no bloqueamos (pueden ser correcciones legitimas del
        // SII), pero avisamos con cuántas liquidaciones quedarían desfasadas.
        $existente = IndicadorMensual::where('anio', $datos['anio'])->where('mes', $datos['mes'])->first();
        $liquidacionesAfectadas = 0;
        if ($existente && ((float) $existente->uf_valor !== (float) $datos['uf_valor'] || (float) $existente->utm_valor !== (float) $datos['utm_valor'])) {
            $liquidacionesAfectadas = \App\Domains\Rrhh\Models\Liquidacion::where('anio', $datos['anio'])
                ->where('mes', $datos['mes'])
                ->whereIn('estado', ['EMITIDA', 'PAGADA'])
                ->count();

            if ($liquidacionesAfectadas > 0) {
                Log::warning('IndicadorMensual sobrescrito con liquidaciones ya calculadas en el período.', [
                    'anio' => $datos['anio'],
                    'mes' => $datos['mes'],
                    'uf_anterior' => $existente->uf_valor,
                    'uf_nuevo' => $datos['uf_valor'],
                    'liquidaciones_afectadas' => $liquidacionesAfectadas,
                    'usuario_id' => $request->user()?->id,
                ]);
            }
        }

        $indicador = IndicadorMensual::updateOrCreate(
            ['anio' => $datos['anio'], 'mes' => $datos['mes']],
            $datos,
        );

        return response()->json([
            'success' => true,
            'message' => $liquidacionesAfectadas > 0
                ? "Indicador actualizado. Aviso: {$liquidacionesAfectadas} liquidación(es) EMITIDA/PAGADA de este período fueron calculadas con el valor anterior y quedaron desfasadas."
                : 'Indicador mensual registrado.',
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
