<?php

namespace Tests\Feature\Sii\Configuracion;

use App\Domains\Core\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiConfiguracionControllerTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private Empresa $empresa;
    private $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();
    }

    public function test_get_devuelve_campos_sii_de_la_empresa(): void
    {
        $this->empresa->update([
            'giro_emisor'             => 'Venta al por menor',
            'codigo_actividad_sii'    => 471910,
            'comuna'                  => 'Santiago',
            'ambiente_sii'            => 'certificacion',
            'email_intercambio_sii'   => 'intercambio@acme.cl',
        ]);

        Sanctum::actingAs($this->usuario);

        $this->getJson('/api/sii/configuracion')
            ->assertStatus(200)
            ->assertJson([
                'giro_emisor'           => 'Venta al por menor',
                'codigo_actividad_sii'  => 471910,
                'comuna'                => 'Santiago',
                'ambiente_sii'          => 'certificacion',
                'email_intercambio_sii' => 'intercambio@acme.cl',
            ]);
    }

    public function test_put_actualiza_campos_validos(): void
    {
        Sanctum::actingAs($this->usuario);

        $payload = [
            'giro_emisor'           => 'Servicios contables',
            'codigo_actividad_sii'  => 692000,
            'comuna'                => 'Providencia',
            'ciudad'                => 'Santiago',
            'ambiente_sii'          => 'produccion',
            'email_intercambio_sii' => 'sii@empresa.cl',
        ];

        $this->putJson('/api/sii/configuracion', $payload)
            ->assertStatus(200)
            ->assertJson($payload);

        $this->empresa->refresh();
        $this->assertSame('Servicios contables', $this->empresa->giro_emisor);
        $this->assertSame('produccion', $this->empresa->ambiente_sii);
    }

    public function test_put_rechaza_ambiente_invalido_con_422(): void
    {
        Sanctum::actingAs($this->usuario);

        $this->putJson('/api/sii/configuracion', [
            'ambiente_sii' => 'staging', // no es certificacion/produccion
        ])->assertStatus(422)
          ->assertJsonValidationErrors('ambiente_sii');
    }

    public function test_put_rechaza_email_malformado_con_422(): void
    {
        Sanctum::actingAs($this->usuario);

        $this->putJson('/api/sii/configuracion', [
            'ambiente_sii'          => 'certificacion',
            'email_intercambio_sii' => 'no-es-un-email',
        ])->assertStatus(422)
          ->assertJsonValidationErrors('email_intercambio_sii');
    }

    public function test_put_no_acepta_empresa_id_del_payload_mass_assignment(): void
    {
        [$otraEmpresa] = $this->crearEmpresaConAdmin();

        Sanctum::actingAs($this->usuario);

        $this->putJson('/api/sii/configuracion', [
            'empresa_id'   => $otraEmpresa->id, // intento de toma de control
            'ambiente_sii' => 'produccion',
        ])->assertStatus(200);

        // Mi empresa cambio el ambiente, no la otra.
        $this->empresa->refresh();
        $otraEmpresa->refresh();

        $this->assertSame('produccion', $this->empresa->ambiente_sii);
        $this->assertSame('certificacion', $otraEmpresa->ambiente_sii);
    }

    public function test_get_requiere_autenticacion_401(): void
    {
        $this->getJson('/api/sii/configuracion')->assertStatus(401);
    }

    public function test_put_requiere_autenticacion_401(): void
    {
        $this->putJson('/api/sii/configuracion', ['ambiente_sii' => 'certificacion'])
            ->assertStatus(401);
    }

    public function test_aislamiento_multitenant_no_permite_ver_config_de_otra_empresa(): void
    {
        [$otraEmpresa] = $this->crearEmpresaConAdmin();
        $otraEmpresa->update(['giro_emisor' => 'Giro Espia']);

        Sanctum::actingAs($this->usuario);

        $response = $this->getJson('/api/sii/configuracion');

        $response->assertStatus(200);
        $this->assertNotEquals('Giro Espia', $response->json('giro_emisor'));
    }
}
