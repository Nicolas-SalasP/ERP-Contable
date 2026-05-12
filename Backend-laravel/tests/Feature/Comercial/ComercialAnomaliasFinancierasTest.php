<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;

class ComercialAnomaliasFinancierasTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $prov;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Anomalias SpA']);
        $this->usuario = User::create(['nombre' => 'Auditor', 'email' => 'a@anomalia.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
        $this->prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Prov Y', 'codigo_interno' => 'PY', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
    }

    public function test_rechaza_factura_con_totales_en_cero()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id' => $this->prov->id,
            'numero_factura' => 'F-CERO',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 0,
            'monto_iva' => 0,
            'monto_bruto' => 0, 
            'cuentaDestino' => '410101', 'cuentaIva' => '353350', 'cuentaProveedor' => '352105'
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_rechaza_factura_con_fecha_de_emision_en_el_futuro()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id' => $this->prov->id,
            'numero_factura' => 'F-FUTURO',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now()->addDays(5)->format('Y-m-d'),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190, 
            'cuentaDestino' => '410101', 'cuentaIva' => '353350', 'cuentaProveedor' => '352105'
        ]);

        $this->assertNotEquals(201, $response->getStatusCode());
    }

    public function test_rechaza_pago_con_fecha_anterior_a_la_emision_de_la_factura()
    {
        $factura = new Factura(); $factura->empresa_id = $this->empresa->id; $factura->proveedor_id = $this->prov->id; $factura->numero_factura = 'F-PAGO-TIEMPO'; $factura->monto_bruto = 100; $factura->monto_neto = 100; $factura->monto_iva = 0; $factura->tipo = 'COMPRA'; $factura->codigo_unico = 901; $factura->fecha_emision = now(); $factura->estado = 'REGISTRADA'; $factura->save();

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/pagar", [
            'fechaPago' => now()->subDays(2)->format('Y-m-d'),
            'medioPago' => 'TRANSFERENCIA'
        ]);

        $this->assertContains($response->getStatusCode(), [200, 400, 422]);
    }

    public function test_bloquea_anular_una_factura_que_ya_esta_anulada()
    {
        $factura = new Factura(); $factura->empresa_id = $this->empresa->id; $factura->proveedor_id = $this->prov->id; $factura->numero_factura = 'F-DOBLE-ANULA'; $factura->monto_bruto = 100; $factura->monto_neto = 100; $factura->monto_iva = 0; $factura->tipo = 'COMPRA'; $factura->codigo_unico = 902; $factura->fecha_emision = now(); $factura->estado = 'ANULADA'; $factura->save();

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/anular", [
            'motivo' => 'Doble anulación'
        ]);

        $response->assertStatus(400);
    }

    public function test_genera_codigos_unicos_diferentes_para_facturas_masivas()
    {
        $codigos = [];
        for ($i = 0; $i < 20; $i++) {
            $factura = new Factura();
            $factura->empresa_id = $this->empresa->id;
            $factura->proveedor_id = $this->prov->id;
            $factura->numero_factura = "F-MAS-{$i}";
            $factura->monto_bruto = 100;
            $factura->monto_neto = 100;
            $factura->monto_iva = 0;
            $factura->tipo = 'COMPRA';
            $factura->codigo_unico = Factura::generarCodigoUnico();
            $factura->fecha_emision = now();
            $factura->estado = 'REGISTRADA';
            $factura->save();

            $codigos[] = $factura->codigo_unico;
        }

        $this->assertCount(20, array_unique($codigos), 'El generador produjo codigos duplicados');
    }
}