<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Inventario\Exceptions\InventarioException;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Services\Reportes\ReporteAjustesService;
use App\Domains\Inventario\Services\Reportes\ReporteLotesService;
use App\Domains\Inventario\Services\Reportes\ReporteMovimientosService;
use App\Domains\Inventario\Services\Reportes\ReporteReservasService;
use App\Domains\Inventario\Services\Reportes\ReporteStockService;
use App\Domains\Inventario\Services\Reportes\ReporteTomasFisicasService;
use App\Domains\Inventario\Services\Reportes\ReporteValorizacionService;
use Illuminate\Support\Collection;

/** Fachada de reportes de Inventario que delega cada reporte a su propio servicio en Services/Reportes/ (antes ~780 líneas en un solo servicio; se separó por caso de uso porque cada método es de solo lectura, sin cruzar límites transaccionales). */
class InventarioReporteService
{
    private const MAX_LIMIT = 1000;

    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioAlertaService $alertaService,
        private readonly InventarioReposicionService $reposicionService,
        private readonly ReporteStockService $stockService,
        private readonly ReporteMovimientosService $movimientosService,
        private readonly ReporteValorizacionService $valorizacionService,
        private readonly ReporteLotesService $lotesService,
        private readonly ReporteReservasService $reservasService,
        private readonly ReporteTomasFisicasService $tomasFisicasService,
        private readonly ReporteAjustesService $ajustesService,
    ) {
    }

    public function stock(User $usuario, array $filtros = []): array
    {
        return $this->stockService->generar($usuario, $filtros);
    }

    public function movimientos(User $usuario, array $filtros = []): array
    {
        return $this->movimientosService->generar($usuario, $filtros);
    }

    public function valorizacion(User $usuario, array $filtros = []): array
    {
        return $this->valorizacionService->generar($usuario, $filtros);
    }

    public function lotes(User $usuario, array $filtros = []): array
    {
        return $this->lotesService->generar($usuario, $filtros);
    }

    public function reservas(User $usuario, array $filtros = []): array
    {
        return $this->reservasService->generar($usuario, $filtros);
    }

    public function tomasFisicas(User $usuario, array $filtros = []): array
    {
        return $this->tomasFisicasService->generar($usuario, $filtros);
    }

    public function ajustes(User $usuario, array $filtros = []): array
    {
        return $this->ajustesService->generar($usuario, $filtros);
    }

    public function reposicionAlertas(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.reportes.ver');

        $alertas = $this->alertaService->listar($usuario, $filtros + ['limit' => $filtros['limit'] ?? 200]);
        $sugerencias = $this->reposicionService->sugerencias($usuario, $filtros);

        return [
            'data' => [
                'alertas' => $alertas['data'] ?? [],
                'sugerencias_reposicion' => $sugerencias,
            ],
            'resumen' => [
                'alertas' => $alertas['resumen'] ?? [],
                'total_sugerencias_reposicion' => count($sugerencias),
                'cantidad_sugerida_total' => $this->redondear((float) collect($sugerencias)->sum('cantidad_sugerida')),
            ],
            'metadata' => [
                'generado_en' => now()->toISOString(),
                'filtros' => $filtros,
            ],
        ];
    }

    public function exportarCsv(User $usuario, string $tipo, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.reportes.exportar');

        $resultado = match ($tipo) {
            'stock' => $this->stock($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'movimientos' => $this->movimientos($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'valorizacion' => $this->valorizacion($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'lotes' => $this->lotes($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'reservas' => $this->reservas($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'tomas-fisicas' => $this->tomasFisicas($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'ajustes' => $this->ajustes($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'reposicion-alertas' => $this->reposicionAlertas($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            default => throw InventarioException::regla('El tipo de reporte no es válido para exportación CSV.'),
        };

        $filas = $this->filasExportables($tipo, $resultado['data'] ?? []);
        $encabezados = $filas->isNotEmpty() ? array_keys($filas->first()) : ['sin_datos'];

        return [
            'filename' => 'inventario_reporte_' . str_replace('-', '_', $tipo) . '_' . now()->format('Ymd_His') . '.csv',
            'headers' => $encabezados,
            'rows' => $filas->values()->all(),
        ];
    }

    private function filasExportables(string $tipo, mixed $data): Collection
    {
        if ($tipo === 'valorizacion') {
            return collect($data['por_producto'] ?? [])->map(fn ($row) => $this->aplanarFila($row));
        }

        if ($tipo === 'reposicion-alertas') {
            $alertas = collect($data['alertas'] ?? [])->map(function ($row) {
                $fila = $this->aplanarFila($row);
                $fila['origen_reporte'] = 'alerta';
                return $fila;
            });

            $sugerencias = collect($data['sugerencias_reposicion'] ?? [])->map(function ($row) {
                $fila = $this->aplanarFila($row);
                $fila['origen_reporte'] = 'sugerencia_reposicion';
                return $fila;
            });

            return $alertas->concat($sugerencias)->values();
        }

        return collect($data)->map(fn ($row) => $this->aplanarFila($row));
    }

    private function aplanarFila(array $fila): array
    {
        $resultado = [];

        foreach ($fila as $key => $value) {
            if (is_array($value) || $value instanceof Collection) {
                continue;
            }

            if (is_bool($value)) {
                $resultado[$key] = $value ? '1' : '0';
                continue;
            }

            $resultado[$key] = $value;
        }

        return $resultado;
    }

    private function redondear(float $valor, int $decimales = 4): float
    {
        return round($valor, $decimales);
    }
}
