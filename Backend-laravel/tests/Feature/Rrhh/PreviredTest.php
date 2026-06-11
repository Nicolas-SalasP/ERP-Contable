<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use App\Domains\Rrhh\Services\Previred\PreviredService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R6 — Generación del archivo previsional para Previred.
 * Valida formato CSV, mapeo de códigos AFP/salud, datos por trabajador
 * y aislamiento multitenant.
 */
class PreviredTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private PreviredService $service;
    private LiquidacionService $liquidacionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(PreviredService::class);
        $this->liquidacionService = app(LiquidacionService::class);
        $this->cargarParametrosLegales();
    }

    private function cargarParametrosLegales(): void
    {
        ParametroPrevisional::create([
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'afp_cotizacion_pct' => 10.0,
            'afp_comisiones_json' => ['Habitat' => 1.27, 'Modelo' => 0.58, 'Capital' => 1.44, 'Uno' => 0.46],
            'afp_sis_pct' => 1.62,
            'tope_imponible_uf' => 90.0,
            'salud_fonasa_pct' => 7.0,
            'afc_indefinido_trabajador_pct' => 0.6,
            'afc_indefinido_empleador_pct' => 2.4,
            'afc_plazo_fijo_trabajador_pct' => 0.0,
            'afc_plazo_fijo_empleador_pct' => 3.0,
            'afc_tope_imponible_uf' => 135.2,
            'imm' => 539000,
            'gratificacion_tope_mensual_factor' => 4.75,
            'cotizacion_adicional_empleador_pct' => 1.0,
            'mutual_cotizacion_basica_pct' => 0.9,
            'asignacion_familiar_tramos_json' => [
                ['hasta_pesos' => 620251, 'monto_por_carga' => 22007],
                ['hasta_pesos' => null, 'monto_por_carga' => 0],
            ],
            'fuente' => 'test',
        ]);

        IndicadorMensual::create([
            'anio' => 2026, 'mes' => 6,
            'uf_valor' => 39850, 'utm_valor' => 71506, 'uta_valor' => 71506 * 12,
        ]);

        foreach ([
            [1, 0.0, 13.5, 0.0, 0.0],
            [2, 13.5, 30.0, 0.04, 0.54],
            [3, 30.0, 50.0, 0.08, 1.74],
            [4, 50.0, 70.0, 0.135, 4.49],
            [5, 70.0, 90.0, 0.23, 11.14],
            [6, 90.0, 120.0, 0.304, 17.80],
            [7, 120.0, 310.0, 0.35, 23.80],
            [8, 310.0, null, 0.40, 39.30],
        ] as [$o, $d, $h, $t, $f]) {
            TablaImpuestoUnico::create([
                'anio' => 2026, 'orden' => $o, 'desde_utm' => $d, 'hasta_utm' => $h,
                'tasa' => $t, 'factor_deduccion_utm' => $f,
            ]);
        }
    }

    private function crearEmpleadoConContrato(
        int $empresaId,
        string $afp = 'Habitat',
        string $tipoSalud = 'FONASA',
        string $tipoContrato = 'INDEFINIDO',
        float $sueldo = 900000
    ): Empleado {
        $rut = '12.' . rand(100, 999) . '.' . rand(100, 999) . '-' . rand(0, 9);
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => $rut,
            'nombres' => 'Juan Carlos',
            'apellido_paterno' => 'González',
            'apellido_materno' => 'Pérez',
            'afp' => $afp,
            'tipo_salud' => $tipoSalud,
        ]);

        Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => $tipoContrato,
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => $sueldo,
            'horas_semana' => 42,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        return $empleado->fresh();
    }

    private function calcularYEmitir(int $empresaId, int $empleadoId): Liquidacion
    {
        $liq = $this->liquidacionService->calcular($empresaId, $empleadoId, 2026, 6);
        return $this->liquidacionService->emitir($empresaId, $liq->id);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_genera_archivo_csv_con_encabezado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $csv = $this->service->generarArchivo($empresa->id, 2026, 6);

        $this->assertIsString($csv);
        $lineas = array_values(array_filter(explode("\r\n", $csv)));
        $this->assertGreaterThanOrEqual(2, count($lineas), 'Debe tener encabezado + 1 fila de datos');

        // Verificar que la primera línea es el encabezado
        $encabezado = $lineas[0];
        $this->assertStringContainsString('RUT', $encabezado);
        $this->assertStringContainsString('AFP_CODIGO', $encabezado);
        $this->assertStringContainsString('PERIODO', $encabezado);
        $this->assertStringContainsString('LIQUIDO_PAGAR', $encabezado);
    }

    public function test_contiene_una_fila_por_trabajador(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        // Crear 3 trabajadores con liquidaciones emitidas
        for ($i = 0; $i < 3; $i++) {
            $emp = $this->crearEmpleadoConContrato($empresa->id);
            $this->calcularYEmitir($empresa->id, $emp->id);
        }

        $csv = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = array_values(array_filter(explode("\r\n", $csv)));
        // 1 encabezado + 3 datos
        $this->assertCount(4, $lineas);
    }

    public function test_codigo_afp_habitat_es_07(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, 'Habitat');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $csv = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = array_values(array_filter(explode("\r\n", $csv)));
        $cols = str_getcsv($lineas[1], ';');

        // Columna AFP_CODIGO (índice 8 = posición 9 en el CSV)
        $this->assertEquals('07', $cols[8], 'Habitat debe mapear al código 07 de Previred');
    }

    public function test_codigo_afp_uno_es_13(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, 'Uno');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $csv = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = array_values(array_filter(explode("\r\n", $csv)));
        $cols = str_getcsv($lineas[1], ';');

        $this->assertEquals('13', $cols[8], 'Uno AFP debe mapear al código 13 de Previred');
    }

    public function test_codigo_salud_fonasa_es_07(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, 'Habitat', 'FONASA');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $csv = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = array_values(array_filter(explode("\r\n", $csv)));
        $cols = str_getcsv($lineas[1], ';');

        // Columna SALUD_CODIGO (índice 12 = posición 13)
        $this->assertEquals('07', $cols[12], 'FONASA debe mapear al código 07 de Previred');
    }

    public function test_periodo_en_formato_aaaamm(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $csv = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = array_values(array_filter(explode("\r\n", $csv)));
        $cols = str_getcsv($lineas[1], ';');

        // Columna PERIODO (índice 6)
        $this->assertEquals('202606', $cols[6], 'El período debe ser AAAAMM');
    }

    public function test_liquido_pagar_es_positivo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, afp: 'Habitat', sueldo: 1200000);
        $liq = $this->calcularYEmitir($empresa->id, $emp->id);

        $csv = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = array_values(array_filter(explode("\r\n", $csv)));
        $cols = str_getcsv($lineas[1], ';');

        // Columna LIQUIDO_PAGAR (última, índice 24)
        $liquido = (int) $cols[24];
        $this->assertGreaterThan(0, $liquido);
        // El líquido en el CSV debe coincidir con la liquidación
        $this->assertEqualsWithDelta((float) $liq->liquido_a_pagar, $liquido, 1.0);
    }

    public function test_tipo_afc_indefinido_es_1(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, tipoContrato: 'INDEFINIDO');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $csv = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = array_values(array_filter(explode("\r\n", $csv)));
        $cols = str_getcsv($lineas[1], ';');

        // Columna TIPO_AFC (índice 16)
        $this->assertEquals('1', $cols[16], 'Contrato indefinido = tipo AFC 1');
    }

    public function test_tipo_afc_plazo_fijo_es_2(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, tipoContrato: 'PLAZO_FIJO');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $csv = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = array_values(array_filter(explode("\r\n", $csv)));
        $cols = str_getcsv($lineas[1], ';');

        $this->assertEquals('2', $cols[16], 'Contrato a plazo fijo = tipo AFC 2');
    }

    public function test_falla_si_no_hay_liquidaciones_emitidas(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->expectException(RrhhException::class);
        $this->service->generarArchivo($empresa->id, 2026, 6);
    }

    public function test_aislamiento_multitenant(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();

        // Empresa A tiene liquidaciones
        $empA = $this->crearEmpleadoConContrato($empresaA->id);
        $this->calcularYEmitir($empresaA->id, $empA->id);

        // Empresa B no tiene nada → debe lanzar excepción
        $this->expectException(RrhhException::class);
        $this->service->generarArchivo($empresaB->id, 2026, 6);
    }

    public function test_endpoint_previred_requiere_autenticacion(): void
    {
        $response = $this->getJson('/api/rrhh/previred/2026/6/archivo');
        $response->assertStatus(401);
    }

    public function test_endpoint_previred_requiere_permiso_ver(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $rolSinPermiso = \App\Domains\Core\Models\Rol::create([
            'nombre' => 'Sin Permiso', 'jerarquia' => 10, 'permisos' => [],
        ]);
        $usuario = $this->crearUsuario($empresa, $rolSinPermiso);

        $response = $this->actingAs($usuario)->getJson('/api/rrhh/previred/2026/6/archivo');
        $response->assertStatus(403);
    }
}
