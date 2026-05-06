<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Exception;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    protected InventarioPermisoService $permisos;

    public function __construct(InventarioPermisoService $permisos)
    {
        $this->permisos = $permisos;
    }

    public function catalogos(int $empresaId): array
    {
        return [
            'unidades_medida' => UnidadMedida::where('activo', true)->orderBy('nombre')->get(),
            'bodegas' => Bodega::where('empresa_id', $empresaId)->orderBy('nombre')->get(),
            'tipos_producto' => ['BIEN', 'SERVICIO', 'INSUMO'],
            'metodos_valorizacion' => ['PMP', 'FIFO'],
        ];
    }

    public function listarProductos(User $usuario, array $filtros = [])
    {
        $this->permisos->exigir($usuario, 'inventario.productos.ver');

        $query = Producto::where('empresa_id', $usuario->empresa_id)
            ->with(['unidadMedida', 'bodegaDefecto'])
            ->withSum('stocks as stock_actual_total', 'stock_actual')
            ->withSum('stocks as valor_total_stock', 'valor_total');

        if (!empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);

            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo_barra', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('activo', $filtros) && $filtros['activo'] !== null && $filtros['activo'] !== '') {
            $query->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('nombre')->paginate((int) ($filtros['limit'] ?? 15));
    }

    public function obtenerProducto(User $usuario, int $id): Producto
    {
        $this->permisos->exigir($usuario, 'inventario.productos.ver');

        $producto = Producto::where('empresa_id', $usuario->empresa_id)
            ->with(['unidadMedida', 'bodegaDefecto', 'stocks.bodega'])
            ->find($id);

        if (!$producto) {
            throw new Exception('El producto solicitado no existe o no pertenece a la empresa.');
        }

        return $producto;
    }

    public function crearProducto(User $usuario, array $datos): Producto
    {
        $this->permisos->exigir($usuario, 'inventario.productos.crear');
        $this->validarProducto($usuario->empresa_id, $datos);

        return DB::transaction(function () use ($usuario, $datos) {
            $producto = Producto::create([
                'empresa_id' => $usuario->empresa_id,
                'sku' => strtoupper(trim($datos['sku'])),
                'nombre' => trim($datos['nombre']),
                'descripcion' => $datos['descripcion'] ?? null,
                'tipo_producto' => $datos['tipo_producto'] ?? 'BIEN',
                'unidad_medida_id' => $datos['unidad_medida_id'],
                'metodo_valorizacion' => $datos['metodo_valorizacion'] ?? 'PMP',
                'costo_promedio' => $datos['costo_promedio'] ?? 0,
                'precio_venta_neto' => $datos['precio_venta_neto'] ?? 0,
                'afecto_iva' => $datos['afecto_iva'] ?? true,
                'codigo_barra' => $datos['codigo_barra'] ?? null,
                'stock_minimo' => $datos['stock_minimo'] ?? 0,
                'bodega_defecto_id' => $datos['bodega_defecto_id'] ?? null,
                'permite_merma' => $datos['permite_merma'] ?? false,
                'maneja_lotes' => $datos['maneja_lotes'] ?? false,
                'requiere_fecha_vencimiento' => $datos['requiere_fecha_vencimiento'] ?? false,
                'activo' => $datos['activo'] ?? true,
            ]);

            if (!empty($datos['bodega_defecto_id'])) {
                StockProducto::firstOrCreate(
                    [
                        'empresa_id' => $usuario->empresa_id,
                        'producto_id' => $producto->id,
                        'bodega_id' => $datos['bodega_defecto_id'],
                    ],
                    [
                        'stock_actual' => 0,
                        'costo_promedio' => $producto->costo_promedio,
                        'valor_total' => 0,
                    ]
                );
            }

            return $producto->load(['unidadMedida', 'bodegaDefecto']);
        });
    }

    public function actualizarProducto(User $usuario, int $id, array $datos): Producto
    {
        $this->permisos->exigir($usuario, 'inventario.productos.editar');

        $producto = Producto::where('empresa_id', $usuario->empresa_id)->find($id);

        if (!$producto) {
            throw new Exception('El producto solicitado no existe o no pertenece a la empresa.');
        }

        $this->validarProducto($usuario->empresa_id, $datos, $id);

        $producto->update([
            'sku' => strtoupper(trim($datos['sku'])),
            'nombre' => trim($datos['nombre']),
            'descripcion' => $datos['descripcion'] ?? null,
            'tipo_producto' => $datos['tipo_producto'] ?? 'BIEN',
            'unidad_medida_id' => $datos['unidad_medida_id'],
            'metodo_valorizacion' => $datos['metodo_valorizacion'] ?? 'PMP',
            'precio_venta_neto' => $datos['precio_venta_neto'] ?? 0,
            'afecto_iva' => $datos['afecto_iva'] ?? true,
            'codigo_barra' => $datos['codigo_barra'] ?? null,
            'stock_minimo' => $datos['stock_minimo'] ?? 0,
            'bodega_defecto_id' => $datos['bodega_defecto_id'] ?? null,
            'permite_merma' => $datos['permite_merma'] ?? false,
            'maneja_lotes' => $datos['maneja_lotes'] ?? false,
            'requiere_fecha_vencimiento' => $datos['requiere_fecha_vencimiento'] ?? false,
            'activo' => $datos['activo'] ?? true,
        ]);

        return $producto->load(['unidadMedida', 'bodegaDefecto']);
    }

    public function listarBodegas(User $usuario)
    {
        $this->permisos->exigir($usuario, 'inventario.bodegas.ver');

        return Bodega::where('empresa_id', $usuario->empresa_id)
            ->orderBy('nombre')
            ->get();
    }

    public function crearBodega(User $usuario, array $datos): Bodega
    {
        $this->permisos->exigir($usuario, 'inventario.bodegas.crear');

        $codigo = strtoupper(trim((string) $datos['codigo']));

        $existe = Bodega::where('empresa_id', $usuario->empresa_id)
            ->where('codigo', $codigo)
            ->exists();

        if ($existe) {
            throw new Exception('Ya existe una bodega con ese código para la empresa.');
        }

        return Bodega::create([
            'empresa_id' => $usuario->empresa_id,
            'codigo' => $codigo,
            'nombre' => trim($datos['nombre']),
            'direccion' => $datos['direccion'] ?? null,
            'estado' => $datos['estado'] ?? 'ACTIVA',
        ]);
    }

    private function validarProducto(int $empresaId, array $datos, ?int $ignorarProductoId = null): void
    {
        $sku = strtoupper(trim((string) ($datos['sku'] ?? '')));

        if (!preg_match('/^[A-Z0-9._-]{2,50}$/', $sku)) {
            throw new Exception('El SKU debe tener entre 2 y 50 caracteres y solo puede incluir letras, números, punto, guion o guion bajo.');
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));

        if ($nombre === '') {
            throw new Exception('El nombre del producto es obligatorio.');
        }

        $tipoProducto = $datos['tipo_producto'] ?? 'BIEN';

        if (!in_array($tipoProducto, ['BIEN', 'SERVICIO', 'INSUMO'], true)) {
            throw new Exception('El tipo de producto no es válido.');
        }

        $metodoValorizacion = $datos['metodo_valorizacion'] ?? 'PMP';

        if (!in_array($metodoValorizacion, ['PMP', 'FIFO'], true)) {
            throw new Exception('El método de valorización no es válido.');
        }

        foreach (['costo_promedio', 'precio_venta_neto', 'stock_minimo'] as $campo) {
            if (isset($datos[$campo]) && (float) $datos[$campo] < 0) {
                throw new Exception('Los valores monetarios y de stock no pueden ser negativos.');
            }
        }

        $querySku = Producto::where('empresa_id', $empresaId)->where('sku', $sku);

        if ($ignorarProductoId) {
            $querySku->where('id', '<>', $ignorarProductoId);
        }

        if ($querySku->exists()) {
            throw new Exception('Ya existe un producto con ese SKU en esta empresa.');
        }

        if (!UnidadMedida::where('id', $datos['unidad_medida_id'] ?? null)->where('activo', true)->exists()) {
            throw new Exception('La unidad de medida seleccionada no es válida.');
        }

        if (!empty($datos['bodega_defecto_id'])) {
            $bodegaValida = Bodega::where('empresa_id', $empresaId)
                ->where('id', $datos['bodega_defecto_id'])
                ->where('estado', 'ACTIVA')
                ->exists();

            if (!$bodegaValida) {
                throw new Exception('La bodega por defecto no existe o no pertenece a la empresa.');
            }
        }

        $manejaLotes = filter_var($datos['maneja_lotes'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requiereFechaVencimiento = filter_var($datos['requiere_fecha_vencimiento'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($requiereFechaVencimiento && !$manejaLotes) {
            throw new Exception('Un producto que requiere fecha de vencimiento debe manejar lotes.');
        }
    }
}