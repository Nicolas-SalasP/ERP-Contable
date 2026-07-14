<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\AnticipoCliente;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cobertura HTTP de AnticipoClienteController: la logica de negocio ya estaba
 * probada en ComercialAnticiposClienteTest, pero llamando al service
 * directamente -- la capa de ruta/validacion/permisos/forma de respuesta
 * nunca se ejercitaba (controller en 0% de coverage).
 */
class AnticipoClienteControllerTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected $empresa;

    protected $usuario;

    protected $cliente;

    protected $prov;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = $this->crearEmpresa(['rut' => '88.888.888-8', 'razon_social' => 'Anticipos HTTP SpA']);
        $this->usuario = $this->crearUsuario($this->empresa, $this->rolSuperAdmin, [
            'nombre' => 'Tesorero HTTP',
            'email' => 't@anthttp.cl',
        ]);
        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '2.2.2.2-2',
            'razon_social' => 'Cliente HTTP',
        ]);
        $this->prov = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => $this->cliente->rut,
            'razon_social' => $this->cliente->razon_social,
            'codigo_interno' => 'CLI-'.$this->cliente->id,
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    public function test_store_registra_anticipo_via_api()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/anticipos-clientes', [
            'cliente_id' => $this->cliente->id,
            'monto' => 250000,
            'referencia' => 'Anticipo test HTTP',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.saldo_disponible', 250000);
        $this->assertDatabaseHas('anticipos_clientes', [
            'cliente_id' => $this->cliente->id,
            'empresa_id' => $this->empresa->id,
            'monto_original' => 250000,
            'estado' => 'DISPONIBLE',
        ]);
    }

    public function test_store_rechaza_monto_negativo_con_422()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/anticipos-clientes', [
            'cliente_id' => $this->cliente->id,
            'monto' => -100,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['monto']);
    }

    public function test_store_rechaza_sin_autenticacion_401()
    {
        $this->postJson('/api/anticipos-clientes', [
            'cliente_id' => $this->cliente->id,
            'monto' => 100000,
        ])->assertStatus(401);
    }

    public function test_index_lista_solo_anticipos_de_la_empresa_activa()
    {
        AnticipoCliente::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $this->cliente->id,
            'monto' => 50000, 'monto_original' => 50000, 'saldo_disponible' => 50000, 'estado' => 'DISPONIBLE',
        ]);

        $otraEmpresa = $this->crearEmpresa(['rut' => '99.999.999-9', 'razon_social' => 'Rival Anticipos']);
        $clienteRival = Cliente::create(['empresa_id' => $otraEmpresa->id, 'rut' => '3.3.3.3-3', 'razon_social' => 'Cliente Rival']);
        AnticipoCliente::create([
            'empresa_id' => $otraEmpresa->id, 'cliente_id' => $clienteRival->id,
            'monto' => 999999, 'monto_original' => 999999, 'saldo_disponible' => 999999, 'estado' => 'DISPONIBLE',
        ]);

        $response = $this->actingAs($this->usuario)->getJson('/api/anticipos-clientes');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(50000, (int) $data[0]['saldo_disponible']);
    }

    public function test_index_filtra_por_cliente_id()
    {
        $otroCliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '4.4.4.4-4', 'razon_social' => 'Otro Cliente']);
        AnticipoCliente::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $this->cliente->id,
            'monto' => 10000, 'monto_original' => 10000, 'saldo_disponible' => 10000, 'estado' => 'DISPONIBLE',
        ]);
        AnticipoCliente::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $otroCliente->id,
            'monto' => 20000, 'monto_original' => 20000, 'saldo_disponible' => 20000, 'estado' => 'DISPONIBLE',
        ]);

        $response = $this->actingAs($this->usuario)->getJson("/api/anticipos-clientes?cliente_id={$otroCliente->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($otroCliente->id, $data[0]['cliente_id']);
    }

    public function test_aplicar_via_api_disminuye_el_saldo_del_anticipo()
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => $this->prov->id,
            'numero_factura' => 'FV-HTTP-1',
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => now(),
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
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

        $response = $this->actingAs($this->usuario)->postJson("/api/anticipos-clientes/{$anticipo->id}/aplicar", [
            'factura_id' => $factura->id,
            'monto_a_aplicar' => 40000,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(60000, (float) $anticipo->fresh()->saldo_disponible);
    }

    public function test_aplicar_rechaza_sin_factura_id_con_422()
    {
        $anticipo = AnticipoCliente::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $this->cliente->id,
            'monto' => 100000, 'monto_original' => 100000, 'saldo_disponible' => 100000, 'estado' => 'DISPONIBLE',
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/anticipos-clientes/{$anticipo->id}/aplicar", [
            'monto_a_aplicar' => 40000,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['factura_id']);
    }
}
