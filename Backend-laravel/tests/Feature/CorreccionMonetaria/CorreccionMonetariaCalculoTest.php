<?php

namespace Tests\Feature\CorreccionMonetaria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionEmpresa;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionCuenta;
use App\Domains\CorreccionMonetaria\Models\CmEjecucion;
use App\Domains\CorreccionMonetaria\Models\CmIndiceIpc;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use App\Domains\Activos\Models\ActivoFijo;
use Illuminate\Support\Facades\DB;

class CorreccionMonetariaCalculoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->crearConfiguracionCM();
        $this->crearCuentasBase();
    }
    // =========================================================================

    public function test_simular_sin_ipc_devuelve_error()
    {
        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/12/2026');

        $res->assertStatus(400);
        $this->assertFalse($res->json('success'));
    }

    public function test_simular_con_ipc_pero_sin_saldo_devuelve_estructura_vacia()
    {
        $this->cargarIpc(2026, 12, 0.5);

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/12/2026');

        $res->assertOk();
        $this->assertTrue($res->json('data.es_simulacion'));
        $this->assertEmpty($res->json('data.lineas'));
    }

    public function test_simular_con_activo_no_monetario_genera_linea_correcta()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/12/2026');

        $res->assertOk();
        $lineas = collect($res->json('data.lineas'));
        $linea  = $lineas->firstWhere('cuenta_codigo', '112005');

        $this->assertNotNull($linea);
        $this->assertEquals('ACTIVO_NO_MONETARIO', $linea['rol_cm']);
        $this->assertEquals(500000, $linea['ajuste']);
    }

    public function test_simular_ajuste_es_entero_sin_decimales()
    {
        $this->cargarIpc(2026, 12, 0.4200);
        $this->crearAsientoConSaldo('112005', 1000000, 'DEBE');

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/12/2026');

        $lineas = collect($res->json('data.lineas'));
        $linea  = $lineas->firstWhere('cuenta_codigo', '112005');

        $this->assertEquals(4200, $linea['ajuste']);
        $this->assertIsInt($linea['ajuste']);
    }

    public function test_simular_asiento_preview_cuadra_debe_igual_haber()
    {
        $this->cargarIpc(2026, 12, 2.0);
        $this->crearAsientoConSaldo('112005', 5000000, 'DEBE');
        $this->crearAsientoConSaldo('151005', 2000000, 'DEBE');

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/12/2026');

        $preview = $res->json('data.asiento_preview');
        $totalDebe  = collect($preview)->sum('debe');
        $totalHaber = collect($preview)->sum('haber');

        $this->assertEquals($totalDebe, $totalHaber);
    }

    public function test_simular_devuelve_variacion_y_tipo_correctos_modalidad_anual()
    {
        $this->cargarIpc(2026, 1, 0.5);
        $this->cargarIpc(2026, 12, 0.4);
        $this->crearAsientoConSaldo('112005', 1000000, 'DEBE');

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/12/2026');

        $this->assertEquals('anual', $res->json('data.tipo'));
        $this->assertNotNull($res->json('data.variacion_pct'));
    }

    public function test_simular_modalidad_mensual_usa_variacion_del_mes()
    {
        $this->actingAs($this->usuario)
            ->putJson('/api/correccion-monetaria/configuracion', ['modalidad' => 'mensual']);
        $this->cargarIpc(2026, 6, 0.3200);
        $this->crearAsientoConSaldo('112005', 1000000, 'DEBE');

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/6/2026');

        $this->assertEquals('mensual', $res->json('data.tipo'));
        $this->assertEqualsWithDelta(0.32, $res->json('data.variacion_pct'), 0.001);
    }

    public function test_simular_cuenta_con_saldo_haber_no_genera_ajuste_para_activo()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 1000000, 'HABER');

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/12/2026');

        $lineas = collect($res->json('data.lineas'));
        $this->assertEmpty($lineas->where('cuenta_codigo', '112005'));
    }

    public function test_simular_multiples_roles_genera_totales_separados()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');
        $this->crearAsientoConSaldo('112006', 3000000,  'HABER');
        $this->crearAsientoConSaldo('151005', 2000000,  'DEBE');

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/12/2026');

        $totales = $res->json('data.totales');
        $this->assertGreaterThan(0, $totales['activos']);
        $this->assertGreaterThan(0, $totales['depreciacion']);
        $this->assertGreaterThan(0, $totales['existencias']);
    }

    public function test_simular_no_crea_registro_en_cm_ejecuciones()
    {
        $this->cargarIpc(2026, 12, 0.5);
        $this->crearAsientoConSaldo('112005', 1000000, 'DEBE');

        $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/simular/12/2026');

        $this->assertEquals(0, CmEjecucion::count());
    }

    // =========================================================================
    // EJECUCION
    // =========================================================================

    public function test_ejecutar_sin_ipc_devuelve_error()
    {
        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $res->assertStatus(400);
    }

    public function test_ejecutar_sin_saldo_devuelve_error()
    {
        $this->cargarIpc(2026, 12, 0.5);

        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $res->assertStatus(400);
    }

    public function test_ejecutar_happy_path_crea_asiento_contable()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');

        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertNotNull($res->json('data.asiento_comprobante'));
        $this->assertGreaterThan(0, $res->json('data.total_cm_neto'));
    }

    public function test_ejecutar_registra_ejecucion_en_tabla_cm_ejecuciones()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');

        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $this->assertEquals(1, CmEjecucion::where('empresa_id', $this->empresa->id)->count());
        $ejecucion = CmEjecucion::first();
        $this->assertEquals('ejecutada', $ejecucion->estado);
        $this->assertEquals(12, $ejecucion->periodo_mes);
        $this->assertEquals(2026, $ejecucion->periodo_anio);
    }

    public function test_ejecutar_asiento_tiene_origen_modulo_correccion_monetaria()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');

        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $this->assertDatabaseHas('asientos_contables', [
            'empresa_id'    => $this->empresa->id,
            'origen_modulo' => 'correccion_monetaria',
            'estado'        => 'MAYORIZADO',
        ]);
    }

    public function test_ejecutar_asiento_cuadra_debe_igual_haber()
    {
        $this->cargarIpc(2026, 12, 3.0);
        $this->crearAsientoConSaldo('112005', 8000000, 'DEBE');
        $this->crearAsientoConSaldo('112006', 2000000, 'HABER');

        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $asiento = AsientoContable::where('origen_modulo', 'correccion_monetaria')->first();
        $sumaDebe  = $asiento->detalles->sum('debe');
        $sumaHaber = $asiento->detalles->sum('haber');

        $this->assertEquals($sumaDebe, $sumaHaber);
    }

    public function test_ejecutar_dos_veces_mismo_periodo_bloquea_segunda_ejecucion()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');

        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $res2 = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $res2->assertStatus(400);
        $this->assertEquals(1, CmEjecucion::where('estado', 'ejecutada')->count());
    }

    public function test_ejecutar_modalidad_anual_bloquea_mes_sin_cierre()
    {
        $this->cargarIpc(2026, 6, 0.5);
        $this->crearAsientoConSaldo('112005', 5000000, 'DEBE');

        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 6, 'anio' => 2026]);

        $res->assertStatus(400);
        $this->assertStringContainsString('modalidad', strtolower($res->json('message')));
    }

    public function test_ejecutar_modalidad_mensual_permite_cualquier_mes()
    {
        $this->actingAs($this->usuario)
            ->putJson('/api/correccion-monetaria/configuracion', ['modalidad' => 'mensual']);
        $this->cargarIpc(2026, 6, 1.0);
        $this->crearAsientoConSaldo('112005', 5000000, 'DEBE');

        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 6, 'anio' => 2026]);

        $res->assertOk();
    }

    public function test_ejecutar_actualiza_cm_ajuste_acumulado_en_activos_fijos()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');

        $activo = ActivoFijo::create([
            'empresa_id'            => $this->empresa->id,
            'codigo'                => 'ACT-TEST-001',
            'nombre'                => 'Edificio Test',
            'fecha_adquisicion'     => '2020-01-01',
            'valor_adquisicion'     => 10000000,
            'vida_util_meses'       => 480,
            'estado'                => 'ACTIVO',
            'depreciacion_acumulada'=> 0,
        ]);

        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $activo->refresh();
        $this->assertGreaterThan(0, (float) $activo->cm_ajuste_acumulado);
        $this->assertEquals(12, $activo->ultimo_periodo_cm_mes);
        $this->assertEquals(2026, $activo->ultimo_periodo_cm_anio);
    }

    public function test_ejecutar_empresa_14d8_rechaza_ejecucion()
    {
        $this->empresa->update(['regimen_tributario' => '14_D8']);
        CmConfiguracionEmpresa::where('empresa_id', $this->empresa->id)
            ->update(['aplica_cm' => false]);

        $this->cargarIpc(2026, 12, 0.5);

        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $res->assertStatus(400);
    }

    // =========================================================================
    // HISTORIAL
    // =========================================================================

    public function test_historial_retorna_lista_vacia_inicial()
    {
        $res = $this->actingAs($this->usuario)->getJson('/api/correccion-monetaria/historial');

        $res->assertOk();
        $this->assertEmpty($res->json('data'));
    }

    public function test_historial_retorna_ejecucion_despues_de_ejecutar()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 5000000, 'DEBE');

        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $res = $this->actingAs($this->usuario)->getJson('/api/correccion-monetaria/historial');

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_historial_filtro_por_anio_funciona()
    {
        CmEjecucion::create([
            'empresa_id'           => $this->empresa->id,
            'periodo_mes'          => 12,
            'periodo_anio'         => 2024,
            'tipo'                 => 'anual',
            'estado'               => 'ejecutada',
            'factor_ipc_utilizado' => 1.005,
            'variacion_porcentual' => 0.5,
            'total_cm_neto'        => 100000,
        ]);
        CmEjecucion::create([
            'empresa_id'           => $this->empresa->id,
            'periodo_mes'          => 12,
            'periodo_anio'         => 2025,
            'tipo'                 => 'anual',
            'estado'               => 'ejecutada',
            'factor_ipc_utilizado' => 1.004,
            'variacion_porcentual' => 0.4,
            'total_cm_neto'        => 90000,
        ]);

        $res = $this->actingAs($this->usuario)->getJson('/api/correccion-monetaria/historial?anio=2024');

        $this->assertCount(1, $res->json('data'));
        $this->assertEquals(2024, $res->json('data.0.anio'));
    }

    public function test_historial_item_tiene_estructura_correcta()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 5000000, 'DEBE');

        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $item = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/historial')
            ->json('data.0');

        foreach (['id', 'periodo', 'mes', 'anio', 'tipo', 'estado', 'variacion_pct', 'total_cm_neto'] as $campo) {
            $this->assertArrayHasKey($campo, $item, "Falta campo: {$campo}");
        }
    }

    // =========================================================================
    // CONFIGURACION CUENTAS
    // =========================================================================

    public function test_get_cuentas_configuracion_retorna_lista()
    {
        $res = $this->actingAs($this->usuario)->getJson('/api/correccion-monetaria/cuentas');

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertIsArray($res->json('data'));
    }

    public function test_post_cuentas_agrega_cuenta_nueva()
    {
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo'     => '199999',
            'nombre'     => 'Activo NM Extra',
            'tipo'       => 'ACTIVO',
            'imputable'  => true,
            'activo'     => true,
        ]);

        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/cuentas', [
                'cuenta_codigo' => '199999',
                'rol_cm'        => 'ACTIVO_NO_MONETARIO',
            ]);

        $res->assertOk();
        $this->assertDatabaseHas('cm_configuracion_cuentas', [
            'empresa_id'    => $this->empresa->id,
            'cuenta_codigo' => '199999',
            'rol_cm'        => 'ACTIVO_NO_MONETARIO',
        ]);
    }

    public function test_post_cuentas_rechaza_rol_invalido()
    {
        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/cuentas', [
                'cuenta_codigo' => '112005',
                'rol_cm'        => 'ROL_INEXISTENTE',
            ]);

        $res->assertStatus(400);
    }

    public function test_post_cuentas_rechaza_cuenta_inexistente_en_plan()
    {
        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/cuentas', [
                'cuenta_codigo' => '999999',
                'rol_cm'        => 'ACTIVO_NO_MONETARIO',
            ]);

        $res->assertStatus(400);
    }

    public function test_put_cuentas_desactiva_cuenta()
    {
        $this->actingAs($this->usuario)
            ->putJson('/api/correccion-monetaria/cuentas', [
                'cuentas' => [['cuenta_codigo' => '112005', 'aplica' => false]],
            ]);

        $this->assertDatabaseHas('cm_configuracion_cuentas', [
            'empresa_id'    => $this->empresa->id,
            'cuenta_codigo' => '112005',
            'aplica'        => false,
        ]);
    }

    // =========================================================================
    // INTEGRACION F22
    // =========================================================================

    public function test_pre_calculo_renta_incluye_campo_correccion_monetaria()
    {
        $res = $this->actingAs($this->usuario)
            ->getJson('/api/renta/pre-calculo/2026');

        $res->assertOk();
        $this->assertArrayHasKey('correccion_monetaria', $res->json('data'));
    }

    public function test_pre_calculo_renta_sin_cm_ejecutada_devuelve_ejecutada_false()
    {
        $res = $this->actingAs($this->usuario)
            ->getJson('/api/renta/pre-calculo/2026');

        $cm = $res->json('data.correccion_monetaria');
        $this->assertFalse($cm['ejecutada']);
        $this->assertEquals(0, $cm['ingreso_cm']);
        $this->assertEquals(0, $cm['gasto_cm']);
    }

    public function test_pre_calculo_renta_con_cm_ejecutada_refleja_ajuste()
    {
        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');

        $resEjecutar = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);
        $resEjecutar->assertOk();

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/renta/pre-calculo/2026');

        $cm = $res->json('data.correccion_monetaria');
        $this->assertTrue($cm['ejecutada']);
        $this->assertGreaterThan(0, $cm['periodos']);
    }

    public function test_pre_calculo_renta_ingreso_cm_aumenta_base_imponible()
    {
        $sinCm = $this->actingAs($this->usuario)
            ->getJson('/api/renta/pre-calculo/2026')
            ->json('data.resultado.base_imponible');

        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');

        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        $conCm = $this->actingAs($this->usuario)
            ->getJson('/api/renta/pre-calculo/2026')
            ->json('data.resultado.base_imponible');

        $this->assertGreaterThanOrEqual($sinCm, $conCm);
    }

    public function test_pre_calculo_renta_empresa_distinta_no_mezcla_cm()
    {
        [$empresa2, $usuario2] = $this->crearEmpresaConAdmin();

        $this->cargarIpc(2026, 12, 5.0);
        $this->crearAsientoConSaldo('112005', 10000000, 'DEBE');

        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/ejecutar', ['mes' => 12, 'anio' => 2026]);

        CmConfiguracionEmpresa::firstOrCreate(
            ['empresa_id' => $empresa2->id],
            [
                'aplica_cm' => true, 'modalidad' => 'anual',
                'mes_cierre' => 12, 'cuenta_activos_codigo' => '811001',
                'cuenta_depreciacion_codigo' => '821001', 'cuenta_patrimonio_codigo' => '311406',
                'cuenta_existencias_codigo' => '811002', 'cuenta_pasivos_codigo' => '821002',
                'activo' => true,
            ]
        );

        $res2 = $this->actingAs($usuario2)->getJson('/api/renta/pre-calculo/2026');
        $cm2  = $res2->json('data.correccion_monetaria');

        $this->assertFalse($cm2['ejecutada']);
        $this->assertEquals(0, $cm2['ingreso_cm']);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function crearConfiguracionCM(): void
    {
        CmConfiguracionEmpresa::firstOrCreate(
            ['empresa_id' => $this->empresa->id],
            [
                'aplica_cm'                  => true,
                'modalidad'                  => 'anual',
                'mes_cierre'                 => 12,
                'cuenta_activos_codigo'      => '811001',
                'cuenta_depreciacion_codigo' => '821001',
                'cuenta_patrimonio_codigo'   => '311406',
                'cuenta_existencias_codigo'  => '811002',
                'cuenta_pasivos_codigo'      => '821002',
                'activo'                     => true,
            ]
        );
    }

    private function crearCuentasBase(): void
    {
        $cuentas = [
            ['112005', 'Edificios',              'ACTIVO',     true,  'ACTIVO_NO_MONETARIO'],
            ['112006', 'Dep Acum Edificios',     'ACTIVO',     true,  'DEPRECIACION_ACUMULADA'],
            ['151005', 'Inventario Materiales',  'ACTIVO',     true,  'INVENTARIO'],
            ['311406', 'CM Patrimonio',          'PATRIMONIO', true,  'PATRIMONIO_CAPITAL'],
            ['811001', 'CM Ganancia Activos',    'INGRESO',    true,  null],
            ['811002', 'CM Ganancia Existencias','INGRESO',    true,  null],
            ['821001', 'CM Perdida Dep',         'GASTO',      true,  null],
            ['821002', 'CM Perdida Pasivos',     'GASTO',      true,  null],
            ['821003', 'CM Perdida Patrimonio',  'GASTO',      true,  null],
        ];

        foreach ($cuentas as [$codigo, $nombre, $tipo, $imputable, $rol]) {
            PlanCuenta::create([
                'empresa_id' => $this->empresa->id,
                'codigo'     => $codigo,
                'nombre'     => $nombre,
                'tipo'       => $tipo,
                'imputable'  => $imputable,
                'activo'     => true,
            ]);

            if ($rol) {
                CmConfiguracionCuenta::create([
                    'empresa_id'    => $this->empresa->id,
                    'cuenta_codigo' => $codigo,
                    'rol_cm'        => $rol,
                    'aplica'        => true,
                ]);
            }
        }
    }

    private function cargarIpc(int $anio, int $mes, float $variacion): void
    {
        CmIndiceIpc::updateOrCreate(
            ['anio' => $anio, 'mes' => $mes],
            [
                'variacion_mensual'          => $variacion,
                'variacion_acumulada_anual'  => $variacion,
                'factor_multiplicador'       => round(1 + ($variacion / 100), 6),
                'fuente'                     => 'manual',
            ]
        );
    }

    private function crearAsientoConSaldo(string $cuenta, int $monto, string $tipoOp): void
    {
        $asiento = AsientoContable::create([
            'empresa_id'          => $this->empresa->id,
            'fecha'               => '2026-01-31',
            'glosa'               => "Saldo {$cuenta}",
            'numero_comprobante'  => 'TEST-' . uniqid(),
            'tipo_asiento'        => 'traspaso',
            'origen_modulo'       => 'contabilidad',
            'estado'              => 'MAYORIZADO',
        ]);

        DetalleAsiento::create([
            'asiento_id'      => $asiento->id,
            'cuenta_contable' => $cuenta,
            'fecha'           => '2026-01-31',
            'tipo_operacion'  => $tipoOp,
            'debe'            => $tipoOp === 'DEBE' ? $monto : 0,
            'haber'           => $tipoOp === 'HABER' ? $monto : 0,
        ]);
    }
}
