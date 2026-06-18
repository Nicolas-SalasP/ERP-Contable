<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Contabilidad\Services\ImpuestosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;

class F29RetencionHonorariosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_f29_incluye_retencion_de_honorarios_del_periodo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        HonorarioRecibido::create([
            'empresa_id'       => $empresa->id,
            'rut_prestador'    => '12.345.678-9',
            'nombre_prestador' => 'Prestador Uno',
            'fecha'            => '2026-03-10',
            'monto_bruto'      => 1000000,
            'tasa_retencion_pct' => 10.00,
            'monto_retencion'  => 100000,
            'monto_liquido'    => 900000,
        ]);

        HonorarioRecibido::create([
            'empresa_id'       => $empresa->id,
            'rut_prestador'    => '98.765.432-1',
            'nombre_prestador' => 'Prestador Dos',
            'fecha'            => '2026-03-25',
            'monto_bruto'      => 500000,
            'tasa_retencion_pct' => 10.00,
            'monto_retencion'  => 50000,
            'monto_liquido'    => 450000,
        ]);

        $service = app(ImpuestosService::class);
        $resultado = $service->simularF29($empresa->id, 3, 2026);

        $this->assertEquals(150000, $resultado['retenciones']);
    }

    public function test_f29_no_incluye_honorarios_de_otro_periodo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        HonorarioRecibido::create([
            'empresa_id'       => $empresa->id,
            'rut_prestador'    => '12.345.678-9',
            'nombre_prestador' => 'Prestador Uno',
            'fecha'            => '2026-02-15',
            'monto_bruto'      => 1000000,
            'tasa_retencion_pct' => 10.00,
            'monto_retencion'  => 100000,
            'monto_liquido'    => 900000,
        ]);

        $service = app(ImpuestosService::class);
        $resultado = $service->simularF29($empresa->id, 3, 2026);

        $this->assertEquals(0, $resultado['retenciones']);
    }
}
