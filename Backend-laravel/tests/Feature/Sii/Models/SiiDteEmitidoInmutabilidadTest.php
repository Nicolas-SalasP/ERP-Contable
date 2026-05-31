<?php

namespace Tests\Feature\Sii\Models;

use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiDteEmitidoInmutabilidadTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function dteEnBorrador(array $overrides = []): SiiDteEmitido
    {
        return SiiDteEmitido::factory()->create(array_merge([
            'estado' => SiiDteEmitido::ESTADO_BORRADOR,
        ], $overrides));
    }

    private function dteEnFirmado(): SiiDteEmitido
    {
        // Creamos directamente con estado BORRADOR y luego pasamos a FIRMADO
        // via update permitido (transicion BORRADOR -> FIRMADO esta permitida).
        $dte = $this->dteEnBorrador();
        $dte->update([
            'estado'          => SiiDteEmitido::ESTADO_FIRMADO,
            'fecha_firma'     => now(),
            'xml_path'        => 'sii/1/2026/05/33_1_envio.xml',
            'xml_hash_sha256' => str_repeat('a', 64),
        ]);

        return $dte->fresh();
    }

    public function test_dte_en_BORRADOR_puede_modificar_cualquier_campo(): void
    {
        $dte = $this->dteEnBorrador();

        $dte->emisor_rut          = '11111111-1';
        $dte->emisor_razon_social = 'CAMBIO LIBRE';
        $dte->monto_total         = 9999;
        $dte->save();

        $fresco = $dte->fresh();
        $this->assertSame('11111111-1', $fresco->emisor_rut);
        $this->assertSame('CAMBIO LIBRE', $fresco->emisor_razon_social);
        $this->assertSame('9999.00', (string) $fresco->monto_total);
    }

    public function test_dte_en_FIRMADO_no_puede_modificar_monto_total_lanza(): void
    {
        $dte = $this->dteEnFirmado();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('inmutable');

        $dte->monto_total = 99999;
        $dte->save();
    }

    public function test_dte_en_FIRMADO_no_puede_modificar_emisor_rut_lanza(): void
    {
        $dte = $this->dteEnFirmado();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('emisor_rut');

        $dte->emisor_rut = '11111111-1';
        $dte->save();
    }

    public function test_dte_en_FIRMADO_no_puede_modificar_folio_lanza(): void
    {
        $dte = $this->dteEnFirmado();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('folio');

        $dte->folio = 99999;
        $dte->save();
    }

    public function test_dte_en_FIRMADO_SI_puede_modificar_estado_a_ENVIADO_SII(): void
    {
        $dte = $this->dteEnFirmado();

        $dte->estado          = SiiDteEmitido::ESTADO_ENVIADO_SII;
        $dte->track_id        = '1234567890';
        $dte->fecha_envio_sii = now();
        $dte->save();

        $fresco = $dte->fresh();
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $fresco->estado);
        $this->assertSame('1234567890', $fresco->track_id);
        $this->assertNotNull($fresco->fecha_envio_sii);
    }

    public function test_dte_en_FIRMADO_SI_puede_modificar_xml_path(): void
    {
        $dte = $this->dteEnFirmado();

        $dte->xml_path = 'sii/1/2026/05/33_1_envio_v2.xml';
        $dte->save();

        $this->assertSame('sii/1/2026/05/33_1_envio_v2.xml', $dte->fresh()->xml_path);
    }
}
