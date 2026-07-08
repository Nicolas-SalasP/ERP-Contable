<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Services\Dj\Dj1926Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class Dj1926Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearGastoRechazado(int $empresaId, string $codigo, string $nombre, int $monto, string $fecha = '2026-05-15'): void
    {
        $cuenta = PlanCuenta::create([
            'empresa_id'        => $empresaId,
            'codigo'            => $codigo,
            'nombre'            => $nombre,
            'tipo'              => 'GASTO',
            'nivel'             => 5,
            'imputable'         => true,
            'activo'            => true,
            'es_gasto_rechazado' => true,
        ]);

        $asiento = AsientoContable::create([
            'empresa_id'        => $empresaId,
            'numero_comprobante' => uniqid('COMP-'),
            'fecha'             => $fecha,
            'glosa'             => 'Gasto rechazado test',
            'tipo_asiento'      => 'manual',
            'origen_modulo'     => 'contabilidad',
            'estado'            => 'MAYORIZADO',
        ]);

        DetalleAsiento::create([
            'asiento_id'      => $asiento->id,
            'cuenta_contable' => $cuenta->codigo,
            'fecha'           => $fecha,
            'tipo_operacion'  => 'DEBE',
            'debe'            => $monto,
            'haber'           => 0,
            'descripcion_extensa' => 'Gasto rechazado test',
        ]);
    }

    public function test_construye_una_linea_por_cuenta_rechazada(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->crearGastoRechazado($empresa->id, '810101', 'Gastos Personales Socios', 500_000);
        $this->crearGastoRechazado($empresa->id, '810102', 'Multas y Sanciones', 100_000);

        $service = new Dj1926Service();
        $data    = $service->construir($empresa->id, 2026);

        $this->assertCount(2, $data->lineas);
    }

    public function test_agrega_montos_de_varios_asientos_de_la_misma_cuenta(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $cuenta = PlanCuenta::create([
            'empresa_id'        => $empresa->id,
            'codigo'            => '810201',
            'nombre'            => 'Entretenimiento No Deducible',
            'tipo'              => 'GASTO',
            'nivel'             => 5,
            'imputable'         => true,
            'activo'            => true,
            'es_gasto_rechazado' => true,
        ]);

        foreach (['2026-01-10', '2026-04-20', '2026-08-05'] as $fecha) {
            $asiento = AsientoContable::create([
                'empresa_id'         => $empresa->id,
                'numero_comprobante' => uniqid('COMP-'),
                'fecha'              => $fecha,
                'glosa'              => 'Gasto rechazado',
                'tipo_asiento'       => 'manual',
                'origen_modulo'      => 'contabilidad',
                'estado'             => 'MAYORIZADO',
            ]);
            DetalleAsiento::create([
                'asiento_id'         => $asiento->id,
                'cuenta_contable'    => $cuenta->codigo,
                'fecha'              => $fecha,
                'tipo_operacion'     => 'DEBE',
                'debe'               => 200_000,
                'haber'              => 0,
                'descripcion_extensa' => 'Test',
            ]);
        }

        $service = new Dj1926Service();
        $data    = $service->construir($empresa->id, 2026);

        $this->assertCount(1, $data->lineas);
        $this->assertEquals(600_000, $data->lineas[0]->campos['monto_total']);
    }

    public function test_lanza_error_si_no_hay_gastos_rechazados(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->expectException(DjException::class);

        $service = new Dj1926Service();
        $service->construir($empresa->id, 2026);
    }

    public function test_ignora_asientos_que_no_son_mayorizado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $cuenta = PlanCuenta::create([
            'empresa_id'        => $empresa->id,
            'codigo'            => '810301',
            'nombre'            => 'Gastos No Deducibles',
            'tipo'              => 'GASTO',
            'nivel'             => 5,
            'imputable'         => true,
            'activo'            => true,
            'es_gasto_rechazado' => true,
        ]);

        // Asiento en BORRADOR — no debe incluirse
        $asiento = AsientoContable::create([
            'empresa_id'         => $empresa->id,
            'numero_comprobante' => uniqid('COMP-'),
            'fecha'              => '2026-03-01',
            'glosa'              => 'Borrador',
            'tipo_asiento'       => 'manual',
            'origen_modulo'      => 'contabilidad',
            'estado'             => 'BORRADOR',
        ]);
        DetalleAsiento::create([
            'asiento_id'         => $asiento->id,
            'cuenta_contable'    => $cuenta->codigo,
            'fecha'              => '2026-03-01',
            'tipo_operacion'     => 'DEBE',
            'debe'               => 300_000,
            'haber'              => 0,
            'descripcion_extensa' => 'Test borrador',
        ]);

        $this->expectException(DjException::class);

        $service = new Dj1926Service();
        $service->construir($empresa->id, 2026);
    }

    public function test_valida_sin_errores_con_datos_correctos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->crearGastoRechazado($empresa->id, '810401', 'Multa SII', 50_000);

        $service = new Dj1926Service();
        $data    = $service->construir($empresa->id, 2026);
        $errores = $service->validar($data);

        $this->assertEmpty($errores);
    }

    public function test_detecta_monto_cero_en_validacion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $data = new DjData(
            codigoDj:  '1926',
            empresaId: $empresa->id,
            anio:      2026,
            cabecera: ['rut_empresa' => $empresa->rut, 'razon_social' => $empresa->razon_social],
            lineas: [
                new DjLineaData(['codigo_cuenta' => '810101', 'nombre_cuenta' => 'Gastos Socios', 'monto_total' => 0]),
            ],
        );

        $service = new Dj1926Service();
        $errores = $service->validar($data);

        $this->assertNotEmpty($errores);
        $this->assertTrue(collect($errores)->contains(fn ($e) => str_contains($e, 'cero o negativo')));
    }

    public function test_archivo_contiene_registros_A_D_T(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->crearGastoRechazado($empresa->id, '810501', 'Gastos Rechazados', 80_000);

        $service = new Dj1926Service();
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

        // Solo empresa1 tiene gastos rechazados
        $this->crearGastoRechazado($empresa1->id, '810601', 'Multa', 100_000);

        $this->expectException(DjException::class);

        $service = new Dj1926Service();
        $service->construir($empresa2->id, 2026);
    }

    private function crearEmpresaConSuperAdmin(): array
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $usuario->update(['rol_id' => $this->rolSuperAdmin->id]);
        return [$empresa, $usuario];
    }

    // HTTP layer ----------------------------------------------------------

    public function test_http_generar_sin_gastos_rechazados_devuelve_422(): void
    {
        [, $usuario] = $this->crearEmpresaConSuperAdmin();

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1926/generar', ['anio' => 2026]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_http_generar_con_gastos_rechazados_devuelve_201(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();

        $this->crearGastoRechazado($empresa->id, '810701', 'Multa SII HTTP', 75_000);

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1926/generar', ['anio' => 2026]);

        $response->assertStatus(201)
            ->assertJsonPath('data.cantidad_registros', 1);
    }

    public function test_http_multitenant_usuario_solo_ve_sus_envios(): void
    {
        [$empresa1, $usuario1] = $this->crearEmpresaConSuperAdmin();
        [, $usuario2]          = $this->crearEmpresaConSuperAdmin();

        $this->crearGastoRechazado($empresa1->id, '810801', 'Gasto Solo E1', 50_000);
        $this->actingAs($usuario1)->postJson('/api/dj/1926/generar', ['anio' => 2026]);

        $response = $this->actingAs($usuario2)->getJson('/api/dj/1926');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
