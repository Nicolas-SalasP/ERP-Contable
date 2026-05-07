<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;

class ComercialIntegridadRelacionalTest extends TestCase
{
    use RefreshDatabase;
    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]);

        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
            ['id' => 2, 'nombre' => 'Enviada'],
            ['id' => 3, 'nombre' => 'Aprobada']
        ]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Integridad SpA']);
        $this->usuario = User::create(['nombre' => 'Admin', 'email' => 'a@int.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_inactivar_cliente_con_historial_bloquea_el_hard_delete()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Cliente Viejo', 'estado' => 'ACTIVO']);
        Cotizacion::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id, 'nombre_cliente' => 'Cliente Viejo', 'estado_id' => 1, 'numero_cotizacion' => 'COT-INT', 'subtotal' => 100, 'monto_neto' => 100, 'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now()]);

        // Al intentar eliminar, el sistema DEBE hacer un soft-delete o inactivación, nunca un borrado físico.
        $this->actingAs($this->usuario)->deleteJson("/api/clientes/{$cliente->id}")->assertStatus(200);

        // Verificamos que el cliente SÍ siga en la base de datos, pero inactivo
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'estado' => 'INACTIVO'
        ]);
    }

    public function test_rechaza_crear_factura_para_un_proveedor_inactivo()
    {
        $provInactivo = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Prov Baneado', 'estado' => 'INACTIVO', 'codigo_interno' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id' => $provInactivo->id,
            'numero_factura' => 'F-BANEADA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190
        ]);

        // El sistema no debe permitir que se le compre a un proveedor que fue inhabilitado
        $this->assertNotEquals(201, $response->getStatusCode());
    }

    public function test_proveedor_hereda_moneda_por_defecto_si_no_se_envia()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/proveedores', [
            'rut' => '3.3.3.3-3',
            'razon_social' => 'Prov Moneda'
            // NO enviamos moneda_defecto ni pais_iso
        ]);

        $response->assertStatus(201);
        // Debe heredar CLP por estar en Chile, no debe quedar nulo.
        $this->assertEquals('CLP', $response->json('data.moneda_defecto'));
    }
}