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

class VincularCotizacionFacturaCommandTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    private Empresa $empresa;

    private Cliente $cliente;

    private EstadoCotizacion $estadoAceptada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa] = $this->crearEmpresaConAdmin();

        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '11.111.111-1',
            'razon_social' => 'Cliente Test',
            'estado' => 'ACTIVO',
        ]);

        $this->estadoAceptada = EstadoCotizacion::firstOrCreate(['nombre' => 'Aceptada']);
        EstadoCotizacion::firstOrCreate(['nombre' => 'Facturada']);
    }

    private function crearCotizacion(): Cotizacion
    {
        return Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'nombre_cliente' => $this->cliente->razon_social,
            'numero_cotizacion' => 'COT-TEST-'.uniqid(),
            'fecha_emision' => now()->format('Y-m-d'),
            'fecha_validez' => now()->addDays(30)->format('Y-m-d'),
            'validez' => 30,
            'subtotal' => 100000,
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_total' => 119000,
            'estado_id' => $this->estadoAceptada->id,
        ]);
    }

    private function crearFactura(?int $clienteId = null): Factura
    {
        return Factura::create([
            'empresa_id' => $this->empresa->id,
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $clienteId ?? $this->cliente->id,
            'numero_factura' => 'FV-TEST-'.uniqid(),
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);
    }

    public function test_comando_existe_en_artisan_list(): void
    {
        $this->assertContains('cotizacion:vincular-factura', array_keys(Artisan::all()));
    }

    public function test_vincula_cotizacion_y_factura_y_marca_facturada(): void
    {
        $cotizacion = $this->crearCotizacion();
        $factura = $this->crearFactura();

        $this->artisan('cotizacion:vincular-factura', [
            'cotizacion_id' => $cotizacion->id,
            'factura_id' => $factura->id,
        ])
            ->expectsConfirmation('¿Confirmas el vinculo?', 'yes')
            ->assertExitCode(0);

        $factura->refresh();
        $cotizacion->refresh();

        $this->assertEquals($cotizacion->id, $factura->cotizacion_id);
        $this->assertEquals('Facturada', $cotizacion->estado->nombre);
    }

    public function test_cancela_si_no_se_confirma(): void
    {
        $cotizacion = $this->crearCotizacion();
        $factura = $this->crearFactura();

        $this->artisan('cotizacion:vincular-factura', [
            'cotizacion_id' => $cotizacion->id,
            'factura_id' => $factura->id,
        ])
            ->expectsConfirmation('¿Confirmas el vinculo?', 'no')
            ->assertExitCode(0);

        $this->assertNull($factura->fresh()->cotizacion_id);
        $this->assertEquals('Aceptada', $cotizacion->fresh()->estado->nombre);
    }

    public function test_rechaza_factura_de_otra_empresa(): void
    {
        $cotizacion = $this->crearCotizacion();

        $otraEmpresa = Empresa::create(['rut' => '22.222.222-2', 'razon_social' => 'Otra SpA']);
        $otroCliente = Cliente::create([
            'empresa_id' => $otraEmpresa->id,
            'rut' => '33.333.333-3',
            'razon_social' => 'Cliente Otra Empresa',
            'estado' => 'ACTIVO',
        ]);
        $facturaAjena = Factura::create([
            'empresa_id' => $otraEmpresa->id,
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $otroCliente->id,
            'numero_factura' => 'FV-AJENA-1',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190,
            'estado' => 'REGISTRADA',
        ]);

        $exitCode = Artisan::call('cotizacion:vincular-factura', [
            'cotizacion_id' => $cotizacion->id,
            'factura_id' => $facturaAjena->id,
        ]);

        $this->assertSame(1, $exitCode);
        $facturaAjena->refresh();
        $this->assertNull($facturaAjena->cotizacion_id);
    }

    public function test_rechaza_factura_ya_vinculada_a_otra_cotizacion(): void
    {
        $cotizacionA = $this->crearCotizacion();
        $cotizacionB = $this->crearCotizacion();
        $factura = $this->crearFactura();
        $factura->update(['cotizacion_id' => $cotizacionA->id]);

        $exitCode = Artisan::call('cotizacion:vincular-factura', [
            'cotizacion_id' => $cotizacionB->id,
            'factura_id' => $factura->id,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertEquals($cotizacionA->id, $factura->fresh()->cotizacion_id);
    }

    public function test_rechaza_cliente_distinto_sin_forzar(): void
    {
        $cotizacion = $this->crearCotizacion();

        $otroCliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '44.444.444-4',
            'razon_social' => 'Cliente Distinto',
            'estado' => 'ACTIVO',
        ]);
        $factura = $this->crearFactura($otroCliente->id);

        $exitCode = Artisan::call('cotizacion:vincular-factura', [
            'cotizacion_id' => $cotizacion->id,
            'factura_id' => $factura->id,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertNull($factura->fresh()->cotizacion_id);
    }
}
