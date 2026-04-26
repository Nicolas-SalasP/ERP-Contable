<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Core\Models\Empresa;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();
        $service = app(FacturaService::class);

        $prov1 = Proveedor::create([
            'empresa_id' => $empresa->id,
            'codigo_interno' => 'PROV-001',
            'rut' => '11.111.111-1',
            'razon_social' => 'Distribuidora de Insumos SpA',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP'
        ]);

        $prov2 = Proveedor::create([
            'empresa_id' => $empresa->id,
            'codigo_interno' => 'PROV-002',
            'rut' => '22.222.222-2',
            'razon_social' => 'Servicios Cloud Internacional',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP'
        ]);

        $service->registrarFacturaCompra([
            'empresa_id' => $empresa->id,
            'proveedor_id' => $prov1->id,
            'numero_factura' => 'F-500',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 100000,
        ]);

        $service->registrarFacturaCompra([
            'empresa_id' => $empresa->id,
            'proveedor_id' => $prov2->id,
            'numero_factura' => 'F-8821',
            'fecha_emision' => now()->subDays(5)->format('Y-m-d'),
            'monto_neto' => 250000,
        ]);
    }
}