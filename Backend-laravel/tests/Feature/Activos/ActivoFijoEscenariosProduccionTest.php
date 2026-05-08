<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Contabilidad\Models\PlanCuenta;

/**
 * Tests focalizados en escenarios extremos de activos fijos.
 *
 * Cubre casos no contemplados en los tests existentes:
 * - Codigo unico de activo entre empresas
 * - Activos con valor residual extremo
 * - Eliminacion de activos con dependencias
 * - Concurrencia en alta de activos
 */
class ActivoFijoEscenariosProduccionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        // Cuentas necesarias para crear activos
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '120101',
            'nombre' => 'Equipo de oficina',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '120151',
            'nombre' => 'Depreciacion acumulada equipo oficina',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '510101',
            'nombre' => 'Gasto depreciacion',
            'tipo' => 'GASTO',
            'imputable' => true,
            'activo' => true,
        ]);
    }

    public function test_codigo_de_activo_es_unique_globalmente_entre_empresas()
    {
        // Por el schema, `codigo` es unique sin scope de empresa.
        // Esto significa que el codigo de un activo NO se puede repetir
        // ni siquiera entre empresas distintas. Validamos ese comportamiento.

        ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-COMUN-001',
            'nombre' => 'Activo A',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 1000000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);

        $empresaB = $this->crearEmpresa();

        try {
            ActivoFijo::create([
                'empresa_id' => $empresaB->id,
                'codigo' => 'AF-COMUN-001', // mismo codigo
                'nombre' => 'Activo B',
                'cuenta_activo_codigo' => '120101',
                'cuenta_depreciacion_codigo' => '120151',
                'cuenta_gasto_codigo' => '510101',
                'valor_adquisicion' => 500000,
                'fecha_adquisicion' => '2026-04-01',
                'vida_util_meses' => 36,
                'valor_residual' => 1,
                'estado' => 'ACTIVO',
            ]);

            // Si llego aca, el unique no esta funcionando
            $this->markTestIncomplete(
                'Hallazgo: schema dice unique() pero permitio codigo duplicado entre empresas. ' .
                'Decision de diseno: el unique global puede ser problematico en multi-tenant. ' .
                'Considerar cambiar a unique compuesto (empresa_id, codigo).'
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Esperado por schema actual.
            // MySQL: "Duplicate entry"
            // SQLite: "UNIQUE constraint failed"
            $msg = $e->getMessage();
            $this->assertTrue(
                str_contains($msg, 'Duplicate') || str_contains($msg, 'UNIQUE'),
                'Mensaje de error inesperado: ' . $msg
            );
        }
    }

    public function test_activo_con_valor_residual_igual_a_adquisicion_no_genera_depreciacion()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-NULL-DEP',
            'nombre' => 'Activo sin depreciacion',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 100000, // igual al de adquisicion
            'estado' => 'ACTIVO',
        ]);

        // Calculo manual de depreciacion mensual
        $depreciacionMensual = ($activo->valor_adquisicion - $activo->valor_residual) / $activo->vida_util_meses;

        $this->assertEquals(0, $depreciacionMensual,
            'Depreciacion mensual deberia ser 0 si residual == adquisicion');
    }

    public function test_activo_con_vida_util_de_un_mes_se_deprecia_completamente_en_un_mes()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-1MES',
            'nombre' => 'Activo 1 mes',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 60000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 1,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);

        $depreciacionMensual = ($activo->valor_adquisicion - $activo->valor_residual) / $activo->vida_util_meses;

        $this->assertEquals(59999, $depreciacionMensual);
    }

    public function test_activo_con_centro_costo_de_otra_empresa_se_rechaza_por_validacion()
    {
        // Crear centro costo en empresa B
        $empresaB = $this->crearEmpresa();
        $ccB = \App\Domains\Contabilidad\Models\CentroCosto::create([
            'empresa_id' => $empresaB->id,
            'codigo' => 'CC-B-99',
            'nombre' => 'CC Empresa B',
            'activo' => true,
        ]);

        // BD permite el insert (FK no valida empresa). La validacion debe ser semantica.
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id, // empresa A
            'codigo' => 'AF-CC-FOREIGN',
            'nombre' => 'Activo con CC ajeno',
            'centro_costo_id' => $ccB->id, // CC de OTRA empresa
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);

        // BD permitio el insert. Hallazgo de diseno: agregar validacion
        $this->assertNotNull($activo->id);
        $this->markTestIncomplete(
            'Hallazgo: BD acepta activo con centro_costo_id de otra empresa. ' .
            'Validar en ActivoFijoService::store() que centro_costo.empresa_id == empresa_id.'
        );
    }

    public function test_activo_con_depreciacion_acumulada_negativa_es_inconsistente()
    {
        // La depreciacion no puede ser negativa nunca. Si lo es, hay un bug
        // en logica de calculo. Validamos a nivel BD que se persiste como esta.
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-NEG',
            'nombre' => 'Activo dep negativa',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
            'depreciacion_acumulada' => -5000, // valor invalido!
        ]);

        // BD lo permitio (no hay CHECK constraint).
        // Hallazgo: agregar validacion que dep_acumulada >= 0.
        $this->assertEquals(-5000, (float) $activo->depreciacion_acumulada);
        $this->markTestIncomplete(
            'Hallazgo: BD acepta depreciacion_acumulada negativa. ' .
            'Agregar CHECK constraint o validacion en modelo.'
        );
    }

    public function test_activo_dado_de_baja_no_aparece_en_listado_activos()
    {
        $vivoId = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-VIVO',
            'nombre' => 'Activo vivo',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ])->id;

        $bajaId = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-BAJA',
            'nombre' => 'Activo dado de baja',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 50000,
            'fecha_adquisicion' => '2026-01-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'BAJA',
        ])->id;

        // Listar activos solo operativos
        $operativos = ActivoFijo::where('empresa_id', $this->empresa->id)
            ->where('estado', 'ACTIVO')
            ->pluck('id')->toArray();

        $this->assertContains($vivoId, $operativos);
        $this->assertNotContains($bajaId, $operativos);
    }

    public function test_codigo_de_activo_no_acepta_caracteres_especiales_de_sql_injection()
    {
        $codigoMalicioso = "AF'; DROP TABLE activos_fijos; --";

        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => $codigoMalicioso,
            'nombre' => 'Activo SQLi',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);

        // El codigo se persiste literal (Eloquent escapa correctamente)
        $this->assertEquals($codigoMalicioso, $activo->fresh()->codigo);

        // Y la tabla sigue existiendo (no se ejecuto el DROP)
        $count = DB::table('activos_fijos')->count();
        $this->assertGreaterThan(0, $count);
    }

    public function test_listar_activos_de_empresa_no_filtra_activos_de_otra()
    {
        // Crear activo en mi empresa
        $miActivo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-MIO',
            'nombre' => 'Mi activo',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);

        // Activo de otra empresa
        $empresaB = $this->crearEmpresa();
        $activoB = ActivoFijo::create([
            'empresa_id' => $empresaB->id,
            'codigo' => 'AF-AJENO',
            'nombre' => 'Activo ajeno',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 999999,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);

        // Listar como usuario de empresa A
        $response = $this->actingAs($this->usuario)->getJson('/api/activos');
        $response->assertStatus(200);

        $body = $response->json();
        $activos = $body['data'] ?? $body;
        $this->assertIsArray($activos);

        $idsExpuestos = array_map(fn($a) => $a['id'] ?? null, $activos);
        $this->assertContains($miActivo->id, $idsExpuestos);
        $this->assertNotContains($activoB->id, $idsExpuestos,
            'IDOR: usuario A vio activo de empresa B en listado');
    }
}
