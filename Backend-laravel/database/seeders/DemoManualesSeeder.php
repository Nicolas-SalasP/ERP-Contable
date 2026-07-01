<?php

namespace Database\Seeders;

use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración usados por los manuales de usuario (docs/manuales/).
 *
 * Puebla la empresa base (empresa_id = 1) con un set coherente de datos para
 * que las capturas de los módulos de Tesorería e Inventario muestren cifras
 * reales: una cuenta bancaria con movimientos, y un catálogo de productos con
 * bodegas, ubicaciones, stock, lotes y movimientos de kardex.
 *
 * Es idempotente (usa firstOrCreate / comprobaciones de existencia), por lo que
 * puede ejecutarse varias veces sin duplicar registros:
 *
 *   php artisan db:seed --class=Database\\Seeders\\DemoManualesSeeder
 */
class DemoManualesSeeder extends Seeder
{
    private const EMPRESA_ID = 1;

    public function run(): void
    {
        // Seguridad: datos de demostración solo en entornos no productivos.
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->seedTesoreria();
        $this->seedInventario();
    }

    /**
     * Cuenta bancaria de la empresa y una cartola de movimientos de ejemplo
     * (pendientes y conciliados) para los manuales de Tesorería y Banco.
     */
    private function seedTesoreria(): void
    {
        $now = now();

        $cuentaId = DB::table('cuentas_bancarias_empresa')
            ->where('empresa_id', self::EMPRESA_ID)
            ->where('numero_cuenta', '001-12345-09')
            ->value('id');

        if (! $cuentaId) {
            $cuentaId = DB::table('cuentas_bancarias_empresa')->insertGetId([
                'empresa_id' => self::EMPRESA_ID,
                'banco' => 'Banco de Chile',
                'tipo_cuenta' => 'Cuenta Corriente',
                'numero_cuenta' => '001-12345-09',
                'cuenta_contable' => '1-01-001',
                'saldo_actual' => 8_450_000,
                'titular' => 'Tenri SpA',
                'rut_titular' => '76.123.456-7',
                'email_notificacion' => 'tesoreria@tenri.cl',
                'created_at' => $now,
            ]);
        }

        $movimientos = [
            ['fecha' => '2026-06-28', 'descripcion' => 'Transferencia recibida - Comercial Andes Ltda', 'nro_documento' => 'TEF-884213', 'cargo' => 0,      'abono' => 1_250_000, 'estado' => 'PENDIENTE'],
            ['fecha' => '2026-06-27', 'descripcion' => 'Pago proveedor - Enel Distribucion',           'nro_documento' => 'TEF-771002', 'cargo' => 101_150, 'abono' => 0,         'estado' => 'PENDIENTE'],
            ['fecha' => '2026-06-26', 'descripcion' => 'Deposito por ventanilla',                       'nro_documento' => 'DEP-55120',  'cargo' => 0,      'abono' => 430_000,   'estado' => 'PENDIENTE'],
            ['fecha' => '2026-06-25', 'descripcion' => 'Comision mantencion cuenta',                    'nro_documento' => 'CGO-0091',   'cargo' => 8_900,  'abono' => 0,         'estado' => 'CONCILIADO'],
            ['fecha' => '2026-06-24', 'descripcion' => 'Abono cliente - Distribuidora Sur SpA',         'nro_documento' => 'TEF-660418', 'cargo' => 0,      'abono' => 780_000,   'estado' => 'CONCILIADO'],
        ];

        foreach ($movimientos as $mov) {
            $existe = DB::table('movimientos_bancarios')
                ->where('cuenta_bancaria_id', $cuentaId)
                ->where('descripcion', $mov['descripcion'])
                ->exists();

            if (! $existe) {
                DB::table('movimientos_bancarios')->insert(array_merge($mov, [
                    'empresa_id' => self::EMPRESA_ID,
                    'cuenta_bancaria_id' => $cuentaId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    /**
     * Catálogo de productos con bodegas, ubicaciones, stock, lotes y
     * movimientos de kardex para los manuales de Inventario.
     */
    private function seedInventario(): void
    {
        $now = now();
        $emp = self::EMPRESA_ID;

        // Unidad de medida base "Unidad".
        $unidadId = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true],
        )->id;

        // Bodegas.
        $bodegas = [
            ['codigo' => 'BOD-CENTRAL', 'nombre' => 'Bodega Central', 'direccion' => 'Av. Industrial 1200, Santiago',  'estado' => 'ACTIVA'],
            ['codigo' => 'BOD-SUR',     'nombre' => 'Bodega Sur',     'direccion' => 'Camino a Melipilla 4500, Maipú', 'estado' => 'ACTIVA'],
        ];
        $bodIds = [];
        foreach ($bodegas as $b) {
            $id = DB::table('inventario_bodegas')->where('empresa_id', $emp)->where('codigo', $b['codigo'])->value('id');
            if (! $id) {
                $id = DB::table('inventario_bodegas')->insertGetId(array_merge($b, [
                    'empresa_id' => $emp, 'created_at' => $now, 'updated_at' => $now,
                ]));
            }
            $bodIds[$b['codigo']] = $id;
        }

        // Ubicaciones (tipos válidos: ZONA, PASILLO, ESTANTE, NIVEL, POSICION, UBICACION).
        $ubicaciones = [
            ['bodega' => 'BOD-CENTRAL', 'codigo' => 'A-01-01', 'nombre' => 'Pasillo A · Estante 01 · Nivel 1', 'tipo' => 'UBICACION', 'pasillo' => 'A',   'estante' => '01', 'nivel' => '1', 'posicion' => '01', 'capacidad_maxima' => 500],
            ['bodega' => 'BOD-CENTRAL', 'codigo' => 'A-01-02', 'nombre' => 'Pasillo A · Estante 01 · Nivel 2', 'tipo' => 'UBICACION', 'pasillo' => 'A',   'estante' => '01', 'nivel' => '2', 'posicion' => '01', 'capacidad_maxima' => 500],
            ['bodega' => 'BOD-CENTRAL', 'codigo' => 'B-02-01', 'nombre' => 'Pasillo B · Estante 02 · Nivel 1', 'tipo' => 'UBICACION', 'pasillo' => 'B',   'estante' => '02', 'nivel' => '1', 'posicion' => '01', 'capacidad_maxima' => 300],
            ['bodega' => 'BOD-CENTRAL', 'codigo' => 'REC-01',  'nombre' => 'Zona de Recepción',                'tipo' => 'ZONA',      'pasillo' => 'REC', 'estante' => '00', 'nivel' => '0', 'posicion' => '01', 'capacidad_maxima' => 2000],
            ['bodega' => 'BOD-SUR',     'codigo' => 'S-01-01', 'nombre' => 'Pasillo S · Estante 01 · Nivel 1', 'tipo' => 'UBICACION', 'pasillo' => 'S',   'estante' => '01', 'nivel' => '1', 'posicion' => '01', 'capacidad_maxima' => 400],
            ['bodega' => 'BOD-SUR',     'codigo' => 'S-DESP',  'nombre' => 'Zona de Despacho',                 'tipo' => 'ZONA',      'pasillo' => 'DES', 'estante' => '00', 'nivel' => '0', 'posicion' => '01', 'capacidad_maxima' => 1500],
        ];
        foreach ($ubicaciones as $u) {
            $bid = $bodIds[$u['bodega']];
            $existe = DB::table('inventario_ubicaciones')->where('empresa_id', $emp)->where('bodega_id', $bid)->where('codigo', $u['codigo'])->exists();
            if (! $existe) {
                DB::table('inventario_ubicaciones')->insert([
                    'empresa_id' => $emp, 'bodega_id' => $bid, 'ubicacion_padre_id' => null,
                    'codigo' => $u['codigo'], 'nombre' => $u['nombre'], 'tipo' => $u['tipo'],
                    'pasillo' => $u['pasillo'], 'estante' => $u['estante'], 'nivel' => $u['nivel'], 'posicion' => $u['posicion'],
                    'capacidad_maxima' => $u['capacidad_maxima'], 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Productos (tipo_producto: BIEN|SERVICIO|INSUMO; metodo_valorizacion: PMP|FIFO).
        $productos = [
            ['sku' => 'NB-DELL-5420', 'nombre' => 'Notebook Dell Latitude 5420',        'tipo' => 'BIEN',   'metodo' => 'PMP',  'costo' => 620_000, 'precio' => 899_000, 'min' => 5,  'lotes' => false, 'barra' => '7801234500018'],
            ['sku' => 'MON-LG-24',    'nombre' => 'Monitor LG 24" Full HD',              'tipo' => 'BIEN',   'metodo' => 'PMP',  'costo' => 78_000,  'precio' => 129_990, 'min' => 10, 'lotes' => false, 'barra' => '7801234500025'],
            ['sku' => 'TEC-LOG-K120', 'nombre' => 'Teclado Logitech K120',               'tipo' => 'BIEN',   'metodo' => 'PMP',  'costo' => 7_500,   'precio' => 14_990,  'min' => 20, 'lotes' => false, 'barra' => '7801234500032'],
            ['sku' => 'PAP-RESMA-A4', 'nombre' => 'Resma Papel A4 75g (500 hojas)',      'tipo' => 'INSUMO', 'metodo' => 'FIFO', 'costo' => 2_600,   'precio' => 4_590,   'min' => 50, 'lotes' => false, 'barra' => '7801234500049'],
            ['sku' => 'TON-HP-26A',   'nombre' => 'Tóner HP 26A Negro',                  'tipo' => 'INSUMO', 'metodo' => 'PMP',  'costo' => 52_000,  'precio' => 84_990,  'min' => 8,  'lotes' => false, 'barra' => '7801234500056'],
            ['sku' => 'ALC-GEL-1L',   'nombre' => 'Alcohol Gel 1L',                      'tipo' => 'INSUMO', 'metodo' => 'FIFO', 'costo' => 1_900,   'precio' => 3_990,   'min' => 30, 'lotes' => true,  'barra' => '7801234500063'],
            ['sku' => 'GUA-NIT-M',    'nombre' => 'Guantes Nitrilo Talla M (caja 100)',  'tipo' => 'INSUMO', 'metodo' => 'FIFO', 'costo' => 4_200,   'precio' => 7_990,   'min' => 25, 'lotes' => true,  'barra' => '7801234500070'],
            ['sku' => 'CAB-HDMI-2M',  'nombre' => 'Cable HDMI 2 metros',                 'tipo' => 'BIEN',   'metodo' => 'PMP',  'costo' => 2_100,   'precio' => 4_990,   'min' => 40, 'lotes' => false, 'barra' => '7801234500087'],
            ['sku' => 'SIL-ERG-01',   'nombre' => 'Silla Ergonómica Oficina',            'tipo' => 'BIEN',   'metodo' => 'PMP',  'costo' => 68_000,  'precio' => 119_990, 'min' => 6,  'lotes' => false, 'barra' => '7801234500094'],
            ['sku' => 'MOU-LOG-M90',  'nombre' => 'Mouse Logitech M90',                  'tipo' => 'BIEN',   'metodo' => 'PMP',  'costo' => 4_300,   'precio' => 8_990,   'min' => 20, 'lotes' => false, 'barra' => '7801234500100'],
        ];
        $prodIds = [];
        foreach ($productos as $p) {
            $id = DB::table('inventario_productos')->where('empresa_id', $emp)->where('sku', $p['sku'])->value('id');
            if (! $id) {
                $id = DB::table('inventario_productos')->insertGetId([
                    'empresa_id' => $emp, 'sku' => $p['sku'], 'nombre' => $p['nombre'], 'descripcion' => $p['nombre'],
                    'tipo_producto' => $p['tipo'], 'unidad_medida_id' => $unidadId, 'metodo_valorizacion' => $p['metodo'],
                    'costo_promedio' => $p['costo'], 'precio_venta_neto' => $p['precio'], 'afecto_iva' => true,
                    'codigo_barra' => $p['barra'], 'stock_minimo' => $p['min'], 'bodega_defecto_id' => $bodIds['BOD-CENTRAL'],
                    'permite_merma' => true, 'activo' => true, 'maneja_lotes' => $p['lotes'], 'requiere_fecha_vencimiento' => $p['lotes'],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $prodIds[$p['sku']] = ['id' => $id, 'costo' => $p['costo']];
        }

        // Stock por bodega (algunos bajo el mínimo para ilustrar alertas).
        $stockPlan = [
            'NB-DELL-5420' => ['BOD-CENTRAL' => 12, 'BOD-SUR' => 3],
            'MON-LG-24'    => ['BOD-CENTRAL' => 34, 'BOD-SUR' => 8],
            'TEC-LOG-K120' => ['BOD-CENTRAL' => 15, 'BOD-SUR' => 0],
            'PAP-RESMA-A4' => ['BOD-CENTRAL' => 220, 'BOD-SUR' => 40],
            'TON-HP-26A'   => ['BOD-CENTRAL' => 4, 'BOD-SUR' => 2],
            'ALC-GEL-1L'   => ['BOD-CENTRAL' => 120, 'BOD-SUR' => 25],
            'GUA-NIT-M'    => ['BOD-CENTRAL' => 18, 'BOD-SUR' => 5],
            'CAB-HDMI-2M'  => ['BOD-CENTRAL' => 95, 'BOD-SUR' => 30],
            'SIL-ERG-01'   => ['BOD-CENTRAL' => 9, 'BOD-SUR' => 2],
            'MOU-LOG-M90'  => ['BOD-CENTRAL' => 60, 'BOD-SUR' => 14],
        ];
        foreach ($stockPlan as $sku => $porBodega) {
            $pi = $prodIds[$sku];
            foreach ($porBodega as $bcod => $cant) {
                $bid = $bodIds[$bcod];
                $existe = DB::table('inventario_stock')->where('empresa_id', $emp)->where('producto_id', $pi['id'])->where('bodega_id', $bid)->exists();
                if (! $existe) {
                    DB::table('inventario_stock')->insert([
                        'empresa_id' => $emp, 'producto_id' => $pi['id'], 'bodega_id' => $bid,
                        'stock_actual' => $cant, 'costo_promedio' => $pi['costo'], 'valor_total' => $cant * $pi['costo'],
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        // Lotes para los productos que manejan trazabilidad por lote.
        $lotes = [
            ['sku' => 'ALC-GEL-1L', 'codigo' => 'LT-ALC-2601', 'fab' => '2026-01-15', 'ven' => '2027-01-15'],
            ['sku' => 'ALC-GEL-1L', 'codigo' => 'LT-ALC-2604', 'fab' => '2026-04-10', 'ven' => '2027-04-10'],
            ['sku' => 'GUA-NIT-M',  'codigo' => 'LT-GUA-2603', 'fab' => '2026-03-01', 'ven' => '2028-03-01'],
        ];
        foreach ($lotes as $l) {
            $pi = $prodIds[$l['sku']];
            $existe = DB::table('inventario_lotes')->where('empresa_id', $emp)->where('producto_id', $pi['id'])->where('codigo_lote', $l['codigo'])->exists();
            if (! $existe) {
                DB::table('inventario_lotes')->insert([
                    'empresa_id' => $emp, 'producto_id' => $pi['id'], 'codigo_lote' => $l['codigo'],
                    'fecha_fabricacion' => $l['fab'], 'fecha_vencimiento' => $l['ven'], 'observacion' => 'Lote de demostración',
                    'activo' => true, 'estado_operativo' => 'DISPONIBLE', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Movimientos de kardex (entradas y salidas).
        $movimientos = [
            ['sku' => 'NB-DELL-5420', 'tipo' => 'ENTRADA', 'cant' => 15,  'ref' => 'OC-2026-0012', 'motivo' => 'Compra a proveedor', 'fecha' => '2026-06-05'],
            ['sku' => 'NB-DELL-5420', 'tipo' => 'SALIDA',  'cant' => 3,   'ref' => 'VD-2026-0044', 'motivo' => 'Venta',             'fecha' => '2026-06-18'],
            ['sku' => 'MON-LG-24',    'tipo' => 'ENTRADA', 'cant' => 40,  'ref' => 'OC-2026-0012', 'motivo' => 'Compra a proveedor', 'fecha' => '2026-06-05'],
            ['sku' => 'PAP-RESMA-A4', 'tipo' => 'ENTRADA', 'cant' => 250, 'ref' => 'OC-2026-0015', 'motivo' => 'Compra a proveedor', 'fecha' => '2026-06-09'],
            ['sku' => 'PAP-RESMA-A4', 'tipo' => 'SALIDA',  'cant' => 30,  'ref' => 'CI-2026-0007', 'motivo' => 'Consumo interno',    'fecha' => '2026-06-20'],
            ['sku' => 'TEC-LOG-K120', 'tipo' => 'SALIDA',  'cant' => 5,   'ref' => 'VD-2026-0051', 'motivo' => 'Venta',             'fecha' => '2026-06-22'],
        ];
        foreach ($movimientos as $m) {
            $pi = $prodIds[$m['sku']];
            $bid = $bodIds['BOD-CENTRAL'];
            $existe = DB::table('inventario_movimientos')
                ->where('empresa_id', $emp)->where('producto_id', $pi['id'])
                ->where('referencia', $m['ref'])->where('tipo', $m['tipo'])->exists();
            if (! $existe) {
                $esEntrada = $m['tipo'] === 'ENTRADA';
                DB::table('inventario_movimientos')->insert([
                    'empresa_id' => $emp, 'producto_id' => $pi['id'], 'tipo' => $m['tipo'],
                    'bodega_origen_id' => $esEntrada ? null : $bid, 'bodega_destino_id' => $esEntrada ? $bid : null,
                    'cantidad' => $m['cant'], 'costo_unitario' => $pi['costo'], 'costo_total' => $m['cant'] * $pi['costo'],
                    'referencia' => $m['ref'], 'motivo' => $m['motivo'], 'observacion' => null, 'created_by' => 1,
                    'fecha_movimiento' => $m['fecha'], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }
}
