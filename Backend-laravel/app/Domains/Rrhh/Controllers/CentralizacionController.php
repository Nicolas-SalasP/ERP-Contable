<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Models\RrhhMapeoContable;
use App\Domains\Rrhh\Services\Contabilidad\CentralizacionRemuneracionesService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión del mapeo contable RRHH y centralización mensual (R5).
 *
 * Flujo típico:
 *  1. Admin configura las cuentas → POST /api/rrhh/mapeo-contable
 *  2. Fin de mes: emitir liquidaciones → POST /api/rrhh/liquidaciones/{id}/emitir
 *  3. Centralizar → POST /api/rrhh/centralizacion/{anio}/{mes}
 */
class CentralizacionController extends Controller
{
    public function __construct(
        private readonly CentralizacionRemuneracionesService $service,
    ) {}

    // ── Mapeo contable ────────────────────────────────────────────────────────

    public function indexMapeo(Request $request): JsonResponse
    {
        $mapeos = RrhhMapeoContable::where('empresa_id', $request->user()->empresa_id)
            ->orderBy('tipo_cuenta')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $mapeos,
            'tipos_requeridos' => RrhhMapeoContable::TIPOS_REQUERIDOS,
            'todos_los_tipos'  => RrhhMapeoContable::TODOS_LOS_TIPOS,
        ]);
    }

    public function upsertMapeo(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo_cuenta'           => 'required|string|in:' . implode(',', RrhhMapeoContable::TODOS_LOS_TIPOS),
            'cuenta_contable_codigo' => 'required|string|max:20',
            'descripcion'           => 'nullable|string|max:255',
        ]);

        $mapeo = RrhhMapeoContable::updateOrCreate(
            [
                'empresa_id' => $request->user()->empresa_id,
                'tipo_cuenta' => $datos['tipo_cuenta'],
            ],
            [
                'cuenta_contable_codigo' => $datos['cuenta_contable_codigo'],
                'descripcion'            => $datos['descripcion'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Mapeo contable guardado.',
            'data'    => $mapeo,
        ]);
    }

    public function destroyMapeo(Request $request, string $tipo): JsonResponse
    {
        $mapeo = RrhhMapeoContable::where('empresa_id', $request->user()->empresa_id)
            ->where('tipo_cuenta', $tipo)
            ->firstOrFail();

        $mapeo->delete();

        return response()->json(['success' => true, 'message' => 'Mapeo eliminado.']);
    }

    // ── Centralización mensual ────────────────────────────────────────────────

    public function centralizar(Request $request, int $anio, int $mes): JsonResponse
    {
        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            return response()->json(['success' => false, 'message' => 'Período inválido.'], 422);
        }

        $asiento = $this->service->centralizar(
            $request->user()->empresa_id,
            $anio,
            $mes,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Remuneraciones %02d/%d centralizadas. Comprobante: %s',
                $mes,
                $anio,
                $asiento->numero_comprobante
            ),
            'data' => $asiento,
        ], 201);
    }
}
