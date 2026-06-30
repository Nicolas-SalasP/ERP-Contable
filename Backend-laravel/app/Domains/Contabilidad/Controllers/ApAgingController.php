<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Contabilidad\Services\ApAgingService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de Cuentas por Pagar por Antigüedad (AP Aging).
 *
 * Expone el reporte de saldo pendiente de pago clasificado por
 * tramos de vencimiento. Solo accesible con permiso contabilidad.ver.
 */
class ApAgingController extends Controller
{
    public function __construct(private readonly ApAgingService $service) {}

    /**
     * Retorna el reporte AP Aging de la empresa activa del usuario.
     *
     * Respuesta JSON:
     * {
     *   "resumen": { "corriente": 0, "d30": 0, "d60": 0, "d90": 0, "d90plus": 0, "total": 0 },
     *   "detalle": [
     *     { "proveedor_id": 1, "razon_social": "...", "rut": "...", "corriente": 0, ... }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $reporte = $this->service->obtenerReporte();

        return response()->json(['success' => true, 'data' => $reporte]);
    }
}
