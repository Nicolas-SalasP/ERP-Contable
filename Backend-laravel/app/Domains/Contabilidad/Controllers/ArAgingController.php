<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Contabilidad\Services\ArAgingService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de Cuentas por Cobrar por Antigüedad (AR Aging).
 *
 * Expone el reporte de saldo pendiente de cobro clasificado por
 * tramos de vencimiento. Solo accesible con permiso contabilidad.ver.
 */
class ArAgingController extends Controller
{
    public function __construct(private readonly ArAgingService $service) {}

    /**
     * Retorna el reporte AR Aging de la empresa activa del usuario.
     *
     * Respuesta JSON:
     * {
     *   "resumen": { "corriente": 0, "d30": 0, "d60": 0, "d90": 0, "d90plus": 0, "total": 0 },
     *   "detalle": [
     *     { "cliente_id": 1, "razon_social": "...", "rut": "...", "corriente": 0, ... }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $reporte = $this->service->obtenerReporte();

        return response()->json(['success' => true, 'data' => $reporte]);
    }
}
