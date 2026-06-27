<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Services\Dj\Dj1837Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class Dj1837Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearHonorarioSinRetencion(int $empresaId, string $rut, string $nombre, int $monto, string $fecha = '2026-04-10'): void
    {
        HonorarioRecibido::create([
            'empresa_id'         => $empresaId,
            'rut_prestador'      => $rut,
            'nombre_prestador'   => $nombre,
            'fecha'              => $fecha,
            'monto_bruto'        => $monto,
            'tasa_retencion_pct' => 0,
            'monto_retencion'    => 0,
            'monto_liquido'      => $monto,
        ]);
    }

    public function test_construye_una_linea_por_prestador_sin_retencion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->crearHonorarioSinRetencion($empresa->id, '11111111-1', 'Prestador Sin Ret Uno', 400_000);
        $this->crearHonorarioSinRetencion($empresa->id, '22222222-2', 'Prestador Sin Ret Dos', 200_000);

        $service = new Dj1837Service();
        $data    = $service->construir($empresa->id, 2026);

        $this->assertCount(2, $data->lineas);
    }

    public function test_excluye_honorarios_con_retencion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        // Sin retención — debe incluirse
        $this->crearHonorarioSinRetencion($empresa->id, '11111111-1', 'Sin Retención', 300_000);

        // Con retención — NO debe incluirse
        HonorarioRecibido::create([
            'empresa_id'         => $empresa->id,
            'rut_prestador'      => '99999999-9',
            'nombre_prestador'   => 'Con Retención',
            'fecha'              => '2026-05-01',
            'monto_bruto'        => 500_000,
            'tasa_retencion_pct' => 10.75,
            'monto_retencion'    => 53_750,
            'monto_liquido'      => 446_250,
        ]);

        $service = new Dj1837Service();
        $data    = $service->construir($empresa->id, 2026);

        $this->assertCount(1, $data->lineas);
        $this->assertEquals('11111111-1', $data->lineas[0]->campos['rut_prestador']);
    }

    public function test_agrega_varios_boletas_del_mismo_prestador(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        foreach (['2026-01-10', '2026-06-20', '2026-09-05'] as $fecha) {
            $this->crearHonorarioSinRetencion($empresa->id, '33333333-3', 'Prestador Acumulado', 100_000, $fecha);
        }

        $service = new Dj1837Service();
        $data    = $service->construir($empresa->id, 2026);

        $this->assertCount(1, $data->lineas);
        $campos = $data->lineas[0]->campos;
        $this->assertEquals(300_000, $campos['monto_bruto_total']);
        $this->assertEquals(3, $campos['num_documentos']);
    }

    public function test_lanza_error_si_no_hay_honorarios_sin_retencion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        // Solo hay honorarios CON retención
        HonorarioRecibido::create([
            'empresa_id'         => $empresa->id,
            'rut_prestador'      => '44444444-4',
            'nombre_prestador'   => 'Con Retención',
            'fecha'              => '2026-03-01',
            'monto_bruto'        => 200_000,
            'tasa_retencion_pct' => 10.75,
            'monto_retencion'    => 21_500,
            'monto_liquido'      => 178_500,
        ]);

        $this->expectException(DjException::class);

        $service = new Dj1837Service();
        $service->construir($empresa->id, 2026);
    }

    public function test_valida_sin_errores_con_datos_correctos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->crearHonorarioSinRetencion($empresa->id, '55555555-5', 'Prestador OK', 250_000);

        $service = new Dj1837Service();
        $data    = $service->construir($empresa->id, 2026);
        $errores = $service->validar($data);

        $this->assertEmpty($errores);
    }

    public function test_detecta_monto_bruto_cero_en_validacion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $data = new DjData(
            codigoDj:  '1837',
            empresaId: $empresa->id,
            anio:      2026,
            cabecera: ['rut_empresa' => $empresa->rut, 'razon_social' => $empresa->razon_social],
            lineas: [
                new DjLineaData(['rut_prestador' => '11111111-1', 'nombre_prestador' => 'Cero', 'monto_bruto_total' => 0, 'num_documentos' => 1]),
            ],
        );

        $service = new Dj1837Service();
        $errores = $service->validar($data);

        $this->assertNotEmpty($errores);
        $this->assertTrue(collect($errores)->contains(fn ($e) => str_contains($e, 'monto bruto es cero')));
    }

    public function test_archivo_contiene_registros_A_D_T(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->crearHonorarioSinRetencion($empresa->id, '66666666-6', 'Prestador Formato', 150_000);

        $service = new Dj1837Service();
        $data    = $service->construir($empresa->id, 2026);
        $archivo = $service->formatearArchivo($data);

        $this->assertTrue(str_starts_with($archivo, 'A;'));
        $this->assertStringContainsString("\nD;", $archivo);
        $this->assertStringContainsString("\nT;", $archivo);
    }

    public function test_multitenant_no_cruza_empresas(): void
    {
        [$empresa1] = $this->crearEmpresaConAdmin();
        [$empresa2] = $this->crearEmpresaConAdmin();

        $this->crearHonorarioSinRetencion($empresa1->id, '77777777-7', 'Solo Empresa 1', 100_000);

        $this->expectException(DjException::class);

        $service = new Dj1837Service();
        $service->construir($empresa2->id, 2026);
    }

    private function crearEmpresaConSuperAdmin(): array
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $usuario->update(['rol_id' => $this->rolSuperAdmin->id]);
        return [$empresa, $usuario];
    }

    // HTTP layer ----------------------------------------------------------

    public function test_http_generar_sin_honorarios_devuelve_422(): void
    {
        [, $usuario] = $this->crearEmpresaConSuperAdmin();

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1837/generar', ['anio' => 2026]);

        $response->assertStatus(422)
            ->assertJsonStructure(['mensaje']);
    }

    public function test_http_generar_con_honorarios_devuelve_201(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();

        $this->crearHonorarioSinRetencion($empresa->id, '88888888-8', 'Prestador HTTP', 300_000);

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1837/generar', ['anio' => 2026]);

        $response->assertStatus(201)
            ->assertJsonPath('data.cantidad_registros', 1);
    }

    public function test_http_multitenant_usuario_solo_ve_sus_envios(): void
    {
        [$empresa1, $usuario1] = $this->crearEmpresaConSuperAdmin();
        [, $usuario2]          = $this->crearEmpresaConSuperAdmin();

        $this->crearHonorarioSinRetencion($empresa1->id, '99999999-9', 'Solo Empresa 1', 200_000);
        $this->actingAs($usuario1)->postJson('/api/dj/1837/generar', ['anio' => 2026]);

        $response = $this->actingAs($usuario2)->getJson('/api/dj/1837');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
