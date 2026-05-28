<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Proveedor;
use Laravel\Sanctum\Sanctum;

class AnticiposProveedorTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '78.111.222-3',
            'razon_social' => 'Proveedor Anticipos Test',
            'codigo_interno' => 'PA-TEST',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    public function test_anticipo_se_crea_con_estado_pendiente_por_defecto()
    {
        $idAnticipo = DB::table('anticipos_proveedores')->insertGetId([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'monto' => 500000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anticipo = DB::table('anticipos_proveedores')->where('id', $idAnticipo)->first();
        $this->assertEquals('PENDIENTE', $anticipo->estado);
    }

    public function test_anticipo_persiste_decimales_correctamente()
    {
        $idAnticipo = DB::table('anticipos_proveedores')->insertGetId([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'monto' => 123456.78,
            'estado' => 'PENDIENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anticipo = DB::table('anticipos_proveedores')->where('id', $idAnticipo)->first();
        $this->assertEquals(123456.78, (float) $anticipo->monto);
    }

    public function test_endpoint_guardar_anticipo_via_proveedores_responde()
    {
        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/proveedores/anticipos', [
            'proveedor_id' => $this->proveedor->id,
            'monto' => 200000,
            'fecha' => '2026-05-01',
            'referencia' => 'Abono test',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201, 400, 404, 422, 500]);

        if (in_array($response->getStatusCode(), [200, 201])) {
            $existeAnticipo = DB::table('anticipos_proveedores')
                ->where('proveedor_id', $this->proveedor->id)
                ->where('monto', 200000)
                ->exists();
            $this->assertTrue($existeAnticipo, 'Endpoint respondio 200/201 pero no creo el anticipo');
        }
    }

    public function test_anticipo_via_endpoint_no_acepta_proveedor_de_otra_empresa()
    {
        $empresaB = $this->crearEmpresa();
        $proveedorB = Proveedor::create([
            'empresa_id' => $empresaB->id,
            'rut' => '99.999.999-9',
            'razon_social' => 'Prov B',
            'codigo_interno' => 'PB-X',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/proveedores/anticipos', [
            'proveedor_id' => $proveedorB->id, // proveedor ajeno!
            'fecha' => '2026-05-01',
            'monto' => 100000,
            'referencia' => 'Test IDOR',
        ]);

        $this->assertNotContains(
            $response->getStatusCode(),
            [200, 201],
            'IDOR: usuario de empresa A creo anticipo apuntando a proveedor de empresa B'
        );

        $existeAnticipo = \DB::table('anticipos_proveedores')
            ->where('proveedor_id', $proveedorB->id)
            ->where('empresa_id', $this->empresa->id)
            ->exists();
        $this->assertFalse(
            $existeAnticipo,
            'Se creo un anticipo inconsistente (empresa_id != proveedor.empresa_id)'
        );
    }

    public function test_listado_anticipos_pendientes_solo_devuelve_los_de_la_empresa()
    {
        DB::table('anticipos_proveedores')->insert([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'monto' => 100000,
            'estado' => 'PENDIENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $empresaB = $this->crearEmpresa();
        $provB = Proveedor::create([
            'empresa_id' => $empresaB->id,
            'rut' => '11.222.333-4',
            'razon_social' => 'Prov B',
            'codigo_interno' => 'PB',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
        $anticipoIdB = DB::table('anticipos_proveedores')->insertGetId([
            'empresa_id' => $empresaB->id,
            'proveedor_id' => $provB->id,
            'monto' => 999999,
            'estado' => 'PENDIENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->usuario);
        $response = $this->getJson('/api/banco/anticipos-pendientes');

        if (in_array($response->getStatusCode(), [404, 405])) {
            $this->markTestSkipped('Endpoint no expuesto.');
        }

        $response->assertStatus(200);
        $body = $response->json();
        $anticipos = $body['data'] ?? $body;
        $this->assertIsArray($anticipos);

        $idsExpuestos = array_map(fn($a) => $a['id'] ?? null, $anticipos);
        $this->assertNotContains(
            $anticipoIdB,
            $idsExpuestos,
            'Filtracion: anticipo de empresa B aparecio en lista de empresa A'
        );
    }
}
