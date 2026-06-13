<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Services\Provisiones\VacacionesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Tests para VacacionesService: devengo mensual y cálculo proporcional al término.
 */
class VacacionesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private VacacionesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(VacacionesService::class);
    }

    private function contratoVigente(int $empresaId, float $sueldo, string $fechaInicio): Contrato
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '11.111.111-' . rand(0, 9),
            'nombres' => 'Trabajador',
            'apellido_paterno' => 'Vac',
        ]);

        return Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => $fechaInicio,
            'sueldo_base' => $sueldo,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);
    }

    // Fix #1: aniversario calculado con fecha de término, no con now()
    public function test_proporcional_usa_fecha_termino_no_now(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Inicio: 2024-03-01 → aniversario al 2025-03-01
        // Término: 2025-06-01 → 92 días desde el aniversario (mar→jun)
        $contrato = $this->contratoVigente($empresa->id, 600000, '2024-03-01');

        $resultado = $this->service->calcularVacacionesProporcionales($contrato, '2025-06-01');

        // El aniversario correcto es 2025-03-01; días desde ese punto son ~92
        // Si usara now() (hoy ~2026-06-11) el aniversario sería 2026-03-01 y el diff sería negativo
        $this->assertGreaterThan(0, $resultado['dias']);
        $this->assertGreaterThan(0, $resultado['monto']);
        // Días proporcionales esperados ≈ (92/365) × 15 ≈ 3.78
        $this->assertEqualsWithDelta(3.78, $resultado['dias'], 0.5);
    }

    // Fix #6: conversión hábiles → corridos cruzando fin de semana
    public function test_habiles_a_corridos_cruza_fin_de_semana(): void
    {
        // Miércoles 2026-06-10 → contar 3 días hábiles hacia adelante:
        // Jue 11 (1), Vie 12 (2), Sáb 13 (skip), Dom 14 (skip), Lun 15 (3)
        // Desde Jue 11 hasta Lun 15 = 5 días corridos
        $base = Carbon::parse('2026-06-10'); // miércoles
        $corridos = $this->service->habilesACorridos(3, $base);
        $this->assertEquals(5, $corridos);
    }

    public function test_habiles_a_corridos_sin_fin_de_semana(): void
    {
        // Lunes 2026-06-08 → contar 3 hábiles: Mar(1), Mié(2), Jue(3) = 3 corridos
        $base = Carbon::parse('2026-06-08'); // lunes → siguiente: mar
        $corridos = $this->service->habilesACorridos(3, $base);
        $this->assertEquals(3, $corridos);
    }

    public function test_habiles_a_corridos_cero_devuelve_cero(): void
    {
        $base = Carbon::parse('2026-06-10');
        $this->assertEquals(0, $this->service->habilesACorridos(0, $base));
    }

    // Fix #6: el monto de proporcional usa días corridos, no hábiles directos
    public function test_proporcional_monto_usa_dias_corridos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Inicio: 2026-05-01 → término: 2026-06-01 = 31 días desde aniversario
        // Días hábiles proporcionales: (31/365) × 15 ≈ 1.27 → redondeado a 1 hábil
        // 1 hábil desde 2026-06-01 (martes): miércoles 2026-06-03 → 1 corrido (no cruza fds)
        // Remuneración diaria: 900.000/30 = 30.000
        // Monto = 1 corrido × 30.000 = 30.000
        $contrato = $this->contratoVigente($empresa->id, 900000, '2026-05-01');

        $resultado = $this->service->calcularVacacionesProporcionales($contrato, '2026-06-01');

        $remDiaria = 900000 / 30;
        // dias_corridos × remDiaria debe coincidir con monto
        $this->assertEquals(
            round($resultado['dias_corridos'] * $remDiaria, 2),
            $resultado['monto']
        );
        $this->assertGreaterThanOrEqual(0, $resultado['dias_corridos']);
    }

    // Fix #1: término en año/mes diferente a now() → resultado coherente
    public function test_proporcional_fecha_termino_en_ano_pasado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Simulamos término en diciembre 2024 (año distinto a now()=2026)
        // Inicio: 2024-06-01, término: 2024-12-01 → aniversario: 2024-06-01
        // Días desde aniversario: ~183 días
        $contrato = $this->contratoVigente($empresa->id, 750000, '2024-06-01');

        $resultado = $this->service->calcularVacacionesProporcionales($contrato, '2024-12-01');

        // (183/365) × 15 ≈ 7.52 días hábiles → debe ser positivo y razonable
        $this->assertGreaterThan(5.0, $resultado['dias']);
        $this->assertLessThan(10.0, $resultado['dias']);
        $this->assertGreaterThan(0, $resultado['monto']);
    }
}
