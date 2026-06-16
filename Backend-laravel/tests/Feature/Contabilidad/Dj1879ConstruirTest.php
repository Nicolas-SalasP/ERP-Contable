<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Services\Dj\Dj1879Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class Dj1879ConstruirTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_construye_una_linea_por_prestador(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        HonorarioRecibido::create([
            'empresa_id'       => $empresa->id,
            'rut_prestador'    => '11111111-1',
            'nombre_prestador' => 'Prestador Uno',
            'fecha'            => '2026-03-15',
            'monto_bruto'      => 500_000,
            'tasa_retencion_pct' => 10.75,
            'monto_retencion'  => 53_750,
            'monto_liquido'    => 446_250,
        ]);

        HonorarioRecibido::create([
            'empresa_id'       => $empresa->id,
            'rut_prestador'    => '22222222-2',
            'nombre_prestador' => 'Prestador Dos',
            'fecha'            => '2026-06-20',
            'monto_bruto'      => 300_000,
            'tasa_retencion_pct' => 10.75,
            'monto_retencion'  => 32_250,
            'monto_liquido'    => 267_750,
        ]);

        $service = new Dj1879Service();
        $data    = $service->construir($empresa->id, 2026);

        $this->assertCount(2, $data->lineas);
    }

    public function test_agrega_honorarios_y_retencion_del_anio_por_prestador(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $registros = [
            ['monto_bruto' => 100_000, 'monto_retencion' => 15_250, 'fecha' => '2026-01-10'],
            ['monto_bruto' => 200_000, 'monto_retencion' => 30_500, 'fecha' => '2026-04-15'],
            ['monto_bruto' => 300_000, 'monto_retencion' => 45_750, 'fecha' => '2026-08-20'],
        ];

        foreach ($registros as $r) {
            HonorarioRecibido::create([
                'empresa_id'         => $empresa->id,
                'rut_prestador'      => '33333333-3',
                'nombre_prestador'   => 'Prestador Tres',
                'fecha'              => $r['fecha'],
                'monto_bruto'        => $r['monto_bruto'],
                'tasa_retencion_pct' => 10.75,
                'monto_retencion'    => $r['monto_retencion'],
                'monto_liquido'      => $r['monto_bruto'] - $r['monto_retencion'],
            ]);
        }

        $service = new Dj1879Service();
        $data    = $service->construir($empresa->id, 2026);

        $this->assertCount(1, $data->lineas);

        $campos = $data->lineas[0]->campos;
        $this->assertEquals(600_000, $campos['monto_bruto_total']);
        $this->assertEquals(91_500,  $campos['retencion_total']);
        $this->assertEquals(3,       $campos['num_documentos']);
    }

    public function test_lanza_error_si_no_hay_honorarios_del_anio(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->expectException(DjException::class);
        $this->expectExceptionMessageMatches('/2026/');

        $service = new Dj1879Service();
        $service->construir($empresa->id, 2026);
    }
}
