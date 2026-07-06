<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Models\DjEnvio;
use App\Domains\Contabilidad\Models\TasaPpmPropyme;
use App\Domains\Core\Models\Propietario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cobertura HTTP de DJ 1947 (Propyme Transparente 14D N°8): antes solo
 * existian Dj1947ConstruirTest/Dj1947FormatoTest, que ejercitan el
 * servicio/formateador directamente sin pasar nunca por el controller, el
 * middleware subscription.writable ni el permiso contabilidad.dj.procesar.
 */
class Dj1947Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Storage::fake('sii_xml');

        TasaPpmPropyme::create([
            'anio'                   => 2026,
            'tasa_base_pct'          => 0.125,
            'tasa_sobre_50000uf_pct' => 0.250,
        ]);
    }

    private function crearEmpresa14D8ConSuperAdmin(): array
    {
        [$empresa, $usuarioAdmin] = $this->crearEmpresaConAdmin(['regimen_tributario' => '14_D8']);
        $usuarioAdmin->update(['rol_id' => $this->rolSuperAdmin->id]);
        return [$empresa, $usuarioAdmin];
    }

    private function insertarDte(int $empresaId, int $folio, string $fechaEmision, float $montoNeto): void
    {
        DB::table('sii_dte_emitido')->insert([
            'empresa_id'            => $empresaId,
            'tipo_dte'              => 33,
            'folio'                 => $folio,
            'fecha_emision'         => $fechaEmision,
            'estado'                => 'ACEPTADO',
            'monto_neto'            => $montoNeto,
            'monto_exento'          => 0,
            'monto_total'           => $montoNeto * 1.19,
            'emisor_rut'            => '76000001-K',
            'emisor_razon_social'   => 'Empresa Test SA',
            'receptor_rut'          => '11111111-1',
            'receptor_razon_social' => 'Cliente Test',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    private function crearPropietario(int $empresaId, string $rut, string $nombre, float $pct): Propietario
    {
        return Propietario::withoutGlobalScopes()->create([
            'empresa_id'               => $empresaId,
            'rut'                      => $rut,
            'nombre'                   => $nombre,
            'porcentaje_participacion' => $pct,
        ]);
    }

    public function test_generar_crea_dj_envio_con_estado_generado(): void
    {
        [$empresa, $usuario] = $this->crearEmpresa14D8ConSuperAdmin();
        $this->insertarDte($empresa->id, 1, '2026-04-15', 1_000_000.0);
        $this->crearPropietario($empresa->id, '11111111-1', 'Socio A', 100.0);

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1947/generar', ['anio' => 2026]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('dj_envios', [
            'empresa_id' => $empresa->id,
            'codigo_dj'  => '1947',
            'anio'       => 2026,
            'estado'     => DjEnvio::ESTADO_GENERADO,
        ]);
    }

    public function test_generar_para_empresa_que_no_es_14d8_es_rechazado(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin(['regimen_tributario' => '14_D3']);
        $usuario->update(['rol_id' => $this->rolSuperAdmin->id]);

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1947/generar', ['anio' => 2026]);

        $response->assertStatus(422);
    }

    public function test_validar_retorna_valido_true_con_datos_correctos(): void
    {
        [$empresa, $usuario] = $this->crearEmpresa14D8ConSuperAdmin();
        $this->insertarDte($empresa->id, 1, '2026-04-15', 1_000_000.0);
        $this->crearPropietario($empresa->id, '11111111-1', 'Socio A', 100.0);

        $generar = $this->actingAs($usuario)->postJson('/api/dj/1947/generar', ['anio' => 2026]);
        $generar->assertStatus(201);
        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)->postJson("/api/dj/1947/{$envioId}/validar");

        $response->assertStatus(200)->assertJson(['valido' => true]);
    }

    public function test_descargar_retorna_archivo(): void
    {
        [$empresa, $usuario] = $this->crearEmpresa14D8ConSuperAdmin();
        $this->insertarDte($empresa->id, 1, '2026-04-15', 1_000_000.0);
        $this->crearPropietario($empresa->id, '11111111-1', 'Socio A', 100.0);

        $generar = $this->actingAs($usuario)->postJson('/api/dj/1947/generar', ['anio' => 2026]);
        $generar->assertStatus(201);
        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)->get("/api/dj/1947/{$envioId}/descargar");

        $response->assertStatus(200);
    }

    public function test_confirmar_presentacion_actualiza_estado(): void
    {
        [$empresa, $usuario] = $this->crearEmpresa14D8ConSuperAdmin();
        $this->insertarDte($empresa->id, 1, '2026-04-15', 1_000_000.0);
        $this->crearPropietario($empresa->id, '11111111-1', 'Socio A', 100.0);

        $generar = $this->actingAs($usuario)->postJson('/api/dj/1947/generar', ['anio' => 2026]);
        $generar->assertStatus(201);
        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)
            ->postJson("/api/dj/1947/{$envioId}/confirmar-presentacion", ['folio_presentacion' => '888']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('dj_envios', [
            'id'                 => $envioId,
            'estado'             => DjEnvio::ESTADO_PRESENTADO,
            'folio_presentacion' => '888',
        ]);
    }

    public function test_rutas_requieren_autenticacion(): void
    {
        $this->postJson('/api/dj/1947/generar', ['anio' => 2026])->assertStatus(401);
        $this->getJson('/api/dj/1947/')->assertStatus(401);
        $this->postJson('/api/dj/1947/999/validar')->assertStatus(401);
        $this->getJson('/api/dj/1947/999/descargar')->assertStatus(401);
        $this->postJson('/api/dj/1947/999/confirmar-presentacion')->assertStatus(401);
    }

    public function test_usuario_empresa_b_no_puede_acceder_a_dj_envio_de_empresa_a(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresa14D8ConSuperAdmin();
        $this->insertarDte($empresaA->id, 1, '2026-04-15', 1_000_000.0);
        $this->crearPropietario($empresaA->id, '11111111-1', 'Socio A', 100.0);
        $this->actingAs($usuarioA)->postJson('/api/dj/1947/generar', ['anio' => 2026]);
        $envioA = DjEnvio::where('empresa_id', $empresaA->id)->first();

        [, $usuarioB] = $this->crearEmpresa14D8ConSuperAdmin();
        $this->actingAs($usuarioB);

        $status = $this->postJson("/api/dj/1947/{$envioA->id}/validar")->status();
        $this->assertContains($status, [403, 404]);
    }
}
