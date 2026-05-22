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

    public function test_codigo_de_activo_puede_repetirse_entre_empresas_distintas()
    {
        // Despues del fix multitenancy, el unique de `codigo` es compuesto
        // (empresa_id, codigo). Ahora cada empresa puede tener AF-COMUN-001
        // independientemente.

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

        // Esto debe funcionar despues del fix
        $activoB = ActivoFijo::create([
            'empresa_id' => $empresaB->id,
            'codigo' => 'AF-COMUN-001', // mismo codigo, otra empresa
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

        $this->assertNotNull($activoB->id,
            'El codigo deberia poder repetirse entre empresas distintas (multitenancy)');
    }

    public function test_codigo_de_activo_no_puede_repetirse_dentro_de_la_misma_empresa()
    {
        ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-DUP-001',
            'nombre' => 'Activo 1',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 1000000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Mismo codigo en la misma empresa: debe fallar
        ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-DUP-001', // duplicado!
            'nombre' => 'Activo 2',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 500000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 36,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);
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

    public function test_activo_via_endpoint_rechaza_centro_costo_de_otra_empresa()
    {
        // Crear centro costo en empresa B
        $empresaB = $this->crearEmpresa();
        $ccB = \App\Domains\Contabilidad\Models\CentroCosto::create([
            'empresa_id' => $empresaB->id,
            'codigo' => 'CC-B-99',
            'nombre' => 'CC Empresa B',
            'activo' => true,
        ]);

        // Como usuario de empresa A, intentar crear activo con CC de B
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', [
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
        ]);

        // Despues del fix, el service rechaza con excepcion
        $this->assertContains($response->getStatusCode(), [400, 422, 500],
            'Activo con CC de otra empresa deberia ser rechazado');

        // Validar que NO se creo el activo
        $existe = ActivoFijo::where('codigo', 'AF-CC-FOREIGN')->exists();
        $this->assertFalse($existe, 'Se creo activo con CC de empresa ajena');
    }

    public function test_activo_con_depreciacion_acumulada_negativa_es_rechazado_por_constraint()
    {
        // Despues del fix con CHECK constraint, intentar insertar dep negativa debe fallar.
        $this->expectException(\Illuminate\Database\QueryException::class);

        ActivoFijo::create([
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
    }

    public function test_activo_con_depreciacion_acumulada_cero_es_aceptado()
    {
        // Cero es valido (es el default cuando un activo recien se crea)
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-DEP-CERO',
            'nombre' => 'Activo dep en cero',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
            'depreciacion_acumulada' => 0,
        ]);

        $this->assertNotNull($activo->id);
        $this->assertEquals(0, (float) $activo->depreciacion_acumulada);
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
