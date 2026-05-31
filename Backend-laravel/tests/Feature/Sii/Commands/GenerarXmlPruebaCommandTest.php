<?php

namespace Tests\Feature\Sii\Commands;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class GenerarXmlPruebaCommandTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function dteValido(): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->factura()->create([
            'emisor_acteco'    => 471910,
            'emisor_giro'      => 'Comercio',
            'emisor_direccion' => 'Calle 1',
            'emisor_comuna'    => 'Santiago',
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Item',
        ]);

        return $dte;
    }

    public function test_comando_aparece_en_artisan_list(): void
    {
        $this->assertContains('sii:generar-xml-prueba', array_keys(Artisan::all()));
    }

    public function test_genera_xml_para_dte_existente_retorna_success(): void
    {
        $dte = $this->dteValido();
        $exitCode = Artisan::call('sii:generar-xml-prueba', ['dte_id' => $dte->id]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('<DTE', $output);
        $this->assertStringContainsString('encoding="ISO-8859-1"', $output);
    }

    public function test_falla_si_dte_no_existe(): void
    {
        $exitCode = Artisan::call('sii:generar-xml-prueba', ['dte_id' => 99999]);
        $this->assertSame(1, $exitCode);
    }

    public function test_flag_out_persiste_al_disco(): void
    {
        $dte = $this->dteValido();
        $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dte_' . uniqid() . '.xml';

        $exitCode = Artisan::call('sii:generar-xml-prueba', [
            'dte_id' => $dte->id,
            '--out'  => $ruta,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($ruta);
        $contenido = file_get_contents($ruta);
        $this->assertStringContainsString('<DTE', $contenido);

        @unlink($ruta);
    }
}
