<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Services\DashboardResumenService;
use App\Domains\Core\Support\ModuloPermisos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Dashboard
 */
class DashboardResumenController
{
    public function __construct(
        private readonly DashboardResumenService $service
    ) {}

    /**
     * Resumen agregado del dashboard: KPIs, series 12 meses, top clientes, facturas urgentes y secciones gateadas por permiso.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resumen(Request $request): JsonResponse
    {
        $request->validate([
            'periodo' => 'sometimes|string|in:mes,trimestre,año',
        ]);

        $periodo = $request->get('periodo', 'mes');
        /** @var \App\Domains\Core\Models\User $usuario */
        $usuario   = $request->user();
        $empresaId = $usuario->empresa_activa_id ?? $usuario->empresa_id;
        $permisos  = ModuloPermisos::permisosUsuario($usuario);

        $data = $this->service->obtener($empresaId, $periodo, $permisos);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
