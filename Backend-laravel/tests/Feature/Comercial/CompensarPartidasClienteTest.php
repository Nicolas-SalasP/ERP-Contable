<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\AnticipoCliente;
use App\Domains\Contabilidad\Models\PlanCuenta;

/**
 * Equivalente de CompensarPartidasReversaTest para el lado venta:
 * ClienteController::cruzarDocumentos / ClienteService::compensarPartidas cruzan
 * facturas de venta contra Notas de Crédito de venta y/o anticipos de cliente,
 * generando el mismo asiento_pago_id reversible que el lado proveedor.
 */
class CompensarPartidasClienteTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = $this->crearEmpresa([
            'rut' => '76.222.222-2',
            'razon_social' => 'Compensación Venta SpA',
        ]);
        $this->usuario = $this->crearUsuario($this->empresa, $this->rolSuperAdmin, [
            'nombre' => 'Contador Ventas',
            'email' => 'c@compensacion-venta.cl',
        ]);
        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '3.3.3.3-3',
            'razon_social' => 'Cliente Compensacion',
            'estado' => 'ACTIVO',
        ]);

        // Cuentas usadas por el asiento de traspaso de compensarPartidas (lado venta).
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '210205',
            'nombre' => 'Anticipos de Clientes', 'tipo' => 'PASIVO', 'imputable' => true, 'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '152005',
            'nombre' => 'Cuentas por Cobrar Clientes', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true,
        ]);
    }

    public function test_cruzar_documentos_sin_facturas_ids_devuelve_422_no_400()
    {
        $this->actingAs($this->usuario)
            ->postJson("/api/clientes/{$this->cliente->id}/cruzar-documentos", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('facturas_ids');
    }

    public function test_compensar_factura_venta_con_anticipo_cliente_guarda_asiento_pago_id_y_reversa_libera_la_factura()
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $this->cliente->id,
            'numero_factura' => 'V-CMP-1',
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 84034,
            'monto_iva' => 15966,
            'monto_bruto' => 100000,
            'estado' => 'REGISTRADA',
        ]);

        $anticipo = AnticipoCliente::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'monto' => 100000,
            'monto_original' => 100000,
            'saldo_disponible' => 100000,
            'estado' => 'DISPONIBLE',
        ]);

        $this->actingAs($this->usuario)
            ->postJson("/api/clientes/{$this->cliente->id}/cruzar-documentos", [
                'facturas_ids' => [$factura->id],
                'anticipos_ids' => [$anticipo->id],
            ])
            ->assertStatus(200);

        $factura->refresh();
        $anticipo->refresh();
        $this->assertEquals('PAGADA', $factura->estado);
        $this->assertNotNull($factura->asiento_pago_id, 'La Factura de venta compensada debe quedar vinculada al asiento de traspaso.');
        $this->assertEquals('APLICADO', $anticipo->estado);

        // Reversar el asiento de compensación vía el endpoint de anulación.
        $this->actingAs($this->usuario)
            ->postJson('/api/anulacion/anular', [
                'tipo_documento' => 'ASIENTO',
                'documento_id' => $factura->asiento_pago_id,
                'motivo' => 'Reverso de prueba de compensación de venta',
                'fecha_anulacion' => now()->format('Y-m-d'),
            ])
            ->assertStatus(200);

        $factura->refresh();
        $this->assertEquals('REGISTRADA', $factura->estado, 'Al reversar el asiento, la Factura de venta debe liberarse y volver a REGISTRADA.');
        $this->assertNull($factura->asiento_pago_id);
    }

    public function test_compensar_factura_venta_con_nota_credito_no_excede_deuda()
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $this->cliente->id,
            'numero_factura' => 'V-CMP-2',
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 84034,
            'monto_iva' => 15966,
            'monto_bruto' => 100000,
            'estado' => 'REGISTRADA',
        ]);

        $nc = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico() + 1,
            'cliente_id' => $this->cliente->id,
            'numero_factura' => 'NC-CMP-2',
            'tipo' => 'VENTA',
            'tipo_documento' => 'NOTA_CREDITO',
            'fecha_emision' => now(),
            'monto_neto' => 42017,
            'monto_iva' => 7983,
            'monto_bruto' => 50000,
            'estado' => 'REGISTRADA',
        ]);

        $this->actingAs($this->usuario)
            ->postJson("/api/clientes/{$this->cliente->id}/cruzar-documentos", [
                'facturas_ids' => [$factura->id],
                'notas_credito_ids' => [$nc->id],
            ])
            ->assertStatus(200);

        $factura->refresh();
        $nc->refresh();
        $this->assertEquals('ABONADA', $factura->estado);
        $this->assertEquals('APLICADA', $nc->estado);
        // La NC no genera anticipo, por lo que no debe crear un asiento de traspaso adicional.
        $this->assertNull($factura->asiento_pago_id);
    }

    public function test_rechaza_compensacion_si_saldo_a_favor_excede_la_deuda()
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $this->cliente->id,
            'numero_factura' => 'V-CMP-3',
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 8403,
            'monto_iva' => 1597,
            'monto_bruto' => 10000,
            'estado' => 'REGISTRADA',
        ]);

        $anticipo = AnticipoCliente::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'monto' => 100000,
            'monto_original' => 100000,
            'saldo_disponible' => 100000,
            'estado' => 'DISPONIBLE',
        ]);

        $this->actingAs($this->usuario)
            ->postJson("/api/clientes/{$this->cliente->id}/cruzar-documentos", [
                'facturas_ids' => [$factura->id],
                'anticipos_ids' => [$anticipo->id],
            ])
            ->assertStatus(422);

        $this->assertEquals('REGISTRADA', $factura->fresh()->estado);
        $this->assertEquals('DISPONIBLE', $anticipo->fresh()->estado);
    }
}
