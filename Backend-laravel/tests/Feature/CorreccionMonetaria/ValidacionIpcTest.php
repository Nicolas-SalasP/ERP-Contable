<?php

namespace Tests\Feature\CorreccionMonetaria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionEmpresa;
use App\Domains\CorreccionMonetaria\Services\CorreccionMonetariaService;
use Exception;

class ValidacionIpcTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuarioSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa] = $this->crearEmpresaConAdmin();
        $this->usuarioSuperAdmin = $this->crearUsuario($this->empresa, $this->rolSuperAdmin);

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

    public function test_rechaza_variacion_ipc_fuera_de_rango(): void
    {
        $res = $this->actingAs($this->usuarioSuperAdmin)
            ->postJson('/api/correccion-monetaria/indices', [
                'anio'      => 2026,
                'mes'       => 3,
                'variacion' => 100.0,
            ]);

        $res->assertStatus(422);
    }

    public function test_rechaza_variacion_ipc_negativa_extrema(): void
    {
        $res = $this->actingAs($this->usuarioSuperAdmin)
            ->postJson('/api/correccion-monetaria/indices', [
                'anio'      => 2026,
                'mes'       => 4,
                'variacion' => -35.0,
            ]);

        $res->assertStatus(422);
    }

    public function test_acepta_variacion_ipc_normal(): void
    {
        $res = $this->actingAs($this->usuarioSuperAdmin)
            ->postJson('/api/correccion-monetaria/indices', [
                'anio'      => 2026,
                'mes'       => 5,
                'variacion' => 0.5,
            ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('cm_indices_ipc', [
            'anio'              => 2026,
            'mes'               => 5,
            'variacion_mensual' => 0.5,
        ]);
    }

    public function test_acepta_variacion_ipc_en_limite_superior(): void
    {
        $res = $this->actingAs($this->usuarioSuperAdmin)
            ->postJson('/api/correccion-monetaria/indices', [
                'anio'      => 2026,
                'mes'       => 6,
                'variacion' => 30.0,
            ]);

        $res->assertOk()->assertJson(['success' => true]);
    }

    public function test_acepta_variacion_ipc_en_limite_inferior(): void
    {
        $res = $this->actingAs($this->usuarioSuperAdmin)
            ->postJson('/api/correccion-monetaria/indices', [
                'anio'      => 2026,
                'mes'       => 7,
                'variacion' => -30.0,
            ]);

        $res->assertOk()->assertJson(['success' => true]);
    }

    public function test_servicio_lanza_excepcion_directa_para_variacion_extrema(): void
    {
        $service = app(CorreccionMonetariaService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/rango permitido/');

        $service->guardarIndice($this->usuarioSuperAdmin->id, 2026, 1, 100.0);
    }
}
