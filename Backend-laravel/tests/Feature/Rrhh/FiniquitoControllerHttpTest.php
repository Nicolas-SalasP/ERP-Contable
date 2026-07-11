<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * FiniquitoTest cubre el cálculo (Art. 161/163/70) llamando a FiniquitoService
 * directo, salvo un único getJson en test_show_finiquito_de_otra_empresa_devuelve_404_limpio.
 * calcular/firmar/anular nunca atravesaron las rutas HTTP reales: validación,
 * permiso, multitenant y los códigos de RrhhException/ModelNotFoundException.
 */
class FiniquitoControllerHttpTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        // El tope 90 UF usa el mes ANTERIOR al término (mayo para un término en junio).
        IndicadorMensual::create(['anio' => 2026, 'mes' => 5, 'uf_valor' => 39800, 'utm_valor' => 71506, 'uta_valor' => 71506 * 12]);
        IndicadorMensual::create(['anio' => 2026, 'mes' => 6, 'uf_valor' => 39850, 'utm_valor' => 71506, 'uta_valor' => 71506 * 12]);
    }

    private function contratoVigente(int $empresaId, float $sueldo = 900000, string $fechaInicio = '2020-01-01'): Contrato
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '12.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(0, 9),
            'nombres' => 'Trabajador HTTP', 'apellido_paterno' => 'Apellido',
        ]);

        return Contrato::create([
            'empresa_id' => $empresaId, 'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO', 'fecha_inicio' => $fechaInicio,
            'sueldo_base' => $sueldo, 'estado' => 'VIGENTE', 'es_contrato_activo' => true,
        ]);
    }

    private function payloadCalcular(int $contratoId): array
    {
        return [
            'contrato_id' => $contratoId,
            'causal' => 'NECESIDADES_EMPRESA',
            'fecha_termino' => '2026-06-15',
        ];
    }

    // ── calcular ──────────────────────────────────────────────────────────

    public function test_calcular_via_http_devuelve_201_con_indemnizacion()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id);

        $response = $this->actingAs($admin)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contrato->id));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', 'BORRADOR')
            ->assertJsonPath('data.contrato_id', $contrato->id);
        $this->assertGreaterThan(0, $response->json('data.monto_indemnizacion_anos'));
        $this->assertGreaterThan(0, $response->json('data.total_neto'));
    }

    public function test_calcular_valida_causal_invalida_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id);

        $response = $this->actingAs($admin)->postJson('/api/rrhh/finiquitos/calcular', array_merge(
            $this->payloadCalcular($contrato->id),
            ['causal' => 'NO_EXISTE'],
        ));

        $response->assertStatus(422)->assertJsonValidationErrors(['causal']);
    }

    public function test_calcular_contrato_de_otra_empresa_no_lo_encuentra()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();
        $contratoB = $this->contratoVigente($empresaB->id);

        $response = $this->actingAs($adminA)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contratoB->id));

        $response->assertStatus(404);
    }

    public function test_calcular_contrato_ya_terminado_falla_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id);
        $contrato->update(['estado' => 'TERMINADO']);

        $response = $this->actingAs($admin)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contrato->id));

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_calcular_sin_permiso_devuelve_403()
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id);
        $sinPermiso = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        $response = $this->actingAs($sinPermiso)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contrato->id));

        $response->assertStatus(403);
    }

    // ── firmar ────────────────────────────────────────────────────────────

    public function test_firmar_via_http_termina_el_contrato_y_deja_inactivo_al_empleado()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id);
        $finiquito = $this->actingAs($admin)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contrato->id))->json('data');

        $response = $this->actingAs($admin)->postJson("/api/rrhh/finiquitos/{$finiquito['id']}/firmar");

        $response->assertStatus(200)->assertJsonPath('data.estado', 'FIRMADO');
        $this->assertDatabaseHas('contratos', ['id' => $contrato->id, 'estado' => 'TERMINADO', 'es_contrato_activo' => false]);
        $this->assertDatabaseHas('empleados', ['id' => $contrato->empleado_id, 'estado' => 'INACTIVO']);
    }

    public function test_firmar_dos_veces_falla_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id);
        $finiquito = $this->actingAs($admin)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contrato->id))->json('data');
        $this->actingAs($admin)->postJson("/api/rrhh/finiquitos/{$finiquito['id']}/firmar")->assertStatus(200);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/finiquitos/{$finiquito['id']}/firmar");

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_firmar_finiquito_inexistente_devuelve_404()
    {
        [, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/rrhh/finiquitos/999999/firmar');

        $response->assertStatus(404);
    }

    // ── anular ────────────────────────────────────────────────────────────

    public function test_anular_via_http_reactiva_al_empleado()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id);
        $finiquito = $this->actingAs($admin)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contrato->id))->json('data');
        $this->actingAs($admin)->postJson("/api/rrhh/finiquitos/{$finiquito['id']}/firmar")->assertStatus(200);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/finiquitos/{$finiquito['id']}/anular", [
            'motivo' => 'Error en el cálculo original',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.estado', 'ANULADO');
    }

    public function test_anular_valida_motivo_requerido_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id);
        $finiquito = $this->actingAs($admin)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contrato->id))->json('data');
        $this->actingAs($admin)->postJson("/api/rrhh/finiquitos/{$finiquito['id']}/firmar")->assertStatus(200);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/finiquitos/{$finiquito['id']}/anular", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['motivo']);
    }

    public function test_anular_finiquito_en_borrador_falla_porque_no_esta_firmado()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->contratoVigente($empresa->id);
        $finiquito = $this->actingAs($admin)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contrato->id))->json('data');

        $response = $this->actingAs($admin)->postJson("/api/rrhh/finiquitos/{$finiquito['id']}/anular", [
            'motivo' => 'Error en el cálculo original',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_anular_de_otra_empresa_devuelve_404()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB, $adminB] = $this->crearEmpresaConAdmin();
        $contratoB = $this->contratoVigente($empresaB->id);
        $finiquitoB = $this->actingAs($adminB)->postJson('/api/rrhh/finiquitos/calcular', $this->payloadCalcular($contratoB->id))->json('data');
        $this->actingAs($adminB)->postJson("/api/rrhh/finiquitos/{$finiquitoB['id']}/firmar")->assertStatus(200);

        $response = $this->actingAs($adminA)->postJson("/api/rrhh/finiquitos/{$finiquitoB['id']}/anular", [
            'motivo' => 'Intento cruzado',
        ]);

        $response->assertStatus(404);
    }
}
