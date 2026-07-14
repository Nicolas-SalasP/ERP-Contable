<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * ContratoValidacionTest cubre store() vía HTTP. show()/terminar()/agregarHaber()
 * nunca se ejercitaron por esa ruta: multitenant, transición de estado y el
 * efecto colateral de terminar() sobre Empleado.estado.
 */
class ContratoControllerHttpTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function empleadoConContrato(int $empresaId, string $estado = 'VIGENTE'): Contrato
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '12.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(0, 9),
            'nombres' => 'Trabajador HTTP', 'apellido_paterno' => 'Apellido',
        ]);

        return Contrato::create([
            'empresa_id' => $empresaId, 'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO', 'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 700000, 'estado' => $estado, 'es_contrato_activo' => $estado === 'VIGENTE',
        ]);
    }

    // ── show ──────────────────────────────────────────────────────────────

    public function test_show_de_contrato_de_otra_empresa_devuelve_404()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();
        $contratoB = $this->empleadoConContrato($empresaB->id);

        $response = $this->actingAs($adminA)->getJson("/api/rrhh/contratos/{$contratoB->id}");

        $response->assertStatus(404);
    }

    // ── terminar ──────────────────────────────────────────────────────────

    public function test_terminar_via_http_deja_inactivo_al_empleado()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->empleadoConContrato($empresa->id);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/contratos/{$contrato->id}/terminar", [
            'causal_termino' => 'RENUNCIA',
            'fecha_termino_real' => '2026-06-30',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.estado', 'TERMINADO')
            ->assertJsonPath('data.causal_termino', 'RENUNCIA');
        $this->assertDatabaseHas('contratos', ['id' => $contrato->id, 'estado' => 'TERMINADO', 'es_contrato_activo' => false]);
        $this->assertDatabaseHas('empleados', ['id' => $contrato->empleado_id, 'estado' => 'INACTIVO']);
    }

    public function test_terminar_valida_causal_requerida_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->empleadoConContrato($empresa->id);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/contratos/{$contrato->id}/terminar", [
            'fecha_termino_real' => '2026-06-30',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['causal_termino']);
    }

    public function test_terminar_contrato_ya_terminado_falla_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->empleadoConContrato($empresa->id, 'TERMINADO');

        $response = $this->actingAs($admin)->postJson("/api/rrhh/contratos/{$contrato->id}/terminar", [
            'causal_termino' => 'RENUNCIA',
            'fecha_termino_real' => '2026-06-30',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_terminar_de_otra_empresa_devuelve_404_y_no_lo_toca()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();
        $contratoB = $this->empleadoConContrato($empresaB->id);

        $response = $this->actingAs($adminA)->postJson("/api/rrhh/contratos/{$contratoB->id}/terminar", [
            'causal_termino' => 'RENUNCIA',
            'fecha_termino_real' => '2026-06-30',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('contratos', ['id' => $contratoB->id, 'estado' => 'VIGENTE']);
    }

    public function test_terminar_sin_permiso_devuelve_403()
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $contrato = $this->empleadoConContrato($empresa->id);
        $sinPermiso = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        $response = $this->actingAs($sinPermiso)->postJson("/api/rrhh/contratos/{$contrato->id}/terminar", [
            'causal_termino' => 'RENUNCIA',
            'fecha_termino_real' => '2026-06-30',
        ]);

        $response->assertStatus(403);
    }

    // ── agregarHaber ──────────────────────────────────────────────────────

    private function payloadHaber(array $override = []): array
    {
        return array_merge([
            'nombre' => 'Bono de colación',
            'tipo' => 'HABER_NO_IMPONIBLE',
            'modalidad_valor' => 'MONTO_FIJO',
            'monto' => 30000,
            'vigente_desde' => '2026-01-01',
        ], $override);
    }

    public function test_agregar_haber_via_http_devuelve_201()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->empleadoConContrato($empresa->id);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/contratos/{$contrato->id}/haberes", $this->payloadHaber());

        $response->assertStatus(201)->assertJsonPath('data.nombre', 'Bono de colación');
        $this->assertDatabaseHas('haber_descuento_contratos', ['contrato_id' => $contrato->id, 'nombre' => 'Bono de colación']);
    }

    public function test_agregar_haber_en_contrato_terminado_falla_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->empleadoConContrato($empresa->id, 'TERMINADO');

        $response = $this->actingAs($admin)->postJson("/api/rrhh/contratos/{$contrato->id}/haberes", $this->payloadHaber());

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_agregar_haber_valida_tipo_requerido_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $contrato = $this->empleadoConContrato($empresa->id);

        $response = $this->actingAs($admin)->postJson(
            "/api/rrhh/contratos/{$contrato->id}/haberes",
            $this->payloadHaber(['tipo' => null])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['tipo']);
    }

    public function test_agregar_haber_de_otra_empresa_devuelve_404()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();
        $contratoB = $this->empleadoConContrato($empresaB->id);

        $response = $this->actingAs($adminA)->postJson("/api/rrhh/contratos/{$contratoB->id}/haberes", $this->payloadHaber());

        $response->assertStatus(404);
    }
}
