<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Comercial\Models\TasaRetencionHonorarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/** HonorariosController no tenia ningun test HTTP (0% de coverage). */
class HonorariosControllerTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected $empresa;

    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = $this->crearEmpresa(['rut' => '77.777.777-7', 'razon_social' => 'Honorarios SpA']);
        $this->usuario = $this->crearUsuario($this->empresa, $this->rolSuperAdmin, [
            'nombre' => 'Admin Honorarios',
            'email' => 'h@honorarios.cl',
        ]);
        TasaRetencionHonorarios::create(['anio' => 2026, 'tasa_pct' => 15.25]);
    }

    public function test_store_registra_honorario_valido()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/honorarios', [
            'rut_prestador' => '11.111.111-1',
            'nombre_prestador' => 'Juan Perez',
            'fecha' => '2026-06-15',
            'monto_bruto' => 500000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('honorarios_recibidos', [
            'empresa_id' => $this->empresa->id,
            'nombre_prestador' => 'Juan Perez',
            'monto_bruto' => 500000,
            'tasa_retencion_pct' => 15.25,
            'monto_retencion' => 76250,
        ]);
    }

    public function test_store_rechaza_sin_rut_prestador_con_422()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/honorarios', [
            'nombre_prestador' => 'Juan Perez',
            'fecha' => '2026-06-15',
            'monto_bruto' => 500000,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rut_prestador']);
    }

    public function test_index_lista_solo_honorarios_de_la_empresa_activa()
    {
        HonorarioRecibido::create([
            'empresa_id' => $this->empresa->id, 'rut_prestador' => '11.111.111-1',
            'nombre_prestador' => 'Propio', 'fecha' => '2026-06-15', 'monto_bruto' => 100000,
            'tasa_retencion_pct' => 15.25, 'monto_retencion' => 15250, 'monto_liquido' => 84750,
        ]);

        $otraEmpresa = $this->crearEmpresa(['rut' => '88.888.888-8', 'razon_social' => 'Rival Honorarios']);
        HonorarioRecibido::create([
            'empresa_id' => $otraEmpresa->id, 'rut_prestador' => '22.222.222-2',
            'nombre_prestador' => 'Ajeno', 'fecha' => '2026-06-15', 'monto_bruto' => 999999,
            'tasa_retencion_pct' => 15.25, 'monto_retencion' => 152500, 'monto_liquido' => 847499,
        ]);

        $response = $this->actingAs($this->usuario)->getJson('/api/honorarios');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Propio', $data[0]['nombre_prestador']);
    }

    public function test_index_filtra_por_mes_y_anio()
    {
        HonorarioRecibido::create([
            'empresa_id' => $this->empresa->id, 'rut_prestador' => '11.111.111-1',
            'nombre_prestador' => 'De Enero', 'fecha' => '2026-01-15', 'monto_bruto' => 100000,
            'tasa_retencion_pct' => 15.25, 'monto_retencion' => 15250, 'monto_liquido' => 84750,
        ]);
        HonorarioRecibido::create([
            'empresa_id' => $this->empresa->id, 'rut_prestador' => '11.111.111-1',
            'nombre_prestador' => 'De Marzo', 'fecha' => '2026-03-15', 'monto_bruto' => 200000,
            'tasa_retencion_pct' => 15.25, 'monto_retencion' => 30500, 'monto_liquido' => 169500,
        ]);

        $response = $this->actingAs($this->usuario)->getJson('/api/honorarios?mes=1&anio=2026');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('De Enero', $data[0]['nombre_prestador']);
    }

    public function test_destroy_elimina_honorario_propio()
    {
        $honorario = HonorarioRecibido::create([
            'empresa_id' => $this->empresa->id, 'rut_prestador' => '11.111.111-1',
            'nombre_prestador' => 'A Borrar', 'fecha' => '2026-06-15', 'monto_bruto' => 100000,
            'tasa_retencion_pct' => 15.25, 'monto_retencion' => 15250, 'monto_liquido' => 84750,
        ]);

        $response = $this->actingAs($this->usuario)->deleteJson("/api/honorarios/{$honorario->id}");

        $response->assertStatus(200);
        // Soft delete: la fila sigue en la tabla, solo se marca deleted_at.
        $this->assertSoftDeleted('honorarios_recibidos', ['id' => $honorario->id]);
    }

    public function test_destroy_de_honorario_de_otra_empresa_devuelve_404()
    {
        // El route-model-binding ya aplica HasEmpresaScope: un honorario ajeno ni siquiera
        // se resuelve (404) -- el controller ni llega al abort_unless(403) explicito.
        $otraEmpresa = $this->crearEmpresa(['rut' => '88.888.888-8', 'razon_social' => 'Rival Honorarios 2']);
        $honorarioAjeno = HonorarioRecibido::create([
            'empresa_id' => $otraEmpresa->id, 'rut_prestador' => '22.222.222-2',
            'nombre_prestador' => 'Ajeno', 'fecha' => '2026-06-15', 'monto_bruto' => 100000,
            'tasa_retencion_pct' => 15.25, 'monto_retencion' => 15250, 'monto_liquido' => 84750,
        ]);

        $response = $this->actingAs($this->usuario)->deleteJson("/api/honorarios/{$honorarioAjeno->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('honorarios_recibidos', ['id' => $honorarioAjeno->id, 'deleted_at' => null]);
    }
}
