<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;
use App\Support\MensajeErrorGenerico;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Services\InventarioDashboardService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Un único servicio, solo trait. */
class DashboardController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioDashboardService $dashboardService,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->dashboardService->obtener($request->user()),
            ]);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }
}
