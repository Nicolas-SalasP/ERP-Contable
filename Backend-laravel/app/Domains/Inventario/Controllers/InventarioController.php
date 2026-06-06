<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Services\InventarioAjusteCriticoService;
use App\Domains\Inventario\Services\InventarioDisponibilidadService;
use App\Domains\Inventario\Services\InventarioLoteService;
use App\Domains\Inventario\Services\InventarioMovimientoService;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\InventarioReposicionService;
use App\Domains\Inventario\Services\InventarioAlertaService;
use App\Domains\Inventario\Services\InventarioDashboardService;
use App\Domains\Inventario\Services\InventarioReporteService;
use App\Domains\Inventario\Services\InventarioReservaService;
use App\Domains\Inventario\Services\InventarioUbicacionService;
use App\Domains\Inventario\Services\InventarioStockUbicacionService;
use App\Domains\Inventario\Services\InventarioService;
use App\Domains\Inventario\Services\InventarioValorizacionService;
use App\Domains\Inventario\Models\TomaFisicaInventario;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Services\InventarioTomaFisicaService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
class InventarioController
{
    use RespondeInventario;

    protected InventarioService $service;
    protected InventarioMovimientoService $movimientoService;
    protected InventarioPermisoService $permisos;
    protected InventarioValorizacionService $valorizacionService;
    protected InventarioLoteService $loteService;
    protected InventarioReservaService $reservaService;
    protected InventarioDisponibilidadService $disponibilidadService;
    protected InventarioTomaFisicaService $tomaFisicaService;
    protected InventarioReposicionService $reposicionService;
    protected InventarioAlertaService $alertaService;
    protected InventarioDashboardService $dashboardService;
    protected InventarioReporteService $reporteService;
    protected InventarioUbicacionService $ubicacionService;
    protected InventarioStockUbicacionService $stockUbicacionService;

public function __construct(
    InventarioService $service,
    InventarioMovimientoService $movimientoService,
    InventarioPermisoService $permisos,
    InventarioValorizacionService $valorizacionService,
    InventarioLoteService $loteService,
    InventarioReservaService $reservaService,
    InventarioDisponibilidadService $disponibilidadService,
    InventarioTomaFisicaService $tomaFisicaService,
    InventarioReposicionService $reposicionService,
    InventarioAlertaService $alertaService,
    InventarioDashboardService $dashboardService,
    InventarioReporteService $reporteService,
    InventarioUbicacionService $ubicacionService,
    InventarioStockUbicacionService $stockUbicacionService
) {
    $this->service = $service;
    $this->movimientoService = $movimientoService;
    $this->permisos = $permisos;
    $this->valorizacionService = $valorizacionService;
    $this->loteService = $loteService;
    $this->reservaService = $reservaService;
    $this->disponibilidadService = $disponibilidadService;
    $this->tomaFisicaService = $tomaFisicaService;
    $this->reposicionService = $reposicionService;
    $this->alertaService = $alertaService;
    $this->dashboardService = $dashboardService;
    $this->reporteService = $reporteService;
    $this->ubicacionService = $ubicacionService;
    $this->stockUbicacionService = $stockUbicacionService;
}

}
