<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use App\Domains\Contabilidad\Models\PeriodoContable;
use App\Domains\Contabilidad\Models\PlanCuenta;

class ReclasificarPeriodoCerradoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $proveedor;
    protected $cuentaGasto;
    protected $cuentaIva;
    protected $cuentaProveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '11.111.111-1',
            'razon_social' => 'Proveedor Test SA',
            'codigo_interno' => 'PT01',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->cuentaGasto = PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '410001',
            'nombre' => 'Gastos Generales',
            'tipo' => 'GASTO',
        ]);

        $this->cuentaIva = PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '353350',
            'nombre' => 'IVA Crédito Fiscal',
            'tipo' => 'ACTIVO',
        ]);

        $this->cuentaProveedor = PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '352105',
            'nombre' => 'Cuentas por Pagar Proveedores',
            'tipo' => 'PASIVO',
        ]);
    }

    private function crearFacturaConAsiento(string $fecha): array
    {
        $asiento = AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => $fecha,
            'glosa' => 'Centralización Automática Factura Compra N° 1001',
            'tipo_asiento' => 'traspaso',
            'numero_comprobante' => 'FAC-TEST-' . uniqid(),
            'estado' => 'MAYORIZADO',
        ]);

        $detalle = DetalleAsiento::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable' => $this->cuentaGasto->codigo,
            'debe' => 100000,
            'haber' => 0,
            'descripcion_extensa' => 'Gasto Factura N° 1001',
        ]);

        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => '1001-' . uniqid(),
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'codigo_unico' => rand(1000, 9999),
            'fecha_emision' => $fecha,
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
            'comprobante_contable' => $asiento->numero_comprobante,
        ]);

        return [$factura, $asiento, $detalle];
    }

    public function test_reclasificar_hacia_periodo_cerrado_devuelve_error()
    {
        // Factura con asiento en enero 2025 (mes abierto)
        [$factura, $asiento, $detalle] = $this->crearFacturaConAsiento('2025-01-15');

        // Cerrar febrero 2025 (mes destino)
        PeriodoContable::create([
            'empresa_id' => $this->empresa->id,
            'anio' => 2025,
            'mes' => 2,
            'estado' => PeriodoContable::ESTADO_CERRADO,
            'cerrado_por' => $this->usuario->id,
            'cerrado_at' => now(),
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/reclasificar", [
            'fecha' => '2025-02-10',
            'glosa' => 'Reclasificación a febrero',
            'cambios' => [$detalle->id => $this->cuentaIva->codigo],
        ]);

        $this->assertContains($response->getStatusCode(), [400, 409]);
        $response->assertJsonFragment(['success' => false]);
        $this->assertStringContainsStringIgnoringCase('cerrado', $response->json('message') ?? '');
    }

    public function test_reclasificar_desde_periodo_cerrado_devuelve_error()
    {
        // Crear el asiento y la factura mientras enero 2025 está abierto
        [$factura, $asiento, $detalle] = $this->crearFacturaConAsiento('2025-01-15');

        // Cerrar enero 2025 después de haber creado el asiento
        PeriodoContable::create([
            'empresa_id' => $this->empresa->id,
            'anio' => 2025,
            'mes' => 1,
            'estado' => PeriodoContable::ESTADO_CERRADO,
            'cerrado_por' => $this->usuario->id,
            'cerrado_at' => now(),
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/reclasificar", [
            'fecha' => '2025-02-10',
            'glosa' => 'Reclasificación intentada desde mes cerrado',
            'cambios' => [$detalle->id => $this->cuentaIva->codigo],
        ]);

        $this->assertContains($response->getStatusCode(), [400, 409]);
        $response->assertJsonFragment(['success' => false]);
        $this->assertStringContainsStringIgnoringCase('cerrado', $response->json('message') ?? '');
    }

    public function test_reclasificar_con_ambos_periodos_abiertos_tiene_exito()
    {
        // Sin períodos cerrados → debe funcionar sin error
        [$factura, $asiento, $detalle] = $this->crearFacturaConAsiento('2025-01-15');

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/reclasificar", [
            'fecha' => '2025-02-10',
            'glosa' => 'Reclasificación válida',
            'cambios' => [$detalle->id => $this->cuentaIva->codigo],
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
    }
}
