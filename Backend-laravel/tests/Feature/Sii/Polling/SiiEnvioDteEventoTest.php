<?php

namespace Tests\Feature\Sii\Polling;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Models\SiiEnvioDteEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiEnvioDteEventoTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function envioBase(): SiiEnvioDte
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $dte = SiiDteEmitido::factory()->create([
            'empresa_id' => $empresa->id,
            'estado'     => SiiDteEmitido::ESTADO_ENVIADO_SII,
        ]);
        return SiiEnvioDte::create([
            'empresa_id'     => $empresa->id,
            'dte_emitido_id' => $dte->id,
            'ambiente_sii'   => 'certificacion',
            'estado_envio'   => SiiEnvioDte::ESTADO_ENVIADO,
            'track_id'       => 'TRK_42',
            'intentos_envio' => 1,
            'fecha_envio'    => now(),
        ]);
    }

    public function test_eventos_son_inmutables_sin_updated_at(): void
    {
        $cols = Schema::getColumnListing('sii_envio_dte_evento');
        $this->assertNotContains('updated_at', $cols);
        $this->assertContains('created_at', $cols);
        $this->assertFalse((new SiiEnvioDteEvento())->timestamps);
    }

    public function test_relacion_envio_eventos_ordenados_por_created_at(): void
    {
        $envio = $this->envioBase();

        DB::table('sii_envio_dte_evento')->insert([
            ['envio_dte_id' => $envio->id, 'estado_anterior' => 'ENVIADO', 'estado_nuevo' => 'ENVIADO', 'created_at' => now()->subMinute()],
            ['envio_dte_id' => $envio->id, 'estado_anterior' => 'ENVIADO', 'estado_nuevo' => 'ACEPTADO', 'created_at' => now()],
        ]);

        $eventos = $envio->eventos()->get();
        $this->assertCount(2, $eventos);
        $this->assertSame('ENVIADO', $eventos->first()->estado_nuevo);
        $this->assertSame('ACEPTADO', $eventos->last()->estado_nuevo);
    }

    public function test_factory_registrarTransicion_construye_evento_correcto(): void
    {
        $envio = $this->envioBase();

        $ev = SiiEnvioDteEvento::registrarTransicion(
            $envio,
            SiiEnvioDte::ESTADO_ENVIADO,
            SiiEnvioDte::ESTADO_ACEPTADO,
            'EOK',
            'Aceptado',
            200,
            ['extra' => 'foo']
        );

        $this->assertSame($envio->id, $ev->envio_dte_id);
        $this->assertSame('EOK', $ev->codigo_sii_raw);
        $this->assertSame(200, $ev->http_status);
        $this->assertSame('Aceptado', $ev->glosa);
        $this->assertSame('TRK_42', $ev->payload['track_id']);
        $this->assertSame('foo', $ev->payload['extra']);
    }

    public function test_factory_registrarTimeout_incluye_intentos_en_payload(): void
    {
        $envio = $this->envioBase();
        $envio->update(['intentos_polling' => 6]);

        $ev = SiiEnvioDteEvento::registrarTimeout($envio->fresh(), 6);

        $this->assertSame(SiiEnvioDte::ESTADO_ERROR_TIMEOUT, $ev->estado_nuevo);
        $this->assertSame(6, $ev->payload['intentos_polling']);
        $this->assertStringContainsString('Timeout acumulado', $ev->glosa);
    }
}
