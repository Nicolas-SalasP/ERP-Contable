<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ReporteCotizacionesSinFacturaCommandTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearEmpresaConDatos(string $rutEmpresa, string $rutCliente): array
    {
        $empresa = Empresa::create(['rut' => $rutEmpresa, 'razon_social' => "Empresa {$rutEmpresa}"]);
        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'rut' => $rutCliente,
            'razon_social' => "Cliente {$rutCliente}",
            'estado' => 'ACTIVO',
        ]);
        $estadoAceptada = EstadoCotizacion::firstOrCreate(['nombre' => 'Aceptada']);

        $cotizacion = Cotizacion::create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'numero_cotizacion' => 'COT-'.uniqid(),
            'fecha_emision' => now()->format('Y-m-d'),
            'fecha_validez' => now()->addDays(30)->format('Y-m-d'),
            'validez' => 30,
            'subtotal' => 100000,
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_total' => 119000,
            'estado_id' => $estadoAceptada->id,
        ]);

        return [$empresa, $cliente, $cotizacion];
    }

    public function test_comando_existe_en_artisan_list(): void
    {
        $this->assertContains('cotizacion:reporte-sin-factura', array_keys(Artisan::all()));
    }

    public function test_lista_cotizaciones_de_todas_las_empresas_sin_filtro(): void
    {
        [$empresaA, , $cotizacionA] = $this->crearEmpresaConDatos('11.111.111-1', '22.222.222-2');
        [$empresaB, , $cotizacionB] = $this->crearEmpresaConDatos('33.333.333-3', '44.444.444-4');

        $exitCode = Artisan::call('cotizacion:reporte-sin-factura');
        $salida = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString($cotizacionA->numero_cotizacion, $salida);
        $this->assertStringContainsString($cotizacionB->numero_cotizacion, $salida);
    }

    public function test_filtra_por_empresa_no_mezcla_otras(): void
    {
        [$empresaA, , $cotizacionA] = $this->crearEmpresaConDatos('55.555.555-5', '66.666.666-6');
        [$empresaB, , $cotizacionB] = $this->crearEmpresaConDatos('77.777.777-7', '88.888.888-8');

        $exitCode = Artisan::call('cotizacion:reporte-sin-factura', ['--empresa' => $empresaA->id]);
        $salida = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString($cotizacionA->numero_cotizacion, $salida);
        $this->assertStringNotContainsString($cotizacionB->numero_cotizacion, $salida);
    }

    public function test_no_lista_cotizacion_que_ya_tiene_factura_vinculada(): void
    {
        [$empresa, $cliente, $cotizacion] = $this->crearEmpresaConDatos('99.999.999-9', '10.101.010-1');

        Factura::create([
            'empresa_id' => $empresa->id,
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $cliente->id,
            'cotizacion_id' => $cotizacion->id,
            'numero_factura' => 'FV-VINCULADA',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $exitCode = Artisan::call('cotizacion:reporte-sin-factura', ['--empresa' => $empresa->id]);
        $salida = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No hay cotizaciones', $salida);
        $this->assertStringNotContainsString($cotizacion->numero_cotizacion, $salida);
    }
}
