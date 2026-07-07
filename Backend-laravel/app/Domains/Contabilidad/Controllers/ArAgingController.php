<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Contabilidad\Services\ArAgingService;
use Illuminate\Http\JsonResponse;

/** Reporte AR Aging: saldo pendiente de cobro por tramos de vencimiento (permiso contabilidad.ver). */
class ArAgingController extends Controller
{
    public function __construct(private readonly ArAgingService $service) {}

    /** Reporte AR Aging de la empresa activa: resumen por tramos + detalle por cliente. */
    public function index(): JsonResponse
    {
        $reporte = $this->service->obtenerReporte();

        return response()->json(['success' => true, 'data' => $reporte]);
    }
}
