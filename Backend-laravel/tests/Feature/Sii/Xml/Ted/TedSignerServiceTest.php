<?php

namespace Tests\Feature\Sii\Xml\Ted;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use App\Domains\Sii\Services\Xml\Ted\TedSignerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Concerns\GeneraParRsaParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class TedSignerServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraParRsaParaTests;

    private TedSignerService $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->signer = new TedSignerService(new CafService(new CafXmlParser()));
    }

    private function cafConPar(string $pemPrivado, string $pemPublico): SiiCaf
    {
        return SiiCaf::factory()->create([
            'rsa_sk_cifrada' => Crypt::encryptString($pemPrivado),
            'rsa_pubk'       => $pemPublico,
        ]);
    }

    public function test_firma_dd_retorna_base64_no_vacio(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);

        $firma = $this->signer->firmarDd('<DD>contenido</DD>', $caf);

        $this->assertNotEmpty($firma);
        $this->assertNotFalse(base64_decode($firma, true), 'La firma debe ser base64 valida.');
    }

    public function test_firma_dd_produce_256_bytes_binarios_para_rsa_2048(): void
    {
        [$sk, $pk] = $this->generarParRsa(2048);
        $caf = $this->cafConPar($sk, $pk);

        $firma = $this->signer->firmarDd('<DD>test</DD>', $caf);
        $bin   = base64_decode($firma, true);

        $this->assertSame(256, strlen($bin), 'RSA-2048 produce firmas de 256 bytes.');
    }

    public function test_firma_es_deterministica_para_mismo_dd_y_misma_clave(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);

        $firma1 = $this->signer->firmarDd('<DD>x</DD>', $caf);
        $firma2 = $this->signer->firmarDd('<DD>x</DD>', $caf);

        $this->assertSame($firma1, $firma2, 'RSA-PKCS1 (sin sal) firma deterministicamente.');
    }

    public function test_dds_distintos_producen_firmas_distintas(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);

        $firmaA = $this->signer->firmarDd('<DD>A</DD>', $caf);
        $firmaB = $this->signer->firmarDd('<DD>B</DD>', $caf);

        $this->assertNotSame($firmaA, $firmaB);
    }

    public function test_verificar_firma_retorna_true_para_par_correcto(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);

        $dd    = '<DD>verificable</DD>';
        $firma = $this->signer->firmarDd($dd, $caf);

        $this->assertTrue($this->signer->verificarFirma($dd, $firma, $caf));
    }

    public function test_verificar_firma_retorna_false_si_dd_alterado(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);

        $firma = $this->signer->firmarDd('<DD>original</DD>', $caf);

        $this->assertFalse($this->signer->verificarFirma('<DD>tampered</DD>', $firma, $caf));
    }

    public function test_falla_si_rsa_sk_cifrada_no_es_pem_valido(): void
    {
        $caf = SiiCaf::factory()->create([
            'rsa_sk_cifrada' => Crypt::encryptString('NO_ES_UN_PEM'),
        ]);

        $this->expectException(CafInvalidoException::class);

        try {
            $this->signer->firmarDd('<DD>x</DD>', $caf);
        } catch (CafInvalidoException $e) {
            $this->assertSame(CafInvalidoException::MOTIVO_RSA_SK_NO_LEGIBLE, $e->motivo);
            throw $e;
        }
    }

    public function test_verificar_firma_retorna_false_con_pubkey_invalida(): void
    {
        [$sk] = $this->generarParRsa();
        $caf = SiiCaf::factory()->create([
            'rsa_sk_cifrada' => Crypt::encryptString($sk),
            'rsa_pubk'       => 'NO_ES_UN_PEM_PUBLICO',
        ]);

        $firma = $this->signer->firmarDd('<DD>x</DD>', $caf);

        $this->assertFalse($this->signer->verificarFirma('<DD>x</DD>', $firma, $caf));
    }
}
