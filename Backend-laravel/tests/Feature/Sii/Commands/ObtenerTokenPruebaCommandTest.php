<?php

namespace Tests\Feature\Sii\Commands;

use App\Domains\Core\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ObtenerTokenPruebaCommandTest extends TestCase
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
    }

    private function fakeOk(): void
    {
        $cdataSeed = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
                   . '<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>'
                   . '<SII:RESP_BODY><SEMILLA>SMK-1</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>';
        $cdataToken = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
                    . '<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>'
                    . '<SII:RESP_BODY><TOKEN>TOKEN-DESDE-COMANDO-XX</TOKEN></SII:RESP_BODY></SII:RESPUESTA>';
        Http::fake([
            '*/CrSeed*' => Http::response(
                '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
                . '<soapenv:Body><getSeedResponse>'
                . "<getSeedReturn><![CDATA[{$cdataSeed}]]></getSeedReturn>"
                . '</getSeedResponse></soapenv:Body></soapenv:Envelope>',
                200
            ),
            '*/GetTokenFromSeed*' => Http::response(
                '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
                . '<soapenv:Body><getTokenResponse>'
                . "<getTokenReturn><![CDATA[{$cdataToken}]]></getTokenReturn>"
                . '</getTokenResponse></soapenv:Body></soapenv:Envelope>',
                200
            ),
        ]);
    }

    public function test_comando_aparece_en_artisan_list(): void
    {
        $this->assertContains('sii:obtener-token-prueba', array_keys(Artisan::all()));
    }

    public function test_imprime_token_truncado_y_fecha_expiracion(): void
    {
        $empresa = Empresa::create([
            'rut'                   => '76555444-3',
            'razon_social'          => 'EMPRESA CMD TOK',
            'ambiente_sii'          => 'certificacion',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $empresa->rut);
        $this->fakeOk();

        $exitCode = Artisan::call('sii:obtener-token-prueba', ['empresa_id' => $empresa->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Sesion SII obtenida exitosamente', $output);
        $this->assertStringContainsString('TOKEN-DESDE-COMA', $output);  // truncado a 16
        $this->assertStringNotContainsString('TOKEN-DESDE-COMANDO-XX', $output);  // completo NO
        $this->assertStringContainsString('Fecha expiracion', $output);
        $this->assertStringContainsString('Minutos restantes', $output);
        $this->assertStringContainsString('NUEVA SESION', $output);
    }

    public function test_falla_si_empresa_no_tiene_cert_activo(): void
    {
        $empresa = Empresa::create([
            'rut'          => '77000000-0',
            'razon_social' => 'SIN CERT',
            'ambiente_sii' => 'certificacion',
        ]);

        $exitCode = Artisan::call('sii:obtener-token-prueba', ['empresa_id' => $empresa->id]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Certificado', $output);
    }
}
