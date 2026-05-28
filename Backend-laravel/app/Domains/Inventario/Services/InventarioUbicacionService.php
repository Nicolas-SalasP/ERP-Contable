<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioUbicacionService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioStockUbicacionService $stockUbicacionService
    ) {
    }

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.ubicaciones.ver');

        return InventarioUbicacion::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with(['bodega:id,empresa_id,codigo,nombre,estado', 'padre:id,empresa_id,bodega_id,codigo,nombre,tipo'])
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(isset($filtros['activo']) && $filtros['activo'] !== '', fn (Builder $query) => $query->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->when(!empty($filtros['tipo']), fn (Builder $query) => $query->where('tipo', $filtros['tipo']))
            ->when(!empty($filtros['search']), function (Builder $query) use ($filtros) {
                $search = trim((string) $filtros['search']);
                $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery
                        ->where('codigo', 'like', '%' . $search . '%')
                        ->orWhere('nombre', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('bodega_id')
            ->orderBy('codigo')
            ->paginate($this->normalizarPerPage($filtros['per_page'] ?? 50));
    }

    public function obtener(User $usuario, int $ubicacionId): InventarioUbicacion
    {
        $this->permisos->exigir($usuario, 'inventario.ubicaciones.ver');

        $ubicacion = InventarioUbicacion::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with(['bodega:id,empresa_id,codigo,nombre,estado', 'padre:id,empresa_id,bodega_id,codigo,nombre,tipo', 'hijos:id,empresa_id,bodega_id,ubicacion_padre_id,codigo,nombre,tipo,activo'])
            ->find($ubicacionId);

        if (!$ubicacion) {
            throw ValidationException::withMessages([
                'ubicacion_id' => 'La ubicación solicitada no existe o no pertenece a la empresa.',
            ]);
        }

        return $ubicacion;
    }

    public function crear(User $usuario, array $datos): InventarioUbicacion
    {
        $this->permisos->exigir($usuario, 'inventario.ubicaciones.crear');

        return DB::transaction(function () use ($usuario, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $bodega = $this->obtenerBodegaActivaEmpresa((int) $datos['bodega_id'], $empresaId, 'bodega_id');
            $padre = $this->resolverPadre($datos['ubicacion_padre_id'] ?? null, $empresaId, (int) $bodega->id);

            $this->validarCodigoUnico($empresaId, (int) $bodega->id, (string) $datos['codigo']);

            return InventarioUbicacion::create([
                'empresa_id' => $empresaId,
                'bodega_id' => (int) $bodega->id,
                'ubicacion_padre_id' => $padre?->id,
                'codigo' => $this->normalizarCodigo($datos['codigo']),
                'nombre' => trim((string) $datos['nombre']),
                'tipo' => $datos['tipo'] ?? InventarioUbicacion::TIPO_UBICACION,
                'pasillo' => $this->normalizarTextoOpcional($datos['pasillo'] ?? null, 40),
                'estante' => $this->normalizarTextoOpcional($datos['estante'] ?? null, 40),
                'nivel' => $this->normalizarTextoOpcional($datos['nivel'] ?? null, 40),
                'posicion' => $this->normalizarTextoOpcional($datos['posicion'] ?? null, 40),
                'capacidad_maxima' => $datos['capacidad_maxima'] ?? null,
                'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : true,
            ])->load(['bodega', 'padre']);
        });
    }

    public function actualizar(User $usuario, int $ubicacionId, array $datos): InventarioUbicacion
    {
        $this->permisos->exigir($usuario, 'inventario.ubicaciones.editar');

        return DB::transaction(function () use ($usuario, $ubicacionId, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $ubicacion = InventarioUbicacion::query()
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find($ubicacionId);

            if (!$ubicacion) {
                throw ValidationException::withMessages([
                    'ubicacion_id' => 'La ubicación solicitada no existe o no pertenece a la empresa.',
                ]);
            }

            if (!empty($datos['bodega_id']) && (int) $datos['bodega_id'] !== (int) $ubicacion->bodega_id) {
                $this->validarSinStock($ubicacion);
                $bodega = $this->obtenerBodegaActivaEmpresa((int) $datos['bodega_id'], $empresaId, 'bodega_id');
                $ubicacion->bodega_id = (int) $bodega->id;
            }

            $bodegaId = (int) $ubicacion->bodega_id;

            if (array_key_exists('codigo', $datos) && $this->normalizarCodigo($datos['codigo']) !== $ubicacion->codigo) {
                $this->validarCodigoUnico($empresaId, $bodegaId, (string) $datos['codigo'], (int) $ubicacion->id);
                $ubicacion->codigo = $this->normalizarCodigo($datos['codigo']);
            }

            if (array_key_exists('ubicacion_padre_id', $datos)) {
                $padre = $this->resolverPadre($datos['ubicacion_padre_id'], $empresaId, $bodegaId);
                if ($padre && (int) $padre->id === (int) $ubicacion->id) {
                    throw ValidationException::withMessages([
                        'ubicacion_padre_id' => 'La ubicación no puede ser padre de sí misma.',
                    ]);
                }
                if ($padre) {
                    $this->validarSinCiclo((int) $ubicacion->id, $padre);
                }
                $ubicacion->ubicacion_padre_id = $padre?->id;
            }

            foreach (['nombre', 'tipo', 'pasillo', 'estante', 'nivel', 'posicion', 'capacidad_maxima', 'activo'] as $campo) {
                if (!array_key_exists($campo, $datos)) {
                    continue;
                }

                $ubicacion->{$campo} = match ($campo) {
                    'nombre' => trim((string) $datos[$campo]),
                    'tipo' => $datos[$campo],
                    'activo' => (bool) $datos[$campo],
                    'pasillo', 'estante', 'nivel', 'posicion' => $this->normalizarTextoOpcional($datos[$campo], 40),
                    default => $datos[$campo],
                };
            }

            $ubicacion->save();

            return $ubicacion->refresh()->load(['bodega', 'padre']);
        });
    }

    public function stock(User $usuario, int $ubicacionId, array $filtros = []): LengthAwarePaginator
    {
        $ubicacion = $this->obtener($usuario, $ubicacionId);
        $filtros['ubicacion_id'] = (int) $ubicacion->id;

        return $this->stockUbicacionService->listar($usuario, $filtros);
    }

    private function obtenerBodegaActivaEmpresa(int $bodegaId, int $empresaId, string $campo): Bodega
    {
        $bodega = Bodega::query()->where('empresa_id', $empresaId)->find($bodegaId);

        if (!$bodega) {
            throw ValidationException::withMessages([$campo => 'La bodega informada no existe o no pertenece a la empresa.']);
        }

        if ($bodega->estado !== 'ACTIVA') {
            throw ValidationException::withMessages([$campo => 'La bodega informada está inactiva.']);
        }

        return $bodega;
    }

    private function resolverPadre(mixed $ubicacionPadreId, int $empresaId, int $bodegaId): ?InventarioUbicacion
    {
        if (empty($ubicacionPadreId)) {
            return null;
        }

        $padre = InventarioUbicacion::query()
            ->where('empresa_id', $empresaId)
            ->where('bodega_id', $bodegaId)
            ->find((int) $ubicacionPadreId);

        if (!$padre) {
            throw ValidationException::withMessages([
                'ubicacion_padre_id' => 'La ubicación padre no existe o no pertenece a la misma empresa/bodega.',
            ]);
        }

        return $padre;
    }

    private function validarCodigoUnico(int $empresaId, int $bodegaId, string $codigo, ?int $ignorarId = null): void
    {
        $codigo = $this->normalizarCodigo($codigo);

        $existe = InventarioUbicacion::query()
            ->where('empresa_id', $empresaId)
            ->where('bodega_id', $bodegaId)
            ->where('codigo', $codigo)
            ->when($ignorarId !== null, fn (Builder $query) => $query->where('id', '!=', $ignorarId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'codigo' => 'Ya existe una ubicación con este código en la bodega seleccionada.',
            ]);
        }
    }

    private function validarSinCiclo(int $ubicacionId, InventarioUbicacion $padre): void
    {
        $actual = $padre;

        while ($actual) {
            if ((int) $actual->id === $ubicacionId) {
                throw ValidationException::withMessages([
                    'ubicacion_padre_id' => 'La jerarquía de ubicaciones no puede formar ciclos.',
                ]);
            }

            $actual = $actual->ubicacion_padre_id
                ? InventarioUbicacion::find($actual->ubicacion_padre_id)
                : null;
        }
    }

    private function validarSinStock(InventarioUbicacion $ubicacion): void
    {
        $tieneStock = StockUbicacionInventario::query()
            ->where('empresa_id', $ubicacion->empresa_id)
            ->where('ubicacion_id', $ubicacion->id)
            ->where(function (Builder $query) {
                $query
                    ->where('stock_actual', '>', 0)
                    ->orWhere('stock_reservado', '>', 0)
                    ->orWhere('stock_bloqueado', '>', 0)
                    ->orWhere('stock_cuarentena', '>', 0)
                    ->orWhere('stock_en_transito', '>', 0);
            })
            ->exists();

        if ($tieneStock) {
            throw ValidationException::withMessages([
                'bodega_id' => 'No se puede cambiar la bodega de una ubicación con stock registrado.',
            ]);
        }
    }

    private function normalizarCodigo(mixed $codigo): string
    {
        $codigo = strtoupper(trim((string) $codigo));

        if ($codigo === '') {
            throw ValidationException::withMessages([
                'codigo' => 'Debe informar un código de ubicación.',
            ]);
        }

        return $codigo;
    }

    private function normalizarTextoOpcional(mixed $valor, int $max): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, $max);
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        return $perPage <= 0 ? 50 : min($perPage, 200);
    }
}
