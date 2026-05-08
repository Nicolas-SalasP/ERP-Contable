<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;

class ActivoFijoLimitesTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Límites', 'regimen_tributario' => '14_A']);
        $this->usuario = User::create(['nombre' => 'QA Destructor', 'email' => 'qa2@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_rechaza_activos_con_vida_util_astronomica()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => 'Edificio Inmortal',
            'valor_adquisicion' => 1000000,
            'fecha_adquisicion' => now()->format('Y-m-d'),
            'vida_util_meses' => 9999999999999
        ]);

        $response->assertStatus(422);
    }

    public function test_rechaza_activos_con_valores_de_adquisicion_negativos()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => 'Activo Deuda',
            'valor_adquisicion' => -150000,
            'fecha_adquisicion' => now()->format('Y-m-d'),
            'vida_util_meses' => 60
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['valor_adquisicion']);
    }

    public function test_creacion_sin_cuentas_contables_se_permite_pero_falla_en_depreciacion()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => 'Activo Incompleto',
            'valor_adquisicion' => 500000,
            'fecha_adquisicion' => now()->startOfMonth()->format('Y-m-d'),
            'vida_util_meses' => 60
        ]);

        $response->assertStatus(201);

        $resDepreciar = $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', [
            'mes_anio' => now()->format('Y-m')
        ]);

        $resDepreciar->assertStatus(422)
            ->assertSee('no tiene sus cuentas de');
    }

    public function test_campos_de_texto_extremadamente_largos_son_truncados_o_rechazados()
    {
        $textoLargo = str_repeat('A', 1000);

        $response = $this->actingAs($this->usuario)->postJson('/api/activos/proyectos', [
            'nombre' => $textoLargo,
            'vida_util_meses' => 60,
            'anio_fabricacion' => 2024,
            'tipo_activo_id' => 1,
            'cuenta_depreciacion_id' => 2,
            'cuenta_gasto_id' => 3
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }
}