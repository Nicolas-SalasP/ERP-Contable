<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReglaReposicion;
use App\Domains\Inventario\Models\StockProducto;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioReposicionService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos
    ) {
    }

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.reglas_reposicion.ver');

        return ReglaReposicion::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,stock_minimo,maneja_lotes,requiere_fecha_vencimiento',
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->when(!empty($filtros['producto_id']), function (Builder $query) use ($filtros) {
                $query->where('producto_id', (int) $filtros['producto_id']);
            })
            ->when(array_key_exists('bodega_id', $filtros) && $filtros['bodega_id'] !== null && $filtros['bodega_id'] !== '', function (Builder $query) use ($filtros) {
                $query->where('bodega_id', (int) $filtros['bodega_id']);
            })
            ->when(array_key_exists('activo', $filtros) && $filtros['activo'] !== null && $filtros['activo'] !== '', function (Builder $query) use ($filtros) {
                $query->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('producto_id')
            ->orderByRaw('bodega_id IS NOT NULL')
            ->orderBy('bodega_id')
            ->paginate($this->normalizarPerPage($filtros['per_page'] ?? 15));
    }

    public function obtener(User $usuario, int $reglaId): ReglaReposicion
    {
        $this->permisos->exigir($usuario, 'inventario.reglas_reposicion.ver');

        $regla = ReglaReposicion::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,stock_minimo,maneja_lotes,requiere_fecha_vencimiento',
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->find($reglaId);

        if (!$regla) {
            throw new Exception('La regla de reposición no existe o no pertenece a la empresa.');
        }

        return $regla;
    }

    public function crear(User $usuario, array $datos): ReglaReposicion
    {
        $this->permisos->exigir($usuario, 'inventario.reglas_reposicion.crear');

        return DB::transaction(function () use ($usuario, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $datos = $this->normalizarDatos($datos);
            $this->validarRegla($empresaId, $datos);

            $regla = ReglaReposicion::create([
                'empresa_id' => $empresaId,
                'producto_id' => $datos['producto_id'],
                'bodega_id' => $datos['bodega_id'],
                'stock_minimo' => $datos['stock_minimo'],
                'stock_objetivo' => $datos['stock_objetivo'],
                'punto_reorden' => $datos['punto_reorden'],
                'dias_alerta_vencimiento' => $datos['dias_alerta_vencimiento'],
                'activo' => $datos['activo'],
            ]);

            return $regla->load(['producto', 'bodega']);
        });
    }

    public function actualizar(User $usuario, int $reglaId, array $datos): ReglaReposicion
    {
        $this->permisos->exigir($usuario, 'inventario.reglas_reposicion.editar');

        return DB::transaction(function () use ($usuario, $reglaId, $datos) {
            $regla = ReglaReposicion::where('empresa_id', $usuario->empresa_id)->find($reglaId);

            if (!$regla) {
                throw new Exception('La regla de reposición no existe o no pertenece a la empresa.');
            }

            $datos = $this->normalizarDatos($datos);
            $this->validarRegla((int) $usuario->empresa_id, $datos, $regla->id);

            $regla->update([
                'producto_id' => $datos['producto_id'],
                'bodega_id' => $datos['bodega_id'],
                'stock_minimo' => $datos['stock_minimo'],
                'stock_objetivo' => $datos['stock_objetivo'],
                'punto_reorden' => $datos['punto_reorden'],
                'dias_alerta_vencimiento' => $datos['dias_alerta_vencimiento'],
                'activo' => $datos['activo'],
            ]);

            return $regla->refresh()->load(['producto', 'bodega']);
        });
    }

    public function eliminar(User $usuario, int $reglaId): void
    {
        $this->permisos->exigir($usuario, 'inventario.reglas_reposicion.eliminar');

        $regla = ReglaReposicion::where('empresa_id', $usuario->empresa_id)->find($reglaId);

        if (!$regla) {
            throw new Exception('La regla de reposición no existe o no pertenece a la empresa.');
        }

        $regla->delete();
    }

    public function sugerencias(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.alertas.ver');

        return $this->sugerenciasParaEmpresa((int) $usuario->empresa_id, $filtros);
    }

    public function sugerenciasParaEmpresa(int $empresaId, array $filtros = []): array
    {
        return collect($this->evaluacionesParaEmpresa($empresaId, $filtros))
            ->filter(fn (array $evaluacion) => (float) $evaluacion['cantidad_sugerida'] > 0)
            ->values()
            ->all();
    }

    public function evaluacionesParaEmpresa(int $empresaId, array $filtros = []): array
    {
        $reglas = $this->reglasActivasQuery($empresaId, $filtros)->get();

        return $reglas
            ->map(fn (ReglaReposicion $regla) => $this->evaluarRegla($regla))
            ->values()
            ->all();
    }

    public function resolverReglaActiva(int $empresaId, int $productoId, ?int $bodegaId = null): ?ReglaReposicion
    {
        if ($bodegaId !== null) {
            $especifica = ReglaReposicion::query()
                ->where('empresa_id', $empresaId)
                ->where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->where('activo', true)
                ->first();

            if ($especifica) {
                return $especifica;
            }
        }

        return ReglaReposicion::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->whereNull('bodega_id')
            ->where('activo', true)
            ->first();
    }

    public function umbralMinimoPara(int $empresaId, int $productoId, ?int $bodegaId = null): float
    {
        $regla = $this->resolverReglaActiva($empresaId, $productoId, $bodegaId);

        if ($regla) {
            return $this->redondearCantidad((float) $regla->stock_minimo);
        }

        $producto = Producto::query()
            ->where('empresa_id', $empresaId)
            ->find($productoId);

        return $this->redondearCantidad((float) ($producto?->stock_minimo ?? 0));
    }

    private function evaluarRegla(ReglaReposicion $regla): array
    {
        $stockActual = $this->obtenerStockActual(
            empresaId: (int) $regla->empresa_id,
            productoId: (int) $regla->producto_id,
            bodegaId: $regla->bodega_id !== null ? (int) $regla->bodega_id : null
        );

        $stockObjetivo = $this->resolverStockObjetivo($regla);
        $cantidadSugerida = max($stockObjetivo - $stockActual, 0);

        return [
            'regla_id' => (int) $regla->id,
            'empresa_id' => (int) $regla->empresa_id,
            'producto_id' => (int) $regla->producto_id,
            'producto_nombre' => $regla->producto?->nombre,
            'producto_sku' => $regla->producto?->sku,
            'bodega_id' => $regla->bodega_id !== null ? (int) $regla->bodega_id : null,
            'bodega_nombre' => $regla->bodega?->nombre,
            'stock_actual' => $this->redondearCantidad($stockActual),
            'stock_minimo' => $this->redondearCantidad((float) $regla->stock_minimo),
            'stock_objetivo' => $this->redondearCantidad($stockObjetivo),
            'punto_reorden' => $regla->punto_reorden !== null ? $this->redondearCantidad((float) $regla->punto_reorden) : null,
            'cantidad_sugerida' => $this->redondearCantidad($cantidadSugerida),
            'dias_alerta_vencimiento' => (int) $regla->dias_alerta_vencimiento,
            'alcance' => $regla->bodega_id ? 'BODEGA' : 'PRODUCTO',
            'severidad' => $this->resolverSeveridadStock($stockActual, (float) $regla->stock_minimo, $stockObjetivo),
        ];
    }

    private function reglasActivasQuery(int $empresaId, array $filtros = []): Builder
    {
        return ReglaReposicion::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->whereHas('producto', function (Builder $query) {
                $query->where('activo', true);
            })
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,stock_minimo,maneja_lotes,requiere_fecha_vencimiento',
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->when(!empty($filtros['producto_id']), function (Builder $query) use ($filtros) {
                $query->where('producto_id', (int) $filtros['producto_id']);
            })
            ->when(array_key_exists('bodega_id', $filtros) && $filtros['bodega_id'] !== null && $filtros['bodega_id'] !== '', function (Builder $query) use ($filtros) {
                $query->where(function (Builder $subQuery) use ($filtros) {
                    $subQuery
                        ->where('bodega_id', (int) $filtros['bodega_id'])
                        ->orWhereNull('bodega_id');
                });
            })
            ->where(function (Builder $query) {
                $query
                    ->whereNull('bodega_id')
                    ->orWhereHas('bodega', function (Builder $bodegaQuery) {
                        $bodegaQuery->where('estado', 'ACTIVA');
                    });
            })
            ->orderBy('producto_id')
            ->orderByRaw('bodega_id IS NOT NULL')
            ->orderBy('bodega_id');
    }

    private function normalizarDatos(array $datos): array
    {
        return [
            'producto_id' => (int) ($datos['producto_id'] ?? 0),
            'bodega_id' => isset($datos['bodega_id']) && $datos['bodega_id'] !== '' ? (int) $datos['bodega_id'] : null,
            'stock_minimo' => $this->redondearCantidad((float) ($datos['stock_minimo'] ?? 0)),
            'stock_objetivo' => $this->redondearCantidad((float) ($datos['stock_objetivo'] ?? 0)),
            'punto_reorden' => array_key_exists('punto_reorden', $datos) && $datos['punto_reorden'] !== null && $datos['punto_reorden'] !== ''
                ? $this->redondearCantidad((float) $datos['punto_reorden'])
                : null,
            'dias_alerta_vencimiento' => (int) ($datos['dias_alerta_vencimiento'] ?? 30),
            'activo' => array_key_exists('activo', $datos) ? filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN) : true,
        ];
    }

    private function validarRegla(int $empresaId, array $datos, ?int $ignorarReglaId = null): void
    {
        if ($datos['producto_id'] <= 0) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto es obligatorio.',
            ]);
        }

        $producto = Producto::where('empresa_id', $empresaId)->find($datos['producto_id']);

        if (!$producto) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto no existe o no pertenece a la empresa.',
            ]);
        }

        if ($datos['bodega_id'] !== null) {
            $bodegaValida = Bodega::where('empresa_id', $empresaId)
                ->where('id', $datos['bodega_id'])
                ->exists();

            if (!$bodegaValida) {
                throw ValidationException::withMessages([
                    'bodega_id' => 'La bodega no existe o no pertenece a la empresa.',
                ]);
            }
        }

        if ($datos['stock_minimo'] < 0) {
            throw ValidationException::withMessages([
                'stock_minimo' => 'El stock mínimo no puede ser negativo.',
            ]);
        }

        if ($datos['stock_objetivo'] < $datos['stock_minimo']) {
            throw ValidationException::withMessages([
                'stock_objetivo' => 'El stock objetivo debe ser mayor o igual al stock mínimo.',
            ]);
        }

        if ($datos['punto_reorden'] !== null && $datos['punto_reorden'] < $datos['stock_minimo']) {
            throw ValidationException::withMessages([
                'punto_reorden' => 'El punto de reorden debe ser mayor o igual al stock mínimo.',
            ]);
        }

        if ($datos['dias_alerta_vencimiento'] < 0) {
            throw ValidationException::withMessages([
                'dias_alerta_vencimiento' => 'Los días de alerta de vencimiento no pueden ser negativos.',
            ]);
        }

        $duplicada = ReglaReposicion::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $datos['producto_id'])
            ->when($datos['bodega_id'] === null, function (Builder $query) {
                $query->whereNull('bodega_id');
            }, function (Builder $query) use ($datos) {
                $query->where('bodega_id', $datos['bodega_id']);
            })
            ->when($ignorarReglaId !== null, function (Builder $query) use ($ignorarReglaId) {
                $query->where('id', '<>', $ignorarReglaId);
            })
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'producto_id' => 'Ya existe una regla de reposición para este producto y bodega.',
            ]);
        }
    }

    private function obtenerStockActual(int $empresaId, int $productoId, ?int $bodegaId): float
    {
        $query = StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId);

        if ($bodegaId !== null) {
            $query->where('bodega_id', $bodegaId);
        }

        return $this->redondearCantidad((float) $query->sum('stock_actual'));
    }

    private function resolverStockObjetivo(ReglaReposicion $regla): float
    {
        $stockObjetivo = (float) $regla->stock_objetivo;

        if ($stockObjetivo > 0) {
            return $this->redondearCantidad($stockObjetivo);
        }

        if ($regla->punto_reorden !== null) {
            return $this->redondearCantidad((float) $regla->punto_reorden);
        }

        return $this->redondearCantidad((float) $regla->stock_minimo);
    }

    private function resolverSeveridadStock(float $stockActual, float $stockMinimo, float $stockObjetivo): string
    {
        if ($stockMinimo > 0 && $stockActual <= 0) {
            return 'critica';
        }

        if ($stockMinimo > 0 && $stockActual <= $stockMinimo) {
            return 'alta';
        }

        if ($stockObjetivo > 0 && $stockActual < $stockObjetivo) {
            return 'media';
        }

        return 'baja';
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        if ($perPage <= 0) {
            return 15;
        }

        return min($perPage, 100);
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}
