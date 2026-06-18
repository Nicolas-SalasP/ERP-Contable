<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Services\Dj\Dj1879Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class Dj1879FormatoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_dj1879_valida_sin_errores_con_datos_correctos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        HonorarioRecibido::create([
            'empresa_id'         => $empresa->id,
            'rut_prestador'      => '11111111-1',
            'nombre_prestador'   => 'Prestador Uno',
            'fecha'              => '2026-03-15',
            'monto_bruto'        => 500_000,
            'tasa_retencion_pct' => 10.75,
            'monto_retencion'    => 53_750,
            'monto_liquido'      => 446_250,
        ]);

        $service = new Dj1879Service();
        $data    = $service->construir($empresa->id, 2026);
        $errores = $service->validar($data);

        $this->assertEmpty($errores);
    }

    public function test_detecta_prestador_con_monto_bruto_cero(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $data = new DjData(
            codigoDj:  '1879',
            empresaId: $empresa->id,
            anio:      2026,
            cabecera: [
                'rut_empresa'  => $empresa->rut,
                'razon_social' => $empresa->razon_social,
            ],
            lineas: [
                new DjLineaData([
                    'rut_prestador'     => '11111111-1',
                    'nombre_prestador'  => 'Prestador Cero',
                    'monto_bruto_total' => 0,
                    'retencion_total'   => 0,
                    'num_documentos'    => 1,
                ]),
            ],
        );

        $service = new Dj1879Service();
        $errores = $service->validar($data);

        $this->assertNotEmpty($errores);
        $this->assertTrue(
            collect($errores)->contains(fn ($e) => str_contains($e, 'monto bruto es cero')),
            'Se esperaba error de monto bruto cero. Errores: ' . implode(', ', $errores)
        );
    }

    public function test_detecta_descuadre_retencion_total_cero(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $data = new DjData(
            codigoDj:  '1879',
            empresaId: $empresa->id,
            anio:      2026,
            cabecera: [
                'rut_empresa'  => $empresa->rut,
                'razon_social' => $empresa->razon_social,
            ],
            lineas: [
                new DjLineaData([
                    'rut_prestador'     => '22222222-2',
                    'nombre_prestador'  => 'Prestador Sin Retencion',
                    'monto_bruto_total' => 300_000,
                    'retencion_total'   => 0,
                    'num_documentos'    => 2,
                ]),
            ],
        );

        $service = new Dj1879Service();
        $errores = $service->validar($data);

        $this->assertNotEmpty($errores);
        $this->assertTrue(
            collect($errores)->contains(fn ($e) => str_contains($e, 'retención total del año es cero')),
            'Se esperaba error de retención total cero. Errores: ' . implode(', ', $errores)
        );
    }

    public function test_archivo_contiene_registros_A_D_T(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        HonorarioRecibido::create([
            'empresa_id'         => $empresa->id,
            'rut_prestador'      => '33333333-3',
            'nombre_prestador'   => 'Prestador Tres',
            'fecha'              => '2026-06-10',
            'monto_bruto'        => 400_000,
            'tasa_retencion_pct' => 10.75,
            'monto_retencion'    => 43_000,
            'monto_liquido'      => 357_000,
        ]);

        $service = new Dj1879Service();
        $data    = $service->construir($empresa->id, 2026);
        $archivo = $service->formatearArchivo($data);

        $this->assertTrue(
            str_starts_with($archivo, 'A;'),
            'El primer registro debe ser la cabecera (A)'
        );
        $this->assertStringContainsString("\nD;", $archivo, 'Debe existir al menos un registro de detalle (D)');
        $this->assertStringContainsString("\nT;", $archivo, 'Debe existir el registro totalizador (T)');
    }
}
