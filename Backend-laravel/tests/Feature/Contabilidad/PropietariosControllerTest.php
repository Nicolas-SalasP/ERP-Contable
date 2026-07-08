<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Regresión de seguridad: el grupo de rutas empresa/propietarios usaba un solo
 * middleware `permiso:contabilidad.ver` para TODO el grupo (incluyendo
 * POST/PUT/DELETE), permitiendo que un usuario de solo lectura escalara
 * privilegios y modificara propietarios (dato usado en DJ 1947 / Propyme).
 */
class PropietariosControllerTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private Empresa $empresa;
    private User $usuarioSoloLectura;
    private User $usuarioConEscritura;
    private string $ruta = '/api/empresa/propietarios';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = Empresa::create(['rut' => '76.200.001-1', 'razon_social' => 'Propietarios Test SpA']);

        // Auditor → tiene contabilidad.ver pero NO contabilidad.crear (solo lectura).
        $this->usuarioSoloLectura = User::create([
            'nombre' => 'Auditor Solo Lectura',
            'email' => 'auditor.propietarios@test.cl',
            'password' => bcrypt('x'),
            'empresa_id' => $this->empresa->id,
            'empresa_activa_id' => $this->empresa->id,
            'rol_id' => $this->rolAuditor->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        // Contador → tiene contabilidad.ver y contabilidad.crear.
        $this->usuarioConEscritura = User::create([
            'nombre' => 'Contador Con Escritura',
            'email' => 'contador.propietarios@test.cl',
            'password' => bcrypt('x'),
            'empresa_id' => $this->empresa->id,
            'empresa_activa_id' => $this->empresa->id,
            'rol_id' => $this->rolContador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
    }

    public function test_usuario_solo_lectura_puede_listar_propietarios(): void
    {
        $this->actingAs($this->usuarioSoloLectura)
            ->getJson($this->ruta)
            ->assertStatus(200);
    }

    public function test_usuario_solo_lectura_no_puede_crear_propietario(): void
    {
        $this->actingAs($this->usuarioSoloLectura)
            ->postJson($this->ruta, [
                'rut' => '11.111.111-1',
                'nombre' => 'Propietario Intruso',
                'porcentaje_participacion' => 50,
            ])
            ->assertStatus(403);
    }

    public function test_usuario_solo_lectura_no_puede_editar_propietario(): void
    {
        $propietario = \App\Domains\Core\Models\Propietario::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '22.222.222-2',
            'nombre' => 'Propietario Original',
            'porcentaje_participacion' => 100,
        ]);

        $this->actingAs($this->usuarioSoloLectura)
            ->putJson($this->ruta . '/' . $propietario->id, ['nombre' => 'Nombre Hackeado'])
            ->assertStatus(403);
    }

    public function test_usuario_solo_lectura_no_puede_eliminar_propietario(): void
    {
        $propietario = \App\Domains\Core\Models\Propietario::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '33.333.333-3',
            'nombre' => 'Propietario A Borrar',
            'porcentaje_participacion' => 100,
        ]);

        $this->actingAs($this->usuarioSoloLectura)
            ->deleteJson($this->ruta . '/' . $propietario->id)
            ->assertStatus(403);
    }

    public function test_usuario_con_permiso_crear_puede_gestionar_propietarios_completo(): void
    {
        $this->actingAs($this->usuarioConEscritura)
            ->getJson($this->ruta)
            ->assertStatus(200);

        $creado = $this->actingAs($this->usuarioConEscritura)
            ->postJson($this->ruta, [
                'rut' => '44.444.444-4',
                'nombre' => 'Propietario Legítimo',
                'porcentaje_participacion' => 100,
            ])
            ->assertStatus(201);

        $id = $creado->json('data.id');

        $this->actingAs($this->usuarioConEscritura)
            ->putJson($this->ruta . '/' . $id, ['nombre' => 'Propietario Editado'])
            ->assertStatus(200);

        $this->actingAs($this->usuarioConEscritura)
            ->deleteJson($this->ruta . '/' . $id)
            ->assertStatus(200);
    }
}
