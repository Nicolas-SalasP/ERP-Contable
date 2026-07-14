<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Models\User;
use App\Domains\Core\Services\DashboardResumenService;
use App\Domains\Core\Support\ModuloPermisos;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @tags Dashboard
 */
class DashboardResumenController
{
    /** Tope de rango para fecha_desde/fecha_hasta explícitas, consistente con ReporteController (vista en pantalla). */
    private const MAX_DIAS_RANGO = 366;

    public function __construct(
        private readonly DashboardResumenService $service
    ) {}

    /**
     * Resumen agregado del dashboard: KPIs, series 12 meses, top clientes, facturas urgentes y secciones gateadas por permiso.
     */
    public function resumen(Request $request): JsonResponse
    {
        $request->validate([
            'periodo' => 'sometimes|string|in:mes,trimestre,año',
            'fecha_desde' => 'sometimes|nullable|date',
            'fecha_hasta' => 'sometimes|nullable|date|after_or_equal:fecha_desde',
            'comparar_anio_anterior' => 'sometimes|boolean',
        ]);

        $periodo = $request->get('periodo', 'mes');
        $fechaDesde = $request->get('fecha_desde') ?: null;
        $fechaHasta = $request->get('fecha_hasta') ?: null;

        // Fechas explícitas tienen prioridad sobre el período preset; ambas deben venir juntas.
        if (($fechaDesde !== null) xor ($fechaHasta !== null)) {
            throw ValidationException::withMessages([
                'fecha_hasta' => 'Debe indicar fecha_desde y fecha_hasta juntas.',
            ]);
        }

        if ($fechaDesde !== null && $fechaHasta !== null) {
            $desdeCarbon = Carbon::parse($fechaDesde);
            $hastaCarbon = Carbon::parse($fechaHasta);

            if ($desdeCarbon->diffInDays($hastaCarbon) > self::MAX_DIAS_RANGO) {
                throw ValidationException::withMessages([
                    'fecha_hasta' => 'El rango de búsqueda no puede superar 1 año (366 días) por rendimiento.',
                ]);
            }
        }

        $compararAnioAnterior = $request->boolean('comparar_anio_anterior');

        /** @var User $usuario */
        $usuario = $request->user();
        $empresaId = $usuario->empresa_activa_id ?? $usuario->empresa_id;
        $permisos = ModuloPermisos::permisosUsuario($usuario);

        $data = $this->service->obtener(
            $empresaId,
            $periodo,
            $permisos,
            $fechaDesde,
            $fechaHasta,
            $compararAnioAnterior
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
