<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use App\Domains\Rrhh\Models\LreEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * GenerarLreTest/ValidarLreTest/DescargaConfirmacionLreTest ya cubren los services
 * llamandolos directo. LreController (rutas, validacion HTTP, permisos, 404 multitenant)
 * seguia en 0% de coverage -- este archivo cubre las 5 rutas via HTTP real.
 */
class LreControllerHttpTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Storage::fake('sii_xml');
    }

    private function contenidoLreValido(string $rutEmpresa = '12345678-9'): string
    {
        return implode("\n", [
            '## INICIO_LIBRO',
            "EMPRESA_RUT|{$rutEmpresa}",
            'EMPRESA_RAZON_SOCIAL|Empresa Test',
            'PERIODO_ANIO|2026',
            'PERIODO_MES|6',
            'CANTIDAD_TRABAJADORES|1',
            '## INICIO_TRABAJADOR',
            '1101|9876543-2',
            '1102|01/01/2024',
            '2101|800000',
            '5501|650000',
            '## FIN_TRABAJADOR',
            '## FIN_LIBRO',
        ]);
    }

    private function crearEmpleadoConLiquidacion(int $empresaId, string $rut): void
    {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId, 'rut' => $rut,
            'nombres' => 'Juan', 'apellido_paterno' => 'Pérez',
            'afp' => 'Capital', 'tipo_salud' => 'FONASA',
            'estado' => 'ACTIVO', 'fecha_ingreso_empresa' => '2024-01-01',
        ]);

        $contrato = Contrato::create([
            'empresa_id' => $empresaId, 'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO', 'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 800000, 'horas_semana' => 45,
            'estado' => 'VIGENTE', 'es_contrato_activo' => true,
        ]);

        $liq = Liquidacion::create([
            'empresa_id' => $empresaId, 'empleado_id' => $empleado->id, 'contrato_id' => $contrato->id,
            'anio' => 2026, 'mes' => 6, 'estado' => Liquidacion::ESTADO_EMITIDA,
            'total_haberes_imponibles' => 900000, 'total_haberes_no_imponibles' => 50000,
            'total_haberes' => 950000, 'base_imponible' => 900000, 'base_tributable' => 810000,
            'total_descuentos_legales' => 135000, 'total_descuentos_voluntarios' => 0,
            'total_descuentos' => 135000, 'liquido_a_pagar' => 815000,
            'aporte_empleador_afc' => 21600, 'aporte_empleador_sis' => 14580,
            'aporte_empleador_mutual' => 8100, 'aporte_empleador_reforma' => 9000,
            'salud_legal' => 63000, 'salud_adicional' => 0,
            'dias_trabajados' => 30, 'dias_licencia_medica' => 0, 'dias_vacaciones' => 0,
        ]);

        LiquidacionDetalle::create([
            'empresa_id' => $empresaId, 'liquidacion_id' => $liq->id,
            'codigo_concepto' => 'SUELDO_BASE', 'nombre_concepto' => 'Sueldo Base',
            'tipo' => 'HABER_IMPONIBLE', 'monto' => 800000, 'orden' => 100,
        ]);
        LiquidacionDetalle::create([
            'empresa_id' => $empresaId, 'liquidacion_id' => $liq->id,
            'codigo_concepto' => 'AFP_COTIZACION', 'nombre_concepto' => 'Cotización AFP',
            'tipo' => 'DESCUENTO_LEGAL', 'monto' => 90000, 'orden' => 200,
        ]);
    }

    private function crearLreEnvio(int $empresaId, array $overrides = []): LreEnvio
    {
        $archivoPath = "lre/{$empresaId}/2026-06.txt";
        Storage::disk('sii_xml')->put($archivoPath, $this->contenidoLreValido());

        return LreEnvio::create(array_merge([
            'empresa_id' => $empresaId, 'anio' => 2026, 'mes' => 6,
            'estado' => LreEnvio::ESTADO_GENERADO,
            'cantidad_trabajadores' => 1, 'archivo_path' => $archivoPath,
        ], $overrides));
    }

    // ── generar() ────────────────────────────────────────────────────────

    public function test_generar_via_http_crea_el_lre_y_devuelve_200()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->crearEmpleadoConLiquidacion($empresa->id, '12345678-9');

        $response = $this->actingAs($admin)->postJson('/api/rrhh/lre/generar', [
            'anio' => 2026, 'mes' => 6,
        ]);

        $response->assertStatus(200)->assertJsonPath('estado', LreEnvio::ESTADO_GENERADO);
        $this->assertTrue(Storage::disk('sii_xml')->exists("lre/{$empresa->id}/2026-06.txt"));
    }

    public function test_generar_rechaza_sin_mes_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/rrhh/lre/generar', ['anio' => 2026]);

        $response->assertStatus(422)->assertJsonValidationErrors(['mes']);
    }

    public function test_generar_devuelve_422_si_no_hay_liquidaciones_emitidas()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/rrhh/lre/generar', [
            'anio' => 2026, 'mes' => 6,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('No hay liquidaciones', $response->json('message'));
    }

    // ── validar() ────────────────────────────────────────────────────────

    public function test_validar_via_http_marca_estado_validado_sin_errores()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $lre = $this->crearLreEnvio($empresa->id);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/lre/{$lre->id}/validar");

        $response->assertStatus(200)
            ->assertJsonPath('errores', [])
            ->assertJsonPath('lre.estado', LreEnvio::ESTADO_VALIDADO);
    }

    public function test_validar_via_http_devuelve_422_y_errores_si_el_archivo_es_invalido()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $archivoPath = "lre/{$empresa->id}/2026-06.txt";
        Storage::disk('sii_xml')->put($archivoPath, "EMPRESA_RUT|12345678-9\n## FIN_LIBRO");
        $lre = LreEnvio::create([
            'empresa_id' => $empresa->id, 'anio' => 2026, 'mes' => 6,
            'estado' => LreEnvio::ESTADO_GENERADO,
            'cantidad_trabajadores' => 0, 'archivo_path' => $archivoPath,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/lre/{$lre->id}/validar");

        $response->assertStatus(422)->assertJsonPath('lre.estado', LreEnvio::ESTADO_ERROR_VALIDACION);
        $this->assertNotEmpty($response->json('errores'));
    }

    public function test_validar_de_lre_de_otra_empresa_devuelve_404()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $otraEmpresa = $this->crearEmpresa(['rut' => '66.666.666-6', 'razon_social' => 'Rival LRE']);
        $lreAjeno = $this->crearLreEnvio($otraEmpresa->id);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/lre/{$lreAjeno->id}/validar");

        $response->assertStatus(404);
    }

    // ── confirmarDt() ────────────────────────────────────────────────────

    public function test_confirmar_dt_via_http_transiciona_a_confirmado()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $lre = $this->crearLreEnvio($empresa->id, ['estado' => LreEnvio::ESTADO_VALIDADO]);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/lre/{$lre->id}/confirmar-dt", [
            'numero_confirmacion' => 'DT-2026-0006',
        ]);

        $response->assertStatus(200)->assertJsonPath('estado', LreEnvio::ESTADO_CONFIRMADO_DT);
        $this->assertSame('DT-2026-0006', $lre->fresh()->numero_confirmacion_dt);
    }

    public function test_confirmar_dt_rechaza_si_el_lre_no_esta_validado()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $lre = $this->crearLreEnvio($empresa->id, ['estado' => LreEnvio::ESTADO_GENERADO]);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/lre/{$lre->id}/confirmar-dt", [
            'numero_confirmacion' => 'DT-2026-0006',
        ]);

        $response->assertStatus(422);
    }

    // ── index() ──────────────────────────────────────────────────────────

    public function test_index_filtra_por_anio_y_mes()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->crearLreEnvio($empresa->id, ['anio' => 2026, 'mes' => 6]);
        $this->crearLreEnvio($empresa->id, ['anio' => 2026, 'mes' => 5, 'archivo_path' => "lre/{$empresa->id}/2026-05.txt"]);

        $response = $this->actingAs($admin)->getJson('/api/rrhh/lre?anio=2026&mes=6');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame(6, $data[0]['mes']);
    }

    // ── descargar() ──────────────────────────────────────────────────────

    public function test_descargar_via_http_devuelve_el_archivo()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $lre = $this->crearLreEnvio($empresa->id);

        $response = $this->actingAs($admin)->get("/api/rrhh/lre/{$lre->id}/descargar");

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_descargar_de_lre_de_otra_empresa_devuelve_404()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $otraEmpresa = $this->crearEmpresa(['rut' => '55.555.555-5', 'razon_social' => 'Rival LRE 2']);
        $lreAjeno = $this->crearLreEnvio($otraEmpresa->id);

        $response = $this->actingAs($admin)->getJson("/api/rrhh/lre/{$lreAjeno->id}/descargar");

        $response->assertStatus(404);
    }
}
