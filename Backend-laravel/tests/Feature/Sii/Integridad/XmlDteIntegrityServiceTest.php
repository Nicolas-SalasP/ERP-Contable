<?php

namespace Tests\Feature\Sii\Integridad;

use App\Domains\Sii\Exceptions\IntegridadXmlException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Integridad\XmlDteIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class XmlDteIntegrityServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private XmlDteIntegrityService $service;
    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->disk = config('sii.storage.disk', 'local');
        Storage::fake($this->disk);
        $this->service = new XmlDteIntegrityService();
    }

    /**
     * Crea un DTE firmado de prueba: BORRADOR -> update a FIRMADO con xml_path,
     * hash y backup cifrado consistentes (o intencionalmente inconsistentes).
     */
    private function dteFirmadoConArtefactos(
        string $xmlEnDisco,
        string $hashEnBd,
        ?string $xmlBackupParaCifrar = null
    ): SiiDteEmitido {
        $dte = SiiDteEmitido::factory()->create(['estado' => SiiDteEmitido::ESTADO_BORRADOR]);

        $xmlPath = sprintf('sii/%d/2026/05/33_%d_envio.xml', $dte->empresa_id, $dte->folio);
        Storage::disk($this->disk)->put($xmlPath, $xmlEnDisco);

        $updates = [
            'estado'          => SiiDteEmitido::ESTADO_FIRMADO,
            'fecha_firma'     => now(),
            'xml_path'        => $xmlPath,
            'xml_hash_sha256' => $hashEnBd,
        ];
        if ($xmlBackupParaCifrar !== null) {
            $updates['xml_completo_cifrado'] = Crypt::encryptString($xmlBackupParaCifrar);
        }
        $dte->update($updates);

        return $dte->fresh();
    }

    public function test_lee_xml_desde_disco_si_hash_coincide(): void
    {
        $xml  = '<EnvioDTE>contenido valido</EnvioDTE>';
        $hash = hash('sha256', $xml);
        $dte  = $this->dteFirmadoConArtefactos($xml, $hash, $xml);

        $leido = $this->service->leerVerificado($dte->id);

        $this->assertSame($xml, $leido);
    }

    public function test_fallback_a_BD_si_disco_inaccesible(): void
    {
        $xml  = '<EnvioDTE>desde BD</EnvioDTE>';
        $hash = hash('sha256', $xml);

        $dte = SiiDteEmitido::factory()->create(['estado' => SiiDteEmitido::ESTADO_BORRADOR]);
        $dte->update([
            'estado'               => SiiDteEmitido::ESTADO_FIRMADO,
            'xml_path'             => 'sii/inexistente/path.xml',  // NO existe en disco
            'xml_hash_sha256'      => $hash,
            'xml_completo_cifrado' => Crypt::encryptString($xml),
        ]);

        $leido = $this->service->leerVerificado($dte->fresh()->id);
        $this->assertSame($xml, $leido);
    }

    public function test_fallback_a_BD_si_hash_disco_no_coincide(): void
    {
        $xmlReal  = '<EnvioDTE>real</EnvioDTE>';
        $hashReal = hash('sha256', $xmlReal);
        // Disco trae contenido corrupto, pero BD tiene el real
        $dte = $this->dteFirmadoConArtefactos('<CORRUPTO/>', $hashReal, $xmlReal);

        $leido = $this->service->leerVerificado($dte->id);
        $this->assertSame($xmlReal, $leido);
    }

    public function test_logguea_incidente_de_disco_corrupto_en_fallback(): void
    {
        // Capturamos via TestHandler de Monolog en el canal sii.
        $handler = new \Monolog\Handler\TestHandler();
        \Illuminate\Support\Facades\Log::channel('sii')->getLogger()->pushHandler($handler);

        $xmlReal  = '<EnvioDTE>x</EnvioDTE>';
        $hashReal = hash('sha256', $xmlReal);
        $dte = $this->dteFirmadoConArtefactos('<MALO/>', $hashReal, $xmlReal);

        $this->service->leerVerificado($dte->id);

        $warning = collect($handler->getRecords())
            ->first(fn ($r) => str_contains((string) $r['message'], 'hash invalido'));
        $this->assertNotNull($warning, 'Debe loggearse warning de disco con hash invalido.');
        $this->assertSame($dte->id, $warning['context']['dte_id']);
    }

    public function test_lanza_excepcion_si_ambas_fuentes_corruptas(): void
    {
        $hashReal = hash('sha256', '<REAL/>');
        // Disco corrupto + BD con hash distinto al esperado
        $dte = $this->dteFirmadoConArtefactos('<DISCO_MALO/>', $hashReal, '<BD_TAMBIEN_MALO/>');

        try {
            $this->service->leerVerificado($dte->id);
            $this->fail('Debio lanzar IntegridadXmlException');
        } catch (IntegridadXmlException $e) {
            $this->assertSame(IntegridadXmlException::MOTIVO_AMBAS_FUENTES_CORRUPTAS, $e->motivo);
            $this->assertSame($dte->id, $e->dteId);
        }
    }

    public function test_lanza_si_dte_no_emitido_aun(): void
    {
        $dte = SiiDteEmitido::factory()->create(['estado' => SiiDteEmitido::ESTADO_BORRADOR]);

        try {
            $this->service->leerVerificado($dte->id);
            $this->fail('Debio lanzar IntegridadXmlException');
        } catch (IntegridadXmlException $e) {
            $this->assertSame(IntegridadXmlException::MOTIVO_DTE_NO_EMITIDO, $e->motivo);
        }
    }
}
