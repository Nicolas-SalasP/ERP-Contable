<?php

namespace Tests\Feature\Sii\Commands;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class EmitirDtePruebaCommandTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        Storage::fake(config('sii.storage.disk', 'local'));
    }

    private function empresaConfigurada(string $rut = '76555444-3'): Empresa
    {
        return Empresa::create([
            'rut'                   => $rut,
            'razon_social'          => 'EMPRESA CMD',
            'giro_emisor'           => 'Servicios',
            'codigo_actividad_sii'  => 471910,
            'direccion'             => 'Av X 1',
            'comuna'                => 'Santiago',
            'ciudad'                => 'Santiago',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
    }

    public function test_comando_aparece_en_artisan_list(): void
    {
        $this->assertContains('sii:emitir-dte-prueba', array_keys(Artisan::all()));
    }

    public function test_emite_dte_de_prueba_con_fixture(): void
    {
        $empresa = $this->empresaConfigurada();
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $empresa->rut);
        $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $exitCode = Artisan::call('sii:emitir-dte-prueba', [
            'empresa_id' => $empresa->id,
            '--tipo'     => 33,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('DTE emitido exitosamente en estado FIRMADO', $output);
        $this->assertStringContainsString('xml_path', $output);
        $this->assertStringContainsString('xml_hash_sha256', $output);

        // El DTE creado por el fixture debe quedar firmado en BD.
        $firmado = SiiDteEmitido::where('empresa_id', $empresa->id)->first();
        $this->assertNotNull($firmado);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $firmado->estado);
    }

    public function test_falla_si_empresa_no_tiene_cert_activo(): void
    {
        $empresa = $this->empresaConfigurada('77999888-K');
        // Sin cert.
        $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $exitCode = Artisan::call('sii:emitir-dte-prueba', [
            'empresa_id' => $empresa->id,
            '--tipo'     => 33,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Certificado', $output);
    }
}
