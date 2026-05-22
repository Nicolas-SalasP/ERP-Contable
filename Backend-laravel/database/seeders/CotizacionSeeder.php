<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Domains\Core\Models\Empresa;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\EstadoCotizacion;

class CotizacionSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();
        if (!$empresa) return;

        // 1. Asegurar Clientes (Solo con los campos de tu migración)
        $clientes = Cliente::where('empresa_id', $empresa->id)->get();
        
        if ($clientes->isEmpty()) {
            $clientes = collect([
                Cliente::create([
                    'empresa_id' => $empresa->id,
                    'rut' => '76.543.210-1',
                    'razon_social' => 'Constructora Horizonte S.A.',
                    'contacto_nombre' => 'Juan Pérez',
                    'contacto_email' => 'jperez@horizonte.cl',
                    'direccion' => 'Av. Siempre Viva 742, Santiago',
                    'email' => 'contacto@horizonte.cl',
                    'estado' => 'ACTIVO'
                ]),
                Cliente::create([
                    'empresa_id' => $empresa->id,
                    'rut' => '88.888.888-8',
                    'razon_social' => 'Servicios Logísticos Globales SpA',
                    'contacto_nombre' => 'María López',
                    'contacto_email' => 'm.lopez@slg.cl',
                    'direccion' => 'Calle Industrial 500, Pudahuel',
                    'email' => 'info@slg.cl',
                    'estado' => 'ACTIVO'
                ])
            ]);
        }

        $estados = EstadoCotizacion::all();
        $now = Carbon::now();

        // 2. Ejemplos de Cotizaciones
        $ejemplos = [
            [
                'cliente' => $clientes[0],
                'items' => [
                    ['descripcion' => 'Mano de obra técnica especializada', 'cantidad' => 1, 'precio_unitario' => 1200000],
                    ['descripcion' => 'Pack materiales eléctricos industriales', 'cantidad' => 2, 'precio_unitario' => 450000],
                ]
            ],
            [
                'cliente' => $clientes[1],
                'items' => [
                    ['descripcion' => 'Horas consultoría logística', 'cantidad' => 40, 'precio_unitario' => 35000],
                    ['descripcion' => 'Implementación software seguimiento', 'cantidad' => 1, 'precio_unitario' => 890000],
                ]
            ]
        ];

        foreach ($ejemplos as $index => $data) {
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $subtotal += $item['cantidad'] * $item['precio_unitario'];
            }

            $iva = round($subtotal * 0.19);
            $total = $subtotal + $iva;
            $estado = $estados->random();

            // Insertar Cotización
            $cotizacionId = DB::table('cotizaciones')->insertGetId([
                'empresa_id' => $empresa->id,
                'cliente_id' => $data['cliente']->id,
                'estado_id' => $estado->id,
                'nombre_cliente' => $data['cliente']->razon_social,
                'numero_cotizacion' => 'COT-' . (1000 + $index),
                'fecha_emision' => $now->copy()->subDays(rand(1, 15))->format('Y-m-d'),
                'fecha_validez' => $now->copy()->addDays(15)->format('Y-m-d'),
                'validez' => 15,
                'subtotal' => $subtotal,
                'porcentaje_descuento' => 0,
                'monto_descuento' => 0,
                'monto_neto' => $subtotal,
                'porcentaje_iva' => 19,
                'monto_iva' => $iva,
                'monto_total' => $total,
                'total' => $total,
                'es_afecta' => true,
                'notas_condiciones' => 'Cotización generada automáticamente.',
                'created_at' => $now,
            ]);

            // Insertar Detalles (Solo con las columnas que realmente existen en tu BD)
            foreach ($data['items'] as $item) {
                DB::table('cotizacion_detalles')->insert([
                    'cotizacion_id' => $cotizacionId,
                    'producto_nombre' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $item['cantidad'] * $item['precio_unitario'],
                ]);
            }
        }
    }
}