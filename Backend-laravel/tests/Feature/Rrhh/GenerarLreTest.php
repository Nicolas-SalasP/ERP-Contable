<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use App\Domains\Rrhh\Models\LreEnvio;
use App\Domains\Rrhh\Models\Mutualidad;
use App\Domains\Rrhh\Services\Lre\GenerarLreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class GenerarLreTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    private GenerarLreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Storage::fake('sii_xml');
        $this->service = app(GenerarLreService::class);
    }

    // ------------------------------------------------------------------
    // Helpers de setup
    // ------------------------------------------------------------------

    private function crearEmpleadoConLiquidacion(int $empresaId, string $rut, array $overridesLiq = []): array
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => $rut,
            'nombres' => 'Juan',
            'apellido_paterno' => 'Pérez',
            'afp' => 'Capital',
            'tipo_salud' => 'FONASA',
            'estado' => 'ACTIVO',
            'fecha_ingreso_empresa' => '2024-01-01',
        ]);

        $contrato = Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 800000,
            'horas_semana' => 45,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $liq = Liquidacion::create(array_merge([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'contrato_id' => $contrato->id,
            'anio' => 2026,
            'mes' => 6,
            'estado' => Liquidacion::ESTADO_EMITIDA,
            'total_haberes_imponibles' => 900000,
            'total_haberes_no_imponibles' => 50000,
            'total_haberes' => 950000,
            'base_imponible' => 900000,
            'base_tributable' => 810000,
            'total_descuentos_legales' => 135000,
            'total_descuentos_voluntarios' => 0,
            'total_descuentos' => 135000,
            'liquido_a_pagar' => 815000,
            'aporte_empleador_afc' => 21600,
            'aporte_empleador_sis' => 14580,
            'aporte_empleador_mutual' => 8100,
            'aporte_empleador_reforma' => 9000,
            'salud_legal' => 63000,
            'salud_adicional' => 0,
            'dias_trabajados' => 30,
            'dias_licencia_medica' => 0,
            'dias_vacaciones' => 0,
        ], $overridesLiq));

        // Detalles mínimos
        LiquidacionDetalle::create([
            'empresa_id' => $empresaId,
            'liquidacion_id' => $liq->id,
            'codigo_concepto' => 'SUELDO_BASE',
            'nombre_concepto' => 'Sueldo Base',
            'tipo' => 'HABER_IMPONIBLE',
            'monto' => 800000,
            'orden' => 100,
        ]);

        LiquidacionDetalle::create([
            'empresa_id' => $empresaId,
            'liquidacion_id' => $liq->id,
            'codigo_concepto' => 'AFP_COTIZACION',
            'nombre_concepto' => 'Cotización AFP',
            'tipo' => 'DESCUENTO_LEGAL',
            'monto' => 90000,
            'orden' => 200,
        ]);

        LiquidacionDetalle::create([
            'empresa_id' => $empresaId,
            'liquidacion_id' => $liq->id,
            'codigo_concepto' => 'AFC_TRABAJADOR',
            'nombre_concepto' => 'AFC Trabajador',
            'tipo' => 'DESCUENTO_LEGAL',
            'monto' => 5400,
            'orden' => 210,
        ]);

        LiquidacionDetalle::create([
            'empresa_id' => $empresaId,
            'liquidacion_id' => $liq->id,
            'codigo_concepto' => 'COLACION',
            'nombre_concepto' => 'Colación',
            'tipo' => 'HABER_NO_IMPONIBLE',
            'monto' => 30000,
            'orden' => 300,
        ]);

        LiquidacionDetalle::create([
            'empresa_id' => $empresaId,
            'liquidacion_id' => $liq->id,
            'codigo_concepto' => 'MOVILIZACION',
            'nombre_concepto' => 'Movilización',
            'tipo' => 'HABER_NO_IMPONIBLE',
            'monto' => 20000,
            'orden' => 310,
        ]);

        return [$empleado, $contrato, $liq];
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_genera_lre_con_lineas_por_trabajador(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->crearEmpleadoConLiquidacion($empresa->id, '12345678-9');
        $this->crearEmpleadoConLiquidacion($empresa->id, '22345678-0');
        $this->crearEmpleadoConLiquidacion($empresa->id, '32345678-1');

        $envio = $this->service->generar($empresa->id, 2026, 6);

        $this->assertEquals(3, $envio->cantidad_trabajadores);
        $this->assertEquals(LreEnvio::ESTADO_GENERADO, $envio->estado);
        $this->assertTrue(
            Storage::disk('sii_xml')->exists("lre/{$empresa->id}/2026-06.txt")
        );
    }

    public function test_lanza_error_si_no_hay_liquidaciones_emitidas(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        // Solo una liquidación en BORRADOR
        $empleado = Empleado::create([
            'empresa_id' => $empresa->id,
            'rut' => '12345678-9',
            'nombres' => 'Pedro',
            'apellido_paterno' => 'Soto',
            'afp' => 'Capital',
            'tipo_salud' => 'FONASA',
            'estado' => 'ACTIVO',
            'fecha_ingreso_empresa' => '2024-01-01',
        ]);

        $contrato = Contrato::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 700000,
            'horas_semana' => 45,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        Liquidacion::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'contrato_id' => $contrato->id,
            'anio' => 2026,
            'mes' => 6,
            'estado' => Liquidacion::ESTADO_BORRADOR,
            'total_haberes_imponibles' => 700000,
            'total_haberes_no_imponibles' => 0,
            'total_haberes' => 700000,
            'base_imponible' => 700000,
            'base_tributable' => 630000,
            'total_descuentos_legales' => 100000,
            'total_descuentos_voluntarios' => 0,
            'total_descuentos' => 100000,
            'liquido_a_pagar' => 600000,
            'aporte_empleador_afc' => 16800,
            'aporte_empleador_sis' => 11340,
            'aporte_empleador_mutual' => 6300,
            'aporte_empleador_reforma' => 7000,
            'salud_legal' => 49000,
            'salud_adicional' => 0,
        ]);

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('No hay liquidaciones emitidas para el período 6/2026.');

        $this->service->generar($empresa->id, 2026, 6);
    }

    public function test_archivo_lre_tiene_estructura_del_formato(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->crearEmpleadoConLiquidacion($empresa->id, '12345678-9');

        $this->service->generar($empresa->id, 2026, 6);

        $contenido = Storage::disk('sii_xml')->get("lre/{$empresa->id}/2026-06.txt");

        $this->assertStringContainsString('## INICIO_LIBRO', $contenido);
        $this->assertStringContainsString('## INICIO_TRABAJADOR', $contenido);
        $this->assertStringContainsString('## FIN_TRABAJADOR', $contenido);
        $this->assertStringContainsString('## FIN_LIBRO', $contenido);
        $this->assertStringContainsString('1101|', $contenido);
        $this->assertStringContainsString('5501|', $contenido);

        // Exactamente 1 sección de trabajador
        $this->assertEquals(1, substr_count($contenido, '## INICIO_TRABAJADOR'));
    }

    // Regresión: código mutual del LRE debe priorizar empresa->mutualidad sobre el campo legacy
    // por empleado, porque la afiliación es por empresa (ver migración 2026_07_10_000001).
    public function test_codigo_mutual_prioriza_mutualidad_de_la_empresa_sobre_campo_legacy_empleado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $ist = Mutualidad::where('nombre', 'IST')->first();
        $empresa->update(['mutualidad_id' => $ist->id]);

        [$empleado] = $this->crearEmpleadoConLiquidacion($empresa->id, '11111111-1');
        // organismo_mutual_codigo=1 (ACHS legacy) debe quedar sobrescrito por la mutualidad de la empresa (IST=3).
        $empleado->update(['organismo_mutual_codigo' => 1]);

        $this->service->generar($empresa->id, 2026, 6);

        $contenido = Storage::disk('sii_xml')->get("lre/{$empresa->id}/2026-06.txt");

        $this->assertStringContainsString('1152|3', $contenido, 'Debe usar codigo_lre de empresa.mutualidad (IST=3), no el campo legacy del empleado (1=ACHS)');
    }

    public function test_solo_incluye_liquidaciones_emitidas_no_borradores(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        // Empleado 1: liquidación EMITIDA
        $this->crearEmpleadoConLiquidacion($empresa->id, '12345678-9');

        // Empleado 2: liquidación BORRADOR
        $empleado2 = Empleado::create([
            'empresa_id' => $empresa->id,
            'rut' => '22345678-0',
            'nombres' => 'Ana',
            'apellido_paterno' => 'López',
            'afp' => 'Habitat',
            'tipo_salud' => 'FONASA',
            'estado' => 'ACTIVO',
            'fecha_ingreso_empresa' => '2024-01-01',
        ]);

        $contrato2 = Contrato::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado2->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 700000,
            'horas_semana' => 45,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        Liquidacion::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado2->id,
            'contrato_id' => $contrato2->id,
            'anio' => 2026,
            'mes' => 6,
            'estado' => Liquidacion::ESTADO_BORRADOR,
            'total_haberes_imponibles' => 700000,
            'total_haberes_no_imponibles' => 0,
            'total_haberes' => 700000,
            'base_imponible' => 700000,
            'base_tributable' => 630000,
            'total_descuentos_legales' => 100000,
            'total_descuentos_voluntarios' => 0,
            'total_descuentos' => 100000,
            'liquido_a_pagar' => 600000,
            'aporte_empleador_afc' => 16800,
            'aporte_empleador_sis' => 11340,
            'aporte_empleador_mutual' => 6300,
            'aporte_empleador_reforma' => 7000,
            'salud_legal' => 49000,
            'salud_adicional' => 0,
        ]);

        $envio = $this->service->generar($empresa->id, 2026, 6);

        $this->assertEquals(1, $envio->cantidad_trabajadores);
    }

    public function test_regenerar_no_duplica_registro_del_periodo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->crearEmpleadoConLiquidacion($empresa->id, '12345678-9');

        // Primera generación
        $envio1 = $this->service->generar($empresa->id, 2026, 6);

        // Segunda generación (idempotente)
        $envio2 = $this->service->generar($empresa->id, 2026, 6);

        // Solo debe existir un registro para el período
        $count = LreEnvio::where('empresa_id', $empresa->id)
            ->where('anio', 2026)
            ->where('mes', 6)
            ->count();

        $this->assertEquals(1, $count);
        $this->assertEquals($envio1->id, $envio2->id);
        $this->assertTrue(
            Storage::disk('sii_xml')->exists("lre/{$empresa->id}/2026-06.txt")
        );
    }

    public function test_no_permite_regenerar_un_lre_ya_confirmado_ante_la_dt(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $this->crearEmpleadoConLiquidacion($empresa->id, '12345678-9');

        $envio = $this->service->generar($empresa->id, 2026, 6);
        $envio->update(['estado' => LreEnvio::ESTADO_CONFIRMADO_DT]);

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('ya fue confirmado ante la Dirección del Trabajo');

        $this->service->generar($empresa->id, 2026, 6);
    }
}
