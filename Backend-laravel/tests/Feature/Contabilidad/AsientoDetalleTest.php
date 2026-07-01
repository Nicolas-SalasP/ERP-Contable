<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use App\Domains\Contabilidad\Models\PlanCuenta;

class AsientoDetalleTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private Empresa $empresaA;
    private User $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresaA = Empresa::create([
            'rut'          => '77.777.777-7',
            'razon_social' => 'Empresa Alfa SpA',
        ]);

        $this->contador = User::create([
            'nombre'                => 'Contador Alfa',
            'email'                 => 'contador@alfa.cl',
            'password'              => bcrypt('123'),
            'empresa_id'            => $this->empresaA->id,
            'rol_id'                => $this->rolContador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
    }

    /**
     * Crea un asiento con dos líneas cuadradas y lo retorna.
     */
    private function crearAsientoConDetalles(): AsientoContable
    {
        $caja = PlanCuenta::create([
            'empresa_id' => $this->empresaA->id,
            'codigo'     => '1001',
            'nombre'     => 'Caja',
            'tipo'       => 'ACTIVO',
            'imputable'  => true,
            'activo'     => true,
        ]);

        $ventas = PlanCuenta::create([
            'empresa_id' => $this->empresaA->id,
            'codigo'     => '4001',
            'nombre'     => 'Ventas',
            'tipo'       => 'INGRESO',
            'imputable'  => true,
            'activo'     => true,
        ]);

        $asiento = AsientoContable::create([
            'empresa_id'         => $this->empresaA->id,
            'usuario_id'         => $this->contador->id,
            'numero_comprobante' => 'C-001',
            'fecha'              => '2024-01-15',
            'glosa'              => 'Venta al contado',
            'tipo_asiento'       => 'traspaso',
            'origen_modulo'      => 'manual',
            'estado'             => 'MAYORIZADO',
        ]);

        DetalleAsiento::create([
            'asiento_id'         => $asiento->id,
            'cuenta_contable'    => $caja->codigo,
            'tipo_operacion'     => 'DEBE',
            'debe'               => 50000,
            'haber'              => 0,
            'descripcion_extensa'=> 'Ingreso efectivo',
        ]);

        DetalleAsiento::create([
            'asiento_id'         => $asiento->id,
            'cuenta_contable'    => $ventas->codigo,
            'tipo_operacion'     => 'HABER',
            'debe'               => 0,
            'haber'              => 50000,
            'descripcion_extensa'=> 'Venta del día',
        ]);

        return $asiento;
    }

    /**
     * GL Drill-down: retorna 200 con estructura completa (cabecera, detalles, totales).
     */
    public function test_retorna_detalle_completo_del_asiento(): void
    {
        $asiento = $this->crearAsientoConDetalles();

        $response = $this->actingAs($this->contador)
            ->getJson("/api/contabilidad/asientos/{$asiento->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'cabecera',
                    'detalles' => [
                        ['id', 'cuenta_contable', 'cuenta_nombre', 'descripcion', 'debe', 'haber'],
                    ],
                    'total_debe',
                    'total_haber',
                ],
            ]);

        $data = $response->json('data');

        $this->assertCount(2, $data['detalles']);
        $this->assertEquals(50000, $data['total_debe']);
        $this->assertEquals(50000, $data['total_haber']);

        // Verifica que el nombre y descripcion de la primera linea lleguen correctos
        $lineaCaja = collect($data['detalles'])->firstWhere('cuenta_contable', '1001');
        $this->assertNotNull($lineaCaja);
        $this->assertEquals('Caja', $lineaCaja['cuenta_nombre']);
        $this->assertEquals('Ingreso efectivo', $lineaCaja['descripcion']);
    }

    /**
     * Aislamiento multitenant: empresa B no puede acceder a asientos de empresa A.
     */
    public function test_empresa_b_no_puede_ver_asiento_de_empresa_a(): void
    {
        $asiento = $this->crearAsientoConDetalles();

        $empresaB = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Empresa Beta SpA']);
        $usuarioB = User::create([
            'nombre'                => 'Contador Beta',
            'email'                 => 'contador@beta.cl',
            'password'              => bcrypt('123'),
            'empresa_id'            => $empresaB->id,
            'rol_id'                => $this->rolContador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        $response = $this->actingAs($usuarioB)
            ->getJson("/api/contabilidad/asientos/{$asiento->id}");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    /**
     * Retorna 404 cuando el asiento no existe.
     */
    public function test_retorna_404_si_asiento_no_existe(): void
    {
        $response = $this->actingAs($this->contador)
            ->getJson('/api/contabilidad/asientos/99999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
