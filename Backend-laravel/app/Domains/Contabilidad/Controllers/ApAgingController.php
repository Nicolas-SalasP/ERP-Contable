<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Contabilidad\Services\ApAgingService;
use Illuminate\Http\JsonResponse;

/** Reporte AP Aging: saldo pendiente de pago por tramos de vencimiento (permiso contabilidad.ver). */
class ApAgingController extends Controller
{
    public function __construct(private readonly ApAgingService $service) {}

    /** Reporte AP Aging de la empresa activa: resumen por tramos + detalle por proveedor. */
    public function index(): JsonResponse
    {
        $reporte = $this->service->obtenerReporte();

        return response()->json(['success' => true, 'data' => $reporte]);
    }
}
