<?php

namespace Tests\Feature\Sii\Ws;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\SiiConfiguracionIncompletaException;
use App\Domains\Sii\Models\SiiTokenSesion;
use App\Domains\Sii\Services\Ws\SiiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiTokenServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    private SiiTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        $this->service = app(SiiTokenService::class);
    }

    private function empresaConCert(string $rut = '76555444-3', string $ambiente = 'certificacion', ?int $resolucion = 80): Empresa
    {
        $empresa = Empresa::create([
            'rut'                   => $rut,
            'razon_social'          => 'EMPRESA TOKEN',
            'ambiente_sii'          => $ambiente,
            'resolucion_sii_numero' => $resolucion,
            'resolucion_sii_fecha'  => '2024-08-22',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $rut);

        return $empresa;
    }

    private function envelopeSeed(string $semilla): string
    {
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
               . "<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>"
               . "<SII:RESP_BODY><SEMILLA>{$semilla}</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>";

        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soapenv:Body><getSeedResponse>'
             . "<getSeedReturn><![CDATA[{$cdata}]]></getSeedReturn>"
             . '</getSeedResponse></soapenv:Body></soapenv:Envelope>';
    }

    private function envelopeToken(string $token): string
    {
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
               . "<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>"
               . "<SII:RESP_BODY><TOKEN>{$token}</TOKEN></SII:RESP_BODY></SII:RESPUESTA>";

        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soapenv:Body><getTokenResponse>'
             . "<getTokenReturn><![CDATA[{$cdata}]]></getTokenReturn>"
             . '</getTokenResponse></soapenv:Body></soapenv:Envelope>';
    }

    private function fakeOk(string $semilla = 'SEMILLA-X', string $token = 'TOKEN-ABCDEF-123'): void
    {
        Http::fake([
            '*/CrSeed*'           => Http::response($this->envelopeSeed($semilla), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envelopeToken($token), 200),
        ]);
    }

    /**
     * Setea fakes con multiples tokens consecutivos via Http::sequence.
     * Util cuando el test hace varias autenticaciones y necesita distinguir.
     *
     * @param array<int, string> $tokens en orden de aparicion
     * @param array<int, string> $semillas en orden de aparicion (opcional)
     */
    private function fakeOkConSecuencia(array $tokens, array $semillas = []): void
    {
        $seedSeq = Http::sequence();
        $tokenSeq = Http::sequence();

        $semillasCount = max(count($semillas), count($tokens));
        for ($i = 0; $i < $semillasCount; $i++) {
            $seedSeq->push($this->envelopeSeed($semillas[$i] ?? "SEMILLA-{$i}"), 200);
        }
        foreach ($tokens as $tok) {
            $tokenSeq->push($this->envelopeToken($tok), 200);
        }

        Http::fake([
            '*/CrSeed*'           => $seedSeq,
            '*/GetTokenFromSeed*' => $tokenSeq,
        ]);
    }

    public function test_obtiene_token_nuevo_si_no_hay_sesion_activa(): void
    {
        $empresa = $this->empresaConCert();
        $this->fakeOk(semilla: 'S1', token: 'TOK1');

        $sesion = $this->service->obtenerSesionActiva($empresa);

        $this->assertSame('TOK1', $sesion->token);
        $this->assertSame('S1', $sesion->semilla_usada);
        $this->assertSame('certificacion', $sesion->ambiente);
        $this->assertSame(1, $sesion->intentos_uso);
        $this->assertTrue($sesion->estaVigente());
    }

    public function test_reutiliza_sesion_activa_existente_sin_llamar_SII(): void
    {
        $empresa = $this->empresaConCert();
        $this->fakeOk(token: 'TOK1');
        $primera = $this->service->obtenerSesionActiva($empresa);

        // Reset fakes y verificamos que la segunda llamada NO toca HTTP.
        Http::fake(); // limpia
        Http::fake(['*' => Http::response('NO_DEBIO_LLAMARSE', 500)]);

        $segunda = $this->service->obtenerSesionActiva($empresa);

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame(2, $segunda->intentos_uso);
        Http::assertNothingSent();
    }

    public function test_sesion_expirada_no_se_reutiliza_genera_nueva(): void
    {
        $empresa = $this->empresaConCert();
        $this->fakeOkConSecuencia(['TOK1', 'TOK2_NUEVA']);

        $vieja = $this->service->obtenerSesionActiva($empresa);
        $this->assertSame('TOK1', $vieja->token);

        $vieja->update(['fecha_expiracion' => now()->subMinute()]);

        $nueva = $this->service->obtenerSesionActiva($empresa);
        $this->assertNotSame($vieja->id, $nueva->id);
        $this->assertSame('TOK2_NUEVA', $nueva->token);
    }

    public function test_sesion_de_otra_empresa_no_se_reutiliza_aislamiento_multitenant(): void
    {
        $empresaA = $this->empresaConCert('76111111-1');
        $empresaB = $this->empresaConCert('77222222-2');

        $this->fakeOkConSecuencia(['TOK_A', 'TOK_B']);

        $sesionA = $this->service->obtenerSesionActiva($empresaA);
        $sesionB = $this->service->obtenerSesionActiva($empresaB);

        $this->assertNotSame($sesionA->id, $sesionB->id);
        $this->assertNotSame($sesionA->token, $sesionB->token);
        $this->assertSame('TOK_A', $sesionA->token);
        $this->assertSame('TOK_B', $sesionB->token);
        $this->assertSame($empresaA->id, $sesionA->empresa_id);
        $this->assertSame($empresaB->id, $sesionB->empresa_id);
    }

    public function test_sesion_de_otro_ambiente_no_se_reutiliza(): void
    {
        $empresa = $this->empresaConCert('76555444-3', 'certificacion');

        $this->fakeOkConSecuencia(['TOK_CERT', 'TOK_PROD']);

        $sesionCert = $this->service->obtenerSesionActiva($empresa);
        $this->assertSame('certificacion', $sesionCert->ambiente);
        $this->assertSame('TOK_CERT', $sesionCert->token);

        $empresa->update(['ambiente_sii' => 'produccion']);

        $sesionProd = $this->service->obtenerSesionActiva($empresa->fresh());

        $this->assertNotSame($sesionCert->id, $sesionProd->id);
        $this->assertSame('produccion', $sesionProd->ambiente);
        $this->assertSame('TOK_PROD', $sesionProd->token);
    }

    public function test_registrar_uso_incrementa_intentos_uso_y_actualiza_ultimo_uso_en(): void
    {
        $empresa = $this->empresaConCert();
        $this->fakeOk();
        $sesion = $this->service->obtenerSesionActiva($empresa);
        $primeraFecha = $sesion->ultimo_uso_en;
        $this->assertSame(1, $sesion->intentos_uso);

        // Avanzamos el tiempo simulando que pasa un segundo.
        sleep(1);
        $this->service->obtenerSesionActiva($empresa);
        $refresh = $sesion->fresh();

        $this->assertSame(2, $refresh->intentos_uso);
        $this->assertGreaterThanOrEqual($primeraFecha->timestamp, $refresh->ultimo_uso_en->timestamp);
    }

    public function test_hash_firma_semilla_se_persiste_correctamente(): void
    {
        $empresa = $this->empresaConCert();
        $this->fakeOk();
        $sesion = $this->service->obtenerSesionActiva($empresa);

        $this->assertNotNull($sesion->hash_firma_semilla);
        $this->assertSame(64, strlen($sesion->hash_firma_semilla));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sesion->hash_firma_semilla);
    }

    public function test_lanza_si_ambiente_produccion_sin_resolucion_sii_numero(): void
    {
        $empresa = $this->empresaConCert('76555444-3', 'produccion', resolucion: null);

        try {
            $this->service->obtenerSesionActiva($empresa);
            $this->fail('Debio lanzar SiiConfiguracionIncompletaException');
        } catch (SiiConfiguracionIncompletaException $e) {
            $this->assertSame(SiiConfiguracionIncompletaException::MOTIVO_PROD_SIN_RESOLUCION, $e->motivo);
            $this->assertSame($empresa->id, $e->empresaId);
        }

        // No debe haberse pegado al SII.
        Http::assertNothingSent();
    }

    public function test_force_genera_nueva_sesion_aunque_haya_activa(): void
    {
        $empresa = $this->empresaConCert();
        $this->fakeOkConSecuencia(['TOK1', 'TOK2']);

        $primera = $this->service->obtenerSesionActiva($empresa);
        $this->assertSame('TOK1', $primera->token);
        $this->assertTrue($primera->estaVigente(), 'Primera sesion debe seguir vigente.');

        $segunda = $this->service->generarSesionNueva($empresa);

        $this->assertNotSame($primera->id, $segunda->id);
        $this->assertSame('TOK2', $segunda->token);
    }

    public function test_token_persistido_no_aparece_en_serializacion_json_hidden(): void
    {
        $empresa = $this->empresaConCert();
        $this->fakeOk(token: 'TOK_SECRETO');
        $sesion = $this->service->obtenerSesionActiva($empresa);

        $json = $sesion->toJson();
        $arr  = $sesion->toArray();

        $this->assertArrayNotHasKey('token', $arr);
        $this->assertStringNotContainsString('TOK_SECRETO', $json);
        $this->assertStringNotContainsString('"token"', $json);
    }
}
