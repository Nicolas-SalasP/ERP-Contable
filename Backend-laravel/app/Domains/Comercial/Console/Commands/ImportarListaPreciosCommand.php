<?php

namespace App\Domains\Comercial\Console\Commands;

use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\ProveedorProducto;
use App\Domains\Inventario\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Importa la lista de precios de un proveedor (CSV/XLS/XLSX) y hace upsert en
 * proveedor_productos. Columnas esperadas en la fila de encabezado (cualquier orden):
 * codigo_proveedor, sku_interno o codigo_barra (para matchear con Producto), costo_neto.
 *
 * Las filas que no matchean ningun Producto se reportan en el output, nunca crean
 * productos nuevos ni abortan el resto del import.
 */
class ImportarListaPreciosCommand extends Command
{
    protected $signature = 'comercial:importar-lista-precios
        {proveedor_id : ID del proveedor dueno de la lista de precios}
        {archivo : Ruta al archivo CSV/XLS/XLSX con la lista}';

    protected $description = 'Importa una lista de precios de proveedor y actualiza el costo de reposicion de sus productos.';

    public function handle(): int
    {
        $proveedorId = (int) $this->argument('proveedor_id');
        $rutaArchivo = (string) $this->argument('archivo');

        $proveedor = Proveedor::find($proveedorId);
        if (! $proveedor) {
            $this->error("Proveedor {$proveedorId} no existe.");

            return self::FAILURE;
        }

        if (! is_file($rutaArchivo)) {
            $this->error("El archivo '{$rutaArchivo}' no existe.");

            return self::FAILURE;
        }

        try {
            $hoja = IOFactory::load($rutaArchivo)->getActiveSheet();
        } catch (\Throwable $e) {
            $this->error('No se pudo leer el archivo: '.$e->getMessage());

            return self::FAILURE;
        }

        $columnas = $this->localizarColumnas($hoja);
        if ($columnas === null) {
            $this->error('No se encontro una fila de encabezado con las columnas esperadas (codigo_proveedor, sku_interno o codigo_barra, costo_neto).');

            return self::FAILURE;
        }

        $importados = 0;
        $noEncontrados = [];
        $invalidos = [];
        $ultimaFila = $hoja->getHighestDataRow();

        DB::transaction(function () use (
            $hoja,
            $columnas,
            $proveedor,
            $ultimaFila,
            &$importados,
            &$noEncontrados,
            &$invalidos
        ) {
            for ($fila = $columnas['fila'] + 1; $fila <= $ultimaFila; $fila++) {
                $codigoProveedor = $this->valorCelda($hoja, $columnas['codigo_proveedor'], $fila);
                $skuInterno = $this->valorCelda($hoja, $columnas['sku_interno'], $fila);
                $codigoBarra = $this->valorCelda($hoja, $columnas['codigo_barra'], $fila);
                $costoNeto = $this->valorCelda($hoja, $columnas['costo_neto'], $fila);

                if ($codigoProveedor === null && $skuInterno === null && $codigoBarra === null && $costoNeto === null) {
                    continue;
                }

                if ($costoNeto === null || ! is_numeric($costoNeto) || (float) $costoNeto < 0) {
                    $invalidos[] = "Fila {$fila}: costo_neto invalido ('{$costoNeto}').";

                    continue;
                }

                $producto = $this->matchearProducto($proveedor->empresa_id, $skuInterno, $codigoBarra);
                if ($producto === null) {
                    $referencia = $skuInterno ?? $codigoBarra ?? $codigoProveedor ?? '(sin referencia)';
                    $noEncontrados[] = "Fila {$fila}: no matchea ningun producto (referencia: {$referencia}).";

                    continue;
                }

                ProveedorProducto::updateOrCreate(
                    [
                        'proveedor_id' => $proveedor->id,
                        'producto_id' => $producto->id,
                    ],
                    [
                        'empresa_id' => $proveedor->empresa_id,
                        'codigo_proveedor' => $codigoProveedor,
                        'costo_neto' => (float) $costoNeto,
                        'moneda' => $proveedor->moneda_defecto ?? 'CLP',
                        'vigente_desde' => now()->toDateString(),
                        'activo' => true,
                    ]
                );

                $importados++;
            }
        });

        $this->info("Importados/actualizados: {$importados}.");

        if (! empty($invalidos)) {
            $this->warn('Filas con datos invalidos ('.count($invalidos).'):');
            foreach ($invalidos as $linea) {
                $this->line("  - {$linea}");
            }
        }

        if (! empty($noEncontrados)) {
            $this->warn('Filas sin producto que matchee ('.count($noEncontrados).'):');
            foreach ($noEncontrados as $linea) {
                $this->line("  - {$linea}");
            }
        }

        return self::SUCCESS;
    }

    /** @return array{fila: int, codigo_proveedor: ?string, sku_interno: ?string, codigo_barra: ?string, costo_neto: ?string}|null */
    private function localizarColumnas(Worksheet $hoja): ?array
    {
        $ultimaFilaBusqueda = min($hoja->getHighestDataRow(), 10);
        $ultimaColumna = $hoja->getHighestDataColumn();

        for ($fila = 1; $fila <= $ultimaFilaBusqueda; $fila++) {
            $mapa = [];
            foreach ($hoja->getRowIterator($fila, $fila)->current()->getCellIterator('A', $ultimaColumna) as $celda) {
                $valor = strtolower(trim((string) $celda->getCalculatedValue()));
                $valor = str_replace(' ', '_', $valor);
                if ($valor !== '') {
                    $mapa[$valor] = $celda->getColumn();
                }
            }

            $tieneCosto = isset($mapa['costo_neto']);
            $tieneReferencia = isset($mapa['sku_interno']) || isset($mapa['codigo_barra']);

            if ($tieneCosto && $tieneReferencia) {
                return [
                    'fila' => $fila,
                    'codigo_proveedor' => $mapa['codigo_proveedor'] ?? null,
                    'sku_interno' => $mapa['sku_interno'] ?? null,
                    'codigo_barra' => $mapa['codigo_barra'] ?? null,
                    'costo_neto' => $mapa['costo_neto'],
                ];
            }
        }

        return null;
    }

    private function valorCelda(Worksheet $hoja, ?string $columna, int $fila): ?string
    {
        if ($columna === null) {
            return null;
        }

        $valor = trim((string) $hoja->getCell($columna.$fila)->getCalculatedValue());

        return $valor === '' ? null : $valor;
    }

    private function matchearProducto(int $empresaId, ?string $skuInterno, ?string $codigoBarra): ?Producto
    {
        if ($skuInterno === null && $codigoBarra === null) {
            return null;
        }

        return Producto::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($query) use ($skuInterno, $codigoBarra) {
                if ($skuInterno !== null) {
                    $query->orWhere('sku', $skuInterno);
                }
                if ($codigoBarra !== null) {
                    $query->orWhere('codigo_barra', $codigoBarra);
                }
            })
            ->first();
    }
}
