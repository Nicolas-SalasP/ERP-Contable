<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Services\Dj\Dj1947Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class Dj1947FormatoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // ---------------------------------------------------------------------------
    // Helper: construye un DjData válido con dos propietarios (60/40)
    // ---------------------------------------------------------------------------

    private function datosValidos(): DjData
    {
        return new DjData(
            codigoDj: '1947',
            empresaId: 1,
            anio: 2026,
            cabecera: [
                'rut_empresa'    => '76123456-7',
                'razon_social'   => 'Empresa Test SpA',
                'anio'           => 2026,
                'base_imponible' => 1_000_000,
                'ppm_total'      => 1_250,
            ],
            lineas: [
                new DjLineaData([
                    'rut_propietario'    => '12345678-9',
                    'nombre_propietario' => 'Socio A',
                    'porcentaje'         => 60.00,
                    'base_atribuida'     => 600_000,
                    'creditos'           => 0,
                    'ppm_atribuido'      => 750,
                ]),
                new DjLineaData([
                    'rut_propietario'    => '98765432-1',
                    'nombre_propietario' => 'Socio B',
                    'porcentaje'         => 40.00,
                    'base_atribuida'     => 400_000,
                    'creditos'           => 0,
                    'ppm_atribuido'      => 500,
                ]),
            ],
        );
    }

    private function servicio(): Dj1947Service
    {
        return $this->app->make(Dj1947Service::class);
    }

    // ---------------------------------------------------------------------------
    // Tests de validación
    // ---------------------------------------------------------------------------

    public function test_dj1947_valida_sin_errores_con_datos_correctos(): void
    {
        $service = $this->servicio();
        $errores = $service->validar($this->datosValidos());

        $this->assertEmpty(
            $errores,
            'No deben existir errores con datos correctos. Encontrados: ' . implode(', ', $errores)
        );
    }

    public function test_detecta_participaciones_que_no_suman_100(): void
    {
        $data = new DjData(
            codigoDj: '1947',
            empresaId: 1,
            anio: 2026,
            cabecera: [
                'rut_empresa'    => '76123456-7',
                'razon_social'   => 'Empresa Test SpA',
                'anio'           => 2026,
                'base_imponible' => 900_000,
                'ppm_total'      => 1_000,
            ],
            lineas: [
                new DjLineaData([
                    'rut_propietario'    => '12345678-9',
                    'nombre_propietario' => 'Socio A',
                    'porcentaje'         => 60.00,
                    'base_atribuida'     => 540_000,
                    'creditos'           => 0,
                    'ppm_atribuido'      => 600,
                ]),
                new DjLineaData([
                    'rut_propietario'    => '98765432-1',
                    'nombre_propietario' => 'Socio B',
                    'porcentaje'         => 30.00,  // 60+30 = 90, no 100
                    'base_atribuida'     => 360_000,
                    'creditos'           => 0,
                    'ppm_atribuido'      => 400,
                ]),
            ],
        );

        $service = $this->servicio();
        $errores = $service->validar($data);

        $this->assertNotEmpty($errores);
        $this->assertTrue(
            collect($errores)->contains(fn ($e) => str_contains($e, 'suman')),
            'Se esperaba error que mencione "suman". Errores: ' . implode(', ', $errores)
        );
    }

    public function test_detecta_descuadre_de_base_imponible(): void
    {
        // cabecera declara 1.000.000 pero suma de propietarios = 500.000
        $data = new DjData(
            codigoDj: '1947',
            empresaId: 1,
            anio: 2026,
            cabecera: [
                'rut_empresa'    => '76123456-7',
                'razon_social'   => 'Empresa Test SpA',
                'anio'           => 2026,
                'base_imponible' => 1_000_000,
                'ppm_total'      => 1_250,
            ],
            lineas: [
                new DjLineaData([
                    'rut_propietario'    => '12345678-9',
                    'nombre_propietario' => 'Socio A',
                    'porcentaje'         => 60.00,
                    'base_atribuida'     => 300_000,  // 300k + 200k = 500k ≠ 1M
                    'creditos'           => 0,
                    'ppm_atribuido'      => 750,
                ]),
                new DjLineaData([
                    'rut_propietario'    => '98765432-1',
                    'nombre_propietario' => 'Socio B',
                    'porcentaje'         => 40.00,
                    'base_atribuida'     => 200_000,
                    'creditos'           => 0,
                    'ppm_atribuido'      => 500,
                ]),
            ],
        );

        $service = $this->servicio();
        $errores = $service->validar($data);

        $this->assertNotEmpty($errores);
        $this->assertTrue(
            collect($errores)->contains(fn ($e) => str_contains($e, 'Descuadre')),
            'Se esperaba error que mencione "Descuadre". Errores: ' . implode(', ', $errores)
        );
    }

    // ---------------------------------------------------------------------------
    // Tests de formato de archivo
    // ---------------------------------------------------------------------------

    public function test_archivo_respeta_formato_del_suplemento(): void
    {
        $service = $this->servicio();
        $archivo = $service->formatearArchivo($this->datosValidos());

        // Debe iniciar con registro A
        $this->assertTrue(
            str_starts_with($archivo, 'A;'),
            'El archivo debe iniciar con el registro de cabecera (A;)'
        );

        // Debe contener un registro D por cada propietario (2 en datosValidos)
        $lineasD = array_filter(explode("\n", $archivo), fn ($l) => str_starts_with($l, 'D;'));
        $this->assertCount(
            2,
            $lineasD,
            'Deben existir exactamente 2 registros D (uno por propietario)'
        );

        // Debe contener el registro T de totales
        $this->assertStringContainsString(
            "\nT;",
            $archivo,
            'Debe existir el registro totalizador (T;)'
        );

        // El separador debe ser punto y coma
        $this->assertStringContainsString(
            ';',
            $archivo,
            'El archivo debe usar punto y coma como separador'
        );

        // El registro T debe reflejar los totales correctos: 2 propietarios, base 1M, ppm 1250
        $this->assertStringContainsString(
            "\nT;2;1000000;1250\n",
            $archivo,
            'El registro T debe contener: num_propietarios=2, base=1000000, ppm=1250'
        );
    }
}
