<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Contabilidad\Models\DjEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cobertura HTTP de DJ 1879 (retenciones de honorarios): antes solo existian
 * Dj1879ConstruirTest/Dj1879FormatoTest, que ejercitan el servicio/formateador
 * directamente sin pasar nunca por el controller, el middleware
 * subscription.writable ni el permiso contabilidad.dj.procesar.
 */
class Dj1879Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Storage::fake('sii_xml');
    }

    private function crearHonorario(int $empresaId, string $rut = '11111111-1'): void
    {
        HonorarioRecibido::create([
            'empresa_id'         => $empresaId,
            'rut_prestador'      => $rut,
            'nombre_prestador'   => 'Prestador Test',
            'fecha'              => '2024-03-15',
            'monto_bruto'        => 500000,
            'tasa_retencion_pct' => 10.75,
            'monto_retencion'    => 53750,
            'monto_liquido'      => 446250,
        ]);
    }

    private function crearEmpresaConSuperAdmin(): array
    {
        [$empresa, $usuarioAdmin] = $this->crearEmpresaConAdmin();
        $usuarioAdmin->update(['rol_id' => $this->rolSuperAdmin->id]);
        return [$empresa, $usuarioAdmin];
    }

    public function test_generar_crea_dj_envio_con_estado_generado(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearHonorario($empresa->id);

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1879/generar', ['anio' => 2024]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('dj_envios', [
            'empresa_id' => $empresa->id,
            'codigo_dj'  => '1879',
            'anio'       => 2024,
            'estado'     => DjEnvio::ESTADO_GENERADO,
        ]);
    }

    public function test_generar_sin_honorarios_es_rechazado(): void
    {
        [, $usuario] = $this->crearEmpresaConSuperAdmin();

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1879/generar', ['anio' => 2024]);

        $response->assertStatus(422);
    }

    public function test_validar_retorna_valido_true_con_datos_correctos(): void
    {
        [, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearHonorario($usuario->empresa_activa_id);

        $generar = $this->actingAs($usuario)->postJson('/api/dj/1879/generar', ['anio' => 2024]);
        $generar->assertStatus(201);
        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)->postJson("/api/dj/1879/{$envioId}/validar");

        $response->assertStatus(200)->assertJson(['valido' => true]);
    }

    public function test_descargar_retorna_archivo(): void
    {
        [, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearHonorario($usuario->empresa_activa_id);

        $generar = $this->actingAs($usuario)->postJson('/api/dj/1879/generar', ['anio' => 2024]);
        $generar->assertStatus(201);
        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)->get("/api/dj/1879/{$envioId}/descargar");

        $response->assertStatus(200);
    }

    public function test_confirmar_presentacion_actualiza_estado(): void
    {
        [, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearHonorario($usuario->empresa_activa_id);

        $generar = $this->actingAs($usuario)->postJson('/api/dj/1879/generar', ['anio' => 2024]);
        $generar->assertStatus(201);
        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)
            ->postJson("/api/dj/1879/{$envioId}/confirmar-presentacion", ['folio_presentacion' => '999']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('dj_envios', [
            'id'                 => $envioId,
            'estado'             => DjEnvio::ESTADO_PRESENTADO,
            'folio_presentacion' => '999',
        ]);
    }

    public function test_rutas_requieren_autenticacion(): void
    {
        $this->postJson('/api/dj/1879/generar', ['anio' => 2024])->assertStatus(401);
        $this->getJson('/api/dj/1879/')->assertStatus(401);
        $this->postJson('/api/dj/1879/999/validar')->assertStatus(401);
        $this->getJson('/api/dj/1879/999/descargar')->assertStatus(401);
        $this->postJson('/api/dj/1879/999/confirmar-presentacion')->assertStatus(401);
    }

    public function test_usuario_empresa_b_no_puede_acceder_a_dj_envio_de_empresa_a(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConSuperAdmin();
        $this->crearHonorario($empresaA->id);
        $this->actingAs($usuarioA)->postJson('/api/dj/1879/generar', ['anio' => 2024]);
        $envioA = DjEnvio::where('empresa_id', $empresaA->id)->first();

        [, $usuarioB] = $this->crearEmpresaConSuperAdmin();
        $this->actingAs($usuarioB);

        $status = $this->postJson("/api/dj/1879/{$envioA->id}/validar")->status();
        $this->assertContains($status, [403, 404]);
    }
}
