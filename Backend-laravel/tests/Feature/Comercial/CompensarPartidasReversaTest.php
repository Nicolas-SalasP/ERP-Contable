<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\AnticipoProveedor;
use App\Domains\Contabilidad\Models\PlanCuenta;

/**
 * Regresión: ProveedorService::compensarPartidas no guardaba asiento_pago_id en la
 * Factura compensada (a diferencia de ConciliacionService::procesarPagoFacturas).
 * Al reversar el asiento de traspaso vía /api/anulacion/anular, AnulacionService no
 * encontraba la Factura y esta quedaba PAGADA/ABONADA huérfana para siempre.
 */
class CompensarPartidasReversaTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = $this->crearEmpresa([
            'rut' => '76.111.111-1',
            'razon_social' => 'Compensación SpA',
        ]);
        $this->usuario = $this->crearUsuario($this->empresa, $this->rolSuperAdmin, [
            'nombre' => 'Contador',
            'email' => 'c@compensacion.cl',
        ]);
        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '2.2.2.2-2',
            'razon_social' => 'Proveedor Compensacion',
            'codigo_interno' => 'P-CMP',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        // Cuentas usadas por el asiento de traspaso de compensarPartidas.
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '352105',
            'nombre' => 'Proveedores', 'tipo' => 'PASIVO', 'imputable' => true, 'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '110205',
            'nombre' => 'Anticipos a Proveedores', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true,
        ]);
    }

    public function test_compensar_partidas_guarda_asiento_pago_id_y_reversa_libera_la_factura()
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'F-CMP-1',
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 84034,
            'monto_iva' => 15966,
            'monto_bruto' => 100000,
            'estado' => 'REGISTRADA',
        ]);

        $anticipo = AnticipoProveedor::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'monto' => 100000,
            'saldo_disponible' => 100000,
            'estado' => 'PENDIENTE',
        ]);

        $this->actingAs($this->usuario)
            ->postJson("/api/proveedores/{$this->proveedor->id}/cruzar-documentos", [
                'facturas_ids' => [$factura->id],
                'anticipos_ids' => [$anticipo->id],
            ])
            ->assertStatus(200);

        $factura->refresh();
        $this->assertEquals('PAGADA', $factura->estado);
        $this->assertNotNull($factura->asiento_pago_id, 'La Factura compensada debe quedar vinculada al asiento de traspaso.');

        // Reversar el asiento de compensación vía el endpoint de anulación.
        $this->actingAs($this->usuario)
            ->postJson('/api/anulacion/anular', [
                'tipo_documento' => 'ASIENTO',
                'documento_id' => $factura->asiento_pago_id,
                'motivo' => 'Reverso de prueba de compensación',
                'fecha_anulacion' => now()->format('Y-m-d'),
            ])
            ->assertStatus(200);

        $factura->refresh();
        $this->assertEquals('REGISTRADA', $factura->estado, 'Al reversar el asiento, la Factura debe liberarse y volver a REGISTRADA.');
        $this->assertNull($factura->asiento_pago_id);
    }
}
