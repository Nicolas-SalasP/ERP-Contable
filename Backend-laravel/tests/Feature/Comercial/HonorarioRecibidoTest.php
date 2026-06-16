<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\TasaRetencionHonorarios;
use App\Domains\Comercial\Services\Honorarios\RegistrarHonorarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class HonorarioRecibidoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private RegistrarHonorarioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(RegistrarHonorarioService::class);
    }

    public function test_registra_honorario_y_calcula_retencion_2026_a_15_25_pct(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        TasaRetencionHonorarios::create(['anio' => 2026, 'tasa_pct' => 15.25]);

        $honorario = $this->service->registrar($empresa->id, [
            'rut_prestador'   => '12345678-5',
            'nombre_prestador' => 'Pedro Prestador',
            'fecha'           => '2026-03-15',
            'monto_bruto'     => 1_000_000,
        ]);

        $this->assertEquals(152_500, $honorario->monto_retencion);
        $this->assertEquals(847_500, $honorario->monto_liquido);
        $this->assertEquals('15.25', $honorario->tasa_retencion_pct);
    }

    public function test_usa_tasa_del_anio_de_la_fecha(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        TasaRetencionHonorarios::create(['anio' => 2025, 'tasa_pct' => 14.50]);
        TasaRetencionHonorarios::create(['anio' => 2026, 'tasa_pct' => 15.25]);

        $honorario2025 = $this->service->registrar($empresa->id, [
            'rut_prestador'    => '12345678-5',
            'nombre_prestador' => 'Pedro Prestador',
            'fecha'            => '2025-06-01',
            'monto_bruto'      => 1_000_000,
        ]);

        $honorario2026 = $this->service->registrar($empresa->id, [
            'rut_prestador'    => '12345678-5',
            'nombre_prestador' => 'Pedro Prestador',
            'fecha'            => '2026-06-01',
            'monto_bruto'      => 1_000_000,
        ]);

        $this->assertEquals(145_000, $honorario2025->monto_retencion);
        $this->assertEquals(152_500, $honorario2026->monto_retencion);
    }

    public function test_lanza_error_sin_tasa_configurada_para_el_anio(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        // No se crea tasa para 2030

        $this->expectException(ComercialException::class);
        $this->expectExceptionMessage('2030');

        $this->service->registrar($empresa->id, [
            'rut_prestador'    => '12345678-5',
            'nombre_prestador' => 'Pedro Prestador',
            'fecha'            => '2030-01-01',
            'monto_bruto'      => 500_000,
        ]);
    }

    public function test_rechaza_rut_prestador_invalido(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        TasaRetencionHonorarios::create(['anio' => 2026, 'tasa_pct' => 15.25]);

        $this->expectException(ComercialException::class);
        $this->expectExceptionMessage('inválido');

        $this->service->registrar($empresa->id, [
            'rut_prestador'    => '12345678-0', // dígito verificador incorrecto
            'nombre_prestador' => 'Pedro Prestador',
            'fecha'            => '2026-03-15',
            'monto_bruto'      => 500_000,
        ]);
    }
}
