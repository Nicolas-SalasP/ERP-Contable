<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Services\CotizacionService;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CorregirFechaFacturaCommandTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    private Empresa $empresa;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        foreach ([
            ['152005', 'Clientes CxC', 'ACTIVO'],
            ['501105', 'Ventas', 'INGRESO'],
            ['353360', 'IVA Débito Fiscal', 'PASIVO'],
        ] as [$cod, $nom, $tipo]) {
            PlanCuenta::create([
                'empresa_id' => $this->empresa->id,
                'codigo' => $cod,
                'nombre' => $nom,
                'tipo' => $tipo,
                'imputable' => true,
                'activo' => true,
            ]);
        }
    }

    public function test_comando_existe_en_artisan_list(): void
    {
        $this->assertContains('factura:corregir-fecha', array_keys(Artisan::all()));
    }

    public function test_corrige_fecha_de_factura_y_asiento_vinculado(): void
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '11.111.111-1',
            'razon_social' => 'Cliente Test',
            'estado' => 'ACTIVO',
        ]);
        $estadoAceptada = EstadoCotizacion::firstOrCreate(['nombre' => 'Aceptada']);
        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'numero_cotizacion' => 'COT-TEST-1',
            'fecha_emision' => '2026-01-20',
            'fecha_validez' => now()->addDays(30)->format('Y-m-d'),
            'validez' => 30,
            'subtotal' => 100000,
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_total' => 119000,
            'estado_id' => $estadoAceptada->id,
        ]);

        $this->actingAs($this->usuario);
        $factura = app(CotizacionService::class)
            ->convertirEnFactura($this->empresa->id, $cotizacion->id, null);

        // Reproduce el bug: se creo con fecha de hoy en vez de la fecha real de la cotizacion.
        $this->assertNotEquals('2026-01-20', $factura->fecha_emision->format('Y-m-d'));

        $asiento = AsientoContable::where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();
        $this->assertNotNull($asiento);

        $this->artisan('factura:corregir-fecha', [
            'factura_id' => $factura->id,
            'fecha' => '2026-01-20',
        ])
            ->expectsConfirmation('¿Confirmas la corrección?', 'yes')
            ->assertExitCode(0);

        $factura->refresh();
        $asiento->refresh();

        $this->assertEquals('2026-01-20', $factura->fecha_emision->format('Y-m-d'));
        $this->assertEquals('2026-01-20', $asiento->fecha->format('Y-m-d'));
    }
}
