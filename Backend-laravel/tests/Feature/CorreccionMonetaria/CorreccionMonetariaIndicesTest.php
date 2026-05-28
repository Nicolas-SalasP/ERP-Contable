<?php

namespace Tests\Feature\CorreccionMonetaria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\CorreccionMonetaria\Models\CmIndiceIpc;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionEmpresa;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionCuenta;
use App\Domains\Contabilidad\Models\PlanCuenta;

class CorreccionMonetariaIndicesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

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

    public function test_post_indices_guarda_ipc_correctamente()
    {
        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', [
                'anio'      => 2026,
                'mes'       => 3,
                'variacion' => 0.4200,
            ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('cm_indices_ipc', [
            'anio'             => 2026,
            'mes'              => 3,
            'variacion_mensual'=> 0.4200,
        ]);
    }

    public function test_factor_multiplicador_se_calcula_correctamente()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', [
                'anio'      => 2026,
                'mes'       => 1,
                'variacion' => 0.5000,
            ]);

        $indice = CmIndiceIpc::where('anio', 2026)->where('mes', 1)->first();
        $this->assertEquals(1.005000, (float) $indice->factor_multiplicador);
    }

    public function test_factor_multiplicador_variacion_negativa()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', [
                'anio'      => 2026,
                'mes'       => 6,
                'variacion' => -0.2000,
            ]);

        $indice = CmIndiceIpc::where('anio', 2026)->where('mes', 6)->first();
        $this->assertEquals(0.998000, (float) $indice->factor_multiplicador);
    }

    public function test_acumulado_anual_se_recalcula_con_dos_meses()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 1, 'variacion' => 0.5000]);
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 2, 'variacion' => 0.3000]);

        $febrero = CmIndiceIpc::where('anio', 2026)->where('mes', 2)->first();
        $esperado = round((1.005 * 1.003 - 1) * 100, 4);
        $this->assertEqualsWithDelta($esperado, (float) $febrero->variacion_acumulada_anual, 0.0001);
    }

    public function test_acumulado_es_multiplicativo_no_aditivo()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 1, 'variacion' => 10.0]);
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 2, 'variacion' => 10.0]);

        $febrero = CmIndiceIpc::where('anio', 2026)->where('mes', 2)->first();
        $acumulado = (float) $febrero->variacion_acumulada_anual;
        $this->assertNotEquals(20.0, $acumulado);
        $esperadoMultiplicativo = round((1.10 * 1.10 - 1) * 100, 4);
        $this->assertEqualsWithDelta($esperadoMultiplicativo, $acumulado, 0.001);
    }

    public function test_get_indices_devuelve_12_meses()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 5, 'variacion' => 0.3]);

        $res = $this->actingAs($this->usuario)->getJson('/api/correccion-monetaria/indices/2026');

        $res->assertOk();
        $this->assertCount(12, $res->json('data'));
    }

    public function test_get_indices_marca_mes_cargado_correctamente()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 4, 'variacion' => 0.42]);

        $res = $this->actingAs($this->usuario)->getJson('/api/correccion-monetaria/indices/2026');
        $meses = collect($res->json('data'));

        $abril = $meses->firstWhere('mes', 4);
        $marzo = $meses->firstWhere('mes', 3);

        $this->assertTrue($abril['cargado']);
        $this->assertFalse($marzo['cargado']);
    }

    public function test_get_indices_mes_sin_datos_tiene_valores_nulos()
    {
        $res = $this->actingAs($this->usuario)->getJson('/api/correccion-monetaria/indices/2026');
        $enero = collect($res->json('data'))->firstWhere('mes', 1);

        $this->assertFalse($enero['cargado']);
        $this->assertNull($enero['variacion_mensual']);
        $this->assertNull($enero['factor_multiplicador']);
    }

    public function test_indices_es_idempotente_actualiza_en_lugar_de_duplicar()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 1, 'variacion' => 0.5]);
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 1, 'variacion' => 0.8]);

        $this->assertEquals(1, CmIndiceIpc::where('anio', 2026)->where('mes', 1)->count());
        $this->assertEquals(0.8000, (float) CmIndiceIpc::where('anio', 2026)->where('mes', 1)->value('variacion_mensual'));
    }

    public function test_indices_rechaza_mes_cero()
    {
        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 0, 'variacion' => 0.5]);
        $res->assertUnprocessable();
    }

    public function test_indices_rechaza_mes_trece()
    {
        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 13, 'variacion' => 0.5]);
        $res->assertUnprocessable();
    }

    public function test_indices_rechaza_variacion_mayor_a_50()
    {
        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 1, 'variacion' => 51]);
        $res->assertUnprocessable();
    }

    public function test_indices_rechaza_variacion_menor_a_menos_20()
    {
        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 1, 'variacion' => -21]);
        $res->assertUnprocessable();
    }

    public function test_indices_acepta_variacion_negativa_valida()
    {
        $res = $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 1, 'variacion' => -0.3]);
        $res->assertOk()->assertJson(['success' => true]);
    }

    public function test_indices_sin_autenticacion_devuelve_401()
    {
        $res = $this->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 1, 'variacion' => 0.5]);
        $res->assertUnauthorized();
    }

    public function test_indices_guarda_observacion()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', [
                'anio'        => 2026,
                'mes'         => 7,
                'variacion'   => 0.42,
                'observacion' => 'Dato revisado manualmente por INE',
            ]);

        $this->assertDatabaseHas('cm_indices_ipc', [
            'mes'         => 7,
            'observacion' => 'Dato revisado manualmente por INE',
        ]);
    }

    public function test_indices_registra_fuente_manual()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 2, 'variacion' => 0.3]);

        $indice = CmIndiceIpc::where('mes', 2)->first();
        $this->assertEquals('manual', $indice->fuente);
    }

    public function test_get_configuracion_devuelve_datos_de_empresa()
    {
        $res = $this->actingAs($this->usuario)->getJson('/api/correccion-monetaria/configuracion');

        $res->assertOk()->assertJson(['success' => true]);
        $data = $res->json('data');
        $this->assertTrue($data['aplica_cm']);
        $this->assertEquals('anual', $data['modalidad']);
        $this->assertEquals(12, $data['mes_cierre']);
    }

    public function test_put_configuracion_cambia_modalidad_a_mensual()
    {
        $res = $this->actingAs($this->usuario)
            ->putJson('/api/correccion-monetaria/configuracion', ['modalidad' => 'mensual']);

        $res->assertOk();
        $this->assertDatabaseHas('cm_configuracion_empresa', [
            'empresa_id' => $this->empresa->id,
            'modalidad'  => 'mensual',
        ]);
    }

    public function test_put_configuracion_cambia_mes_cierre()
    {
        $res = $this->actingAs($this->usuario)
            ->putJson('/api/correccion-monetaria/configuracion', ['mes_cierre' => 6]);

        $res->assertOk();
        $this->assertDatabaseHas('cm_configuracion_empresa', [
            'empresa_id' => $this->empresa->id,
            'mes_cierre' => 6,
        ]);
    }

    public function test_put_configuracion_rechaza_modalidad_invalida()
    {
        $res = $this->actingAs($this->usuario)
            ->putJson('/api/correccion-monetaria/configuracion', ['modalidad' => 'bimestral']);

        $res->assertUnprocessable();
    }

    public function test_estado_periodo_sin_ipc_no_puede_ejecutar()
    {
        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/estado/12/2026');

        $res->assertOk();
        $this->assertFalse($res->json('data.puede_ejecutar'));
        $this->assertFalse($res->json('data.tiene_ipc'));
    }

    public function test_estado_periodo_con_ipc_y_mes_correcto_puede_ejecutar()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 12, 'variacion' => 0.4]);

        $this->crearCuentasCMMinimas();

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/estado/12/2026');

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($res->json('data.tiene_ipc'));
    }

    public function test_estado_periodo_modalidad_anual_bloquea_mes_distinto_al_cierre()
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 6, 'variacion' => 0.4]);

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/estado/6/2026');

        $this->assertTrue($res->json('data.bloqueado_por_modalidad'));
        $this->assertFalse($res->json('data.puede_ejecutar'));
    }

    public function test_estado_periodo_modalidad_mensual_permite_cualquier_mes()
    {
        $this->actingAs($this->usuario)
            ->putJson('/api/correccion-monetaria/configuracion', ['modalidad' => 'mensual']);
        $this->actingAs($this->usuario)
            ->postJson('/api/correccion-monetaria/indices', ['anio' => 2026, 'mes' => 6, 'variacion' => 0.4]);

        $this->crearCuentasCMMinimas();

        $res = $this->actingAs($this->usuario)
            ->getJson('/api/correccion-monetaria/estado/6/2026');

        $this->assertFalse($res->json('data.bloqueado_por_modalidad'));
    }

    private function crearCuentasCMMinimas(): void
    {
        foreach (['811001', '811002', '821001', '821002', '311406'] as $codigo) {
            PlanCuenta::firstOrCreate(
                ['empresa_id' => $this->empresa->id, 'codigo' => $codigo],
                ['nombre' => "CM {$codigo}", 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]
            );
        }
    }
}
