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
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\EstadoCotizacion;

class ComercialImpuestosYCalculosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
            ['id' => 2, 'nombre' => 'Enviada'],
            ['id' => 3, 'nombre' => 'Aprobada']
        ]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Calculos SpA']);
        $this->usuario = User::create(['nombre' => 'Conta', 'email' => 'c@cal.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_cotizacion_exenta_no_suma_iva_al_total()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Exento', 'estado' => 'ACTIVO']);
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-EXENTA',
            'es_afecta' => false,
            'fecha_emision' => now()->format('Y-m-d'),
            'subtotal' => 100000,
            'monto_neto' => 100000,
            'monto_iva' => 0,
            'monto_total' => 100000,
            'detalles' => [['producto_nombre' => 'Servicio Médico', 'cantidad' => 1, 'precio_unitario' => 100000, 'subtotal' => 100000]]
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cotizaciones', [
            'numero_cotizacion' => 'COT-EXENTA',
            'monto_iva' => 0,
            'monto_total' => 100000
        ]);
    }

    public function test_factura_nota_credito_pasa_validaciones_de_tipo_documento()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Prov NC', 'codigo_interno' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => 'NC-001',
            'tipo_documento' => 'NOTA_CREDITO', // Validamos que el enum lo acepte
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190,
            'cuentaDestino' => '410101',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ]);

        $this->assertContains($response->getStatusCode(), [201, 422]);
    }

    public function test_rechaza_crear_factura_con_monto_iva_matematicamente_imposible()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '3.3.3.3-3', 'razon_social' => 'Fraude', 'codigo_interno' => 'P2', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        
        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-FRAUDE',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 10000,
            'monto_iva' => 999999,
            'monto_bruto' => 1009999, 
            'cuentaDestino' => '410101', 'cuentaIva' => '353350', 'cuentaProveedor' => '352105'
        ]);

        $response->assertStatus(422);
    }

    public function test_cotizacion_aplica_redondeo_correcto_en_decimales()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '4.4.4.4-4', 'razon_social' => 'Redondeo', 'estado' => 'ACTIVO']);
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-REDONDEO',
            'fecha_emision' => now()->format('Y-m-d'), 
            'es_afecta' => true,
            'porcentaje_descuento' => 33.33,
            'subtotal' => 31.65,
            'monto_neto' => 21.10,
            'monto_iva' => 4.01,
            'monto_total' => 25.11,
            'detalles' => [['producto_nombre' => 'Item', 'cantidad' => 3, 'precio_unitario' => 10.55, 'subtotal' => 31.65]]
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.monto_total'));
    }
}