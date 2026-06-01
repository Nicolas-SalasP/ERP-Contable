<?php

namespace Tests\Feature\Sii\Commands;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CargarCafCommandTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function escribirXmlTemporal(string $contenido): string
    {
        $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'caf_test_' . uniqid() . '.xml';
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    private function xmlCaf(string $rut, string $idk = '500'): string
    {
        return <<<XML
<?xml version="1.0"?>
<AUTORIZACION>
  <CAF version="1.0">
    <DA>
      <RE>{$rut}</RE><RS>EMPRESA TEST</RS><TD>33</TD>
      <RNG><D>1</D><H>50</H></RNG>
      <FA>2026-01-15</FA>
      <RSAPK><M>M</M><E>E</E></RSAPK>
      <IDK>{$idk}</IDK>
    </DA>
    <FRMA algoritmo="SHA1withRSA">FIRMA</FRMA>
  </CAF>
  <RSASK>-----BEGIN RSA PRIVATE KEY-----
DUMMY
-----END RSA PRIVATE KEY-----</RSASK>
  <RSAPUBK>-----BEGIN PUBLIC KEY-----
DUMMY
-----END PUBLIC KEY-----</RSAPUBK>
</AUTORIZACION>
XML;
    }

    public function test_comando_existe_en_artisan_list(): void
    {
        $this->assertContains('sii:cargar-caf', array_keys(Artisan::all()));
    }

    public function test_cargar_caf_valido_persiste_y_retorna_success(): void
    {
        $rut = '77000000-' . RutHelper::calcularDv(77_000_000);
        $empresa = Empresa::create(['rut' => $rut, 'razon_social' => 'Empresa CMD']);

        $ruta = $this->escribirXmlTemporal($this->xmlCaf($rut, '500'));

        $exitCode = Artisan::call('sii:cargar-caf', [
            'empresa_id' => $empresa->id,
            'ruta'       => $ruta,
        ]);

        @unlink($ruta);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, SiiCaf::where('sii_idk', '500')->count());
    }

    public function test_cargar_archivo_inexistente_retorna_failure(): void
    {
        $exitCode = Artisan::call('sii:cargar-caf', [
            'empresa_id' => 1,
            'ruta'       => '/ruta/que/no/existe.xml',
        ]);

        $this->assertNotSame(0, $exitCode);
    }

    public function test_cargar_xml_malformado_retorna_failure(): void
    {
        $rut = '78000000-' . RutHelper::calcularDv(78_000_000);
        $empresa = Empresa::create(['rut' => $rut, 'razon_social' => 'X']);

        $ruta = $this->escribirXmlTemporal('<AUTORIZACION><CAF>NO_CIERRA');

        $exitCode = Artisan::call('sii:cargar-caf', [
            'empresa_id' => $empresa->id,
            'ruta'       => $ruta,
        ]);

        @unlink($ruta);

        $this->assertNotSame(0, $exitCode);
        $this->assertSame(0, SiiCaf::count());
    }
}
