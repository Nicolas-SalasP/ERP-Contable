<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Domains\Core\Models\Empresa;

class GastosOperacionalesSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();
        if (!$empresa) return;

        $now = Carbon::now();

        $gastos = [
            [
                'proveedor' => ['rut' => '96.506.000-5', 'nombre' => 'Enel Distribución Chile'],
                'glosa' => 'Consumo Electricidad Mes Actual',
                'cuenta_gasto' => '607125',
                'monto_neto' => 85000
            ],
            [
                'proveedor' => ['rut' => '70.005.100-2', 'nombre' => 'Aguas Andinas S.A.'],
                'glosa' => 'Consumo Agua Potable Instalaciones',
                'cuenta_gasto' => '607130',
                'monto_neto' => 42000
            ],
            [
                'proveedor' => ['rut' => '76.123.456-7', 'nombre' => 'Librería e Insumos Pro SpA'],
                'glosa' => 'Compra resmas de papel y tóner',
                'cuenta_gasto' => '606730',
                'monto_neto' => 120000
            ],
            [
                'proveedor' => ['rut' => '77.999.888-k', 'nombre' => 'Maestranza y Mantenciones Industriales'],
                'glosa' => 'Mantenimiento preventivo aire acondicionado',
                'cuenta_gasto' => '605305',
                'monto_neto' => 250000
            ],
            [
                'proveedor' => ['rut' => '88.555.444-1', 'nombre' => 'Lavandería y Limpieza Express'],
                'glosa' => 'Servicio lavado cortinas y alfombras oficinas',
                'cuenta_gasto' => '605405',
                'monto_neto' => 65000
            ],
            [
                'proveedor' => ['rut' => '76.888.777-2', 'nombre' => 'Seguridad y Ropa de Trabajo Ltda'],
                'glosa' => 'Zapatos de seguridad y overoles personal',
                'cuenta_gasto' => '601705',
                'monto_neto' => 380000
            ],
        ];

        foreach ($gastos as $index => $g) {
            $provId = DB::table('proveedores')->updateOrInsert(
                ['rut' => $g['proveedor']['rut'], 'empresa_id' => $empresa->id],
                [
                    'codigo_interno' => 'PROV-G' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    'razon_social' => $g['proveedor']['nombre'],
                    'pais_iso' => 'CL',
                    'moneda_defecto' => 'CLP',
                    'created_at' => $now
                ]
            );
            
            $actualProvId = DB::table('proveedores')
                ->where('rut', $g['proveedor']['rut'])
                ->where('empresa_id', $empresa->id)
                ->value('id');

            $neto = $g['monto_neto'];
            $iva = round($neto * 0.19);
            $bruto = $neto + $iva;
            $fecha = $now->copy()->subDays(rand(1, 28));
            $folioFactura = 'FE-' . rand(10000, 99999);
            $codigoUnicoAsiento = '2026' . str_pad(rand(1, 99999), 6, '0', STR_PAD_LEFT);

            $asientoId = DB::table('asientos_contables')->insertGetId([
                'empresa_id' => $empresa->id,
                'codigo_unico' => $codigoUnicoAsiento,
                'numero_comprobante' => 'TR-' . rand(10000, 99999),
                'fecha' => $fecha->format('Y-m-d'),
                'glosa' => $g['glosa'] . " s/Fact " . $folioFactura,
                'tipo_asiento' => 'traspaso',
                'origen_modulo' => 'compras',
                'estado' => 'CONTABILIZADO',
                'created_at' => $now
            ]);

            DB::table('detalles_asiento')->insert([
                [
                    'asiento_id' => $asientoId,
                    'cuenta_contable' => $g['cuenta_gasto'],
                    'debe' => $neto,
                    'haber' => 0,
                    'descripcion_extensa' => "Cargo a gasto: " . $g['glosa']
                ],
                [
                    'asiento_id' => $asientoId,
                    'cuenta_contable' => '152540',
                    'debe' => $iva,
                    'haber' => 0,
                    'descripcion_extensa' => "IVA Crédito Fiscal s/Fact " . $folioFactura
                ],
                [
                    'asiento_id' => $asientoId,
                    'cuenta_contable' => '352105',
                    'debe' => 0,
                    'haber' => $bruto,
                    'descripcion_extensa' => "Obligación con " . $g['proveedor']['nombre']
                ]
            ]);

            DB::table('facturas')->insert([
                'empresa_id' => $empresa->id,
                'codigo_unico' => rand(100000, 999999),
                'proveedor_id' => $actualProvId,
                'numero_factura' => $folioFactura,
                'tipo' => 'COMPRA',
                'fecha_emision' => $fecha->format('Y-m-d'),
                'monto_neto' => $neto,
                'monto_iva' => $iva,
                'monto_bruto' => $bruto,
                'estado' => 'REGISTRADA',
                'comprobante_contable' => $codigoUnicoAsiento,
                'created_at' => $fecha,
            ]);
        }
    }
}