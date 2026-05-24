<?php

namespace Tests\Feature\Sii\Commands;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class EnviarDtePruebaCommandTest extends TestCase
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

        config([
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries'         => 2,
            'sii.upload.retry_delay_ms'  => 1,
        ]);

        Storage::fake(config('sii.storage.disk', 'local'));
    }

    private function fakeOk(): void
    {
        $envSeed  = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getSeedResponse>'
                  . '<getSeedReturn><![CDATA[<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><SEMILLA>S1</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>]]></getSeedReturn>'
                  . '</getSeedResponse></soapenv:Body></soapenv:Envelope>';
        $envToken = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getTokenResponse>'
                  . '<getTokenReturn><![CDATA[<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><TOKEN>TOK</TOKEN></SII:RESP_BODY></SII:RESPUESTA>]]></getTokenReturn>'
                  . '</getTokenResponse></soapenv:Body></soapenv:Envelope>';

        Http::fake([
            '*/CrSeed*'           => Http::response($envSeed, 200),
            '*/GetTokenFromSeed*' => Http::response($envToken, 200),
            '*/DTEUpload*'        => Http::response("RECIBIDO\nTRACKID: TRK_CMD_42\nERROR: 0\nGLOSA: OK", 200),
        ]);
    }

    private function dteFirmado(): SiiDteEmitido
    {
        $empresa = Empresa::create([
            'rut'                   => '76555444-3',
            'razon_social'          => 'EMPRESA CMD',
            'giro_emisor'           => 'X',
            'codigo_actividad_sii'  => 471910,
            'direccion'             => 'X', 'comuna' => 'X', 'ciudad' => 'X',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $empresa->rut);
        $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $dte = SiiDteEmitido::create([
            'empresa_id' => $empresa->id, 'tipo_dte' => 33, 'folio' => 999998,
            'fecha_emision' => now()->toDateString(),
            'emisor_rut' => $empresa->rut, 'emisor_razon_social' => $empresa->razon_social,
            'emisor_giro' => 'X', 'emisor_acteco' => 471910,
            'emisor_direccion' => 'X', 'emisor_comuna' => 'X',
            'receptor_rut' => '66666666-6', 'receptor_razon_social' => 'CL',
            'moneda' => 'CLP', 'monto_neto' => 1000, 'monto_exento' => 0,
            'tasa_iva' => 19.00, 'iva' => 190, 'monto_total' => 1190,
            'estado' => SiiDteEmitido::ESTADO_BORRADOR, 'es_cedible' => true,
        ]);
        SiiDteEmitidoDetalle::create([
            'dte_emitido_id' => $dte->id, 'numero_linea' => 1,
            'nombre_item' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000, 'monto_item' => 1000,
        ]);

        return app(EmitirDteService::class)->emitir($dte->id);
    }

    public function test_aparece_en_artisan_list(): void
    {
        $this->assertContains('sii:enviar-dte-prueba', array_keys(Artisan::all()));
    }

    public function test_envia_dte_FIRMADO_imprime_track_id(): void
    {
        $dte = $this->dteFirmado();
        $this->fakeOk();

        $exit = Artisan::call('sii:enviar-dte-prueba', ['dte_id' => $dte->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Envio procesado', $output);
        $this->assertStringContainsString('TRK_CMD_42', $output);
        $this->assertStringContainsString('estado_envio    : ENVIADO', $output);
    }

    public function test_falla_si_dte_no_existe(): void
    {
        $exit = Artisan::call('sii:enviar-dte-prueba', ['dte_id' => 99999]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('no encontrado', Artisan::output());
    }
}
