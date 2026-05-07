<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\EstadoCotizacion;

class ComercialSeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected $empresaA;
    protected $usuarioAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]);

        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'], ['id' => 2, 'nombre' => 'Enviada'], ['id' => 3, 'nombre' => 'Aprobada']
        ]);

        $this->empresaA = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Sur SpA', 'regimen_tributario' => '14_D3']);
        $this->usuarioAdmin = User::create(['nombre' => 'Admin', 'email' => 'admin@sur.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresaA->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_rutas_comerciales_rechazan_usuarios_no_autenticados()
    {
        $this->getJson('/api/clientes')->assertStatus(401);
        $this->getJson('/api/proveedores')->assertStatus(401);
        $this->getJson('/api/cotizaciones')->assertStatus(401);
        $this->getJson('/api/facturas')->assertStatus(401);
    }

    public function test_aislamiento_multitenant_en_clientes_y_proveedores()
    {
        $empresaB = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Competencia SpA']);
        $hacker = User::create(['nombre' => 'Hacker', 'email' => 'hacker@comp.cl', 'password' => bcrypt('123'), 'empresa_id' => $empresaB->id, 'rol_id' => 1, 'estado_suscripcion_id' => 1]);

        Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '11.111.111-1', 'razon_social' => 'Cliente Oro', 'estado' => 'ACTIVO']);
        Proveedor::create(['empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-1', 'rut' => '22.222.222-2', 'razon_social' => 'Prov', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $this->assertEmpty($this->actingAs($hacker)->getJson('/api/clientes')->json('data'));
        $this->assertEmpty($this->actingAs($hacker)->getJson('/api/proveedores')->json('data'));
    }

    public function test_no_se_puede_ver_ficha_de_proveedor_de_otra_empresa()
    {
        $empresaB = Empresa::create(['rut' => '99.111.111-1', 'razon_social' => 'Otra SpA']);
        $provAjeno = Proveedor::create(['empresa_id' => $empresaB->id, 'codigo_interno' => 'PR-AJENO', 'rut' => '12.345.678-9', 'razon_social' => 'Prov Ajeno', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $this->actingAs($this->usuarioAdmin)->getJson("/api/proveedores/{$provAjeno->id}/ficha")->assertStatus(404);
    }

    public function test_idor_no_se_puede_ver_factura_de_otra_empresa()
    {
        $empresaB = Empresa::create(['rut' => '22.222.222-2', 'razon_social' => 'Empresa Hacker']);
        $prov = Proveedor::create(['empresa_id' => $empresaB->id, 'codigo_interno' => 'PR-A', 'rut' => '33.333.333-3', 'razon_social' => 'Prov A', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $facturaAjena = Factura::create(['empresa_id' => $empresaB->id, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-SECRETA', 'codigo_unico' => 99, 'fecha_emision' => now(), 'monto_bruto' => 119, 'monto_neto' => 100, 'monto_iva' => 19, 'tipo' => 'COMPRA']);

        $this->actingAs($this->usuarioAdmin)->getJson("/api/facturas/{$facturaAjena->id}")->assertStatus(404);
    }

    public function test_idor_rechaza_usar_proveedor_perteneciente_a_otra_empresa_para_crear_factura()
    {
        $empresaB = Empresa::create(['rut' => '33.888.888-8', 'razon_social' => 'Otra Empresa']);
        $provAjeno = Proveedor::create(['empresa_id' => $empresaB->id, 'codigo_interno' => 'PR-HACK', 'rut' => '44.444.444-4', 'razon_social' => 'Prov Ajeno', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'proveedor_id' => $provAjeno->id,
            'numero_factura' => 'F-HACK',
            'tipo_documento' => 'COMPRA',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 100,
            'monto_iva' => 19,
            'monto_bruto' => 119
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [404, 422]));
    }

    public function test_capa8_bloquea_descarga_de_pdf_de_cotizacion_ajena()
    {
        $empresaB = Empresa::create(['rut' => '7.7.7.7-7', 'razon_social' => 'Empresa Hacker']);
        $cliente = Cliente::create(['empresa_id' => $empresaB->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Cliente', 'estado' => 'ACTIVO']);
        $cotiA = Cotizacion::create(['empresa_id' => $empresaB->id, 'cliente_id' => $cliente->id, 'nombre_cliente' => $cliente->razon_social, 'estado_id' => 1, 'numero_cotizacion' => 'COT-A', 'subtotal' => 100, 'monto_neto' => 100, 'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now(), 'fecha_validez' => now()]);

        $this->actingAs($this->usuarioAdmin)->getJson("/api/cotizaciones/{$cotiA->id}/pdf")->assertStatus(404);
    }
}