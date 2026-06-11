<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Validaciones del endpoint POST /rrhh/empleados/{id}/contratos.
 * Cubre: fecha_termino requerida en PLAZO_FIJO/POR_OBRA, max horas_semana 44.
 */
class ContratoValidacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function crearEmpleado(int $empresaId): Empleado
    {
        return Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '12.' . rand(100, 999) . '.' . rand(100, 999) . '-' . rand(0, 9),
            'nombres' => 'Test',
            'apellido_paterno' => 'Empleado',
            'afp' => 'Habitat',
            'tipo_salud' => 'FONASA',
        ]);
    }

    private function payloadBase(array $override = []): array
    {
        return array_merge([
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2026-01-01',
            'sueldo_base' => 700000,
        ], $override);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_plazo_fijo_exige_fecha_termino(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $response = $this->actingAs($admin)->postJson(
            "/api/rrhh/empleados/{$empleado->id}/contratos",
            $this->payloadBase(['tipo' => 'PLAZO_FIJO'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_termino']);
    }

    public function test_por_obra_exige_fecha_termino(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $response = $this->actingAs($admin)->postJson(
            "/api/rrhh/empleados/{$empleado->id}/contratos",
            $this->payloadBase(['tipo' => 'POR_OBRA'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_termino']);
    }

    public function test_indefinido_no_exige_fecha_termino(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $response = $this->actingAs($admin)->postJson(
            "/api/rrhh/empleados/{$empleado->id}/contratos",
            $this->payloadBase(['tipo' => 'INDEFINIDO'])
        );

        // Puede fallar por otro motivo (service) pero NO por fecha_termino
        $this->assertNotContains(
            'fecha_termino',
            array_keys($response->json('errors') ?? [])
        );
    }

    public function test_horas_semana_maximo_44(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $response = $this->actingAs($admin)->postJson(
            "/api/rrhh/empleados/{$empleado->id}/contratos",
            $this->payloadBase(['horas_semana' => 45])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['horas_semana']);
    }

    public function test_horas_semana_44_es_valido(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $response = $this->actingAs($admin)->postJson(
            "/api/rrhh/empleados/{$empleado->id}/contratos",
            $this->payloadBase(['horas_semana' => 44])
        );

        // No debe fallar por horas_semana
        $this->assertNotContains(
            'horas_semana',
            array_keys($response->json('errors') ?? [])
        );
    }

    public function test_horas_semana_60_ya_no_es_valido(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $response = $this->actingAs($admin)->postJson(
            "/api/rrhh/empleados/{$empleado->id}/contratos",
            $this->payloadBase(['horas_semana' => 60])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['horas_semana']);
    }

    public function test_plazo_fijo_con_fecha_termino_es_valido(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->crearEmpleado($empresa->id);

        $response = $this->actingAs($admin)->postJson(
            "/api/rrhh/empleados/{$empleado->id}/contratos",
            $this->payloadBase([
                'tipo' => 'PLAZO_FIJO',
                'fecha_termino' => '2026-12-31',
            ])
        );

        // No debe haber error de fecha_termino
        $this->assertNotContains(
            'fecha_termino',
            array_keys($response->json('errors') ?? [])
        );
    }
}
