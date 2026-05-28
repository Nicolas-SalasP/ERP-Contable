<?php

namespace Tests\Feature\Sii\Emision;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoEvento;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiDteEmitidoEventoTest extends TestCase
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

    private function escenarioYemitir(): SiiDteEmitido
    {
        $empresa = Empresa::create([
            'rut'                   => '76555444-3',
            'razon_social'          => 'EMPRESA EVENTO',
            'giro_emisor'           => 'Servicios',
            'codigo_actividad_sii'  => 471910,
            'direccion'             => 'X 1', 'comuna' => 'Stgo', 'ciudad' => 'Stgo',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $empresa->rut);
        $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $dte = SiiDteEmitido::create([
            'empresa_id'           => $empresa->id,
            'tipo_dte'             => 33,
            'folio'                => random_int(900_000, 999_999),
            'fecha_emision'        => now()->toDateString(),
            'emisor_rut'           => $empresa->rut,
            'emisor_razon_social'  => $empresa->razon_social,
            'emisor_giro'          => 'Servicios',
            'emisor_acteco'        => 471910,
            'emisor_direccion'     => 'X 1',
            'emisor_comuna'        => 'Santiago',
            'receptor_rut'         => '66666666-6',
            'receptor_razon_social' => 'CLIENTE PRUEBA',
            'moneda'               => 'CLP',
            'monto_neto'           => 1000,
            'monto_exento'         => 0,
            'tasa_iva'             => 19.00,
            'iva'                  => 190,
            'monto_total'          => 1190,
            'estado'               => SiiDteEmitido::ESTADO_BORRADOR,
            'es_cedible'           => true,
        ]);
        SiiDteEmitidoDetalle::create([
            'dte_emitido_id'  => $dte->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Servicio',
            'cantidad'        => 1,
            'precio_unitario' => 1000,
            'monto_item'      => 1000,
        ]);

        return app(EmitirDteService::class)->emitir($dte->id);
    }

    public function test_emitir_dte_crea_evento_con_estado_anterior_BORRADOR_y_nuevo_FIRMADO(): void
    {
        $firmado = $this->escenarioYemitir();

        $eventos = SiiDteEmitidoEvento::where('dte_emitido_id', $firmado->id)->get();
        $this->assertCount(1, $eventos);

        $evento = $eventos->first();
        $this->assertSame(SiiDteEmitido::ESTADO_BORRADOR, $evento->estado_anterior);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $evento->estado_nuevo);
    }

    public function test_evento_incluye_folio_y_hash_en_payload(): void
    {
        $firmado = $this->escenarioYemitir();

        $evento = SiiDteEmitidoEvento::where('dte_emitido_id', $firmado->id)->first();
        $payload = $evento->payload;

        $this->assertIsArray($payload);
        $this->assertSame($firmado->folio, $payload['folio']);
        $this->assertSame($firmado->xml_hash_sha256, $payload['xml_hash_sha256']);
        $this->assertSame($firmado->caf_id, $payload['caf_id']);
        $this->assertSame((int) $firmado->tipo_dte, $payload['tipo_dte']);
    }

    public function test_eventos_son_inmutables_no_tienen_updated_at(): void
    {
        // Verificamos a nivel de schema que la tabla NO tiene updated_at.
        $columnas = Schema::getColumnListing('sii_dte_emitido_evento');
        $this->assertNotContains('updated_at', $columnas);
        $this->assertContains('created_at', $columnas);

        // Y a nivel de modelo: $timestamps esta deshabilitado.
        $evento = new SiiDteEmitidoEvento();
        $this->assertFalse($evento->timestamps);
    }

    public function test_relacion_dte_eventos_retorna_eventos_ordenados_por_created_at(): void
    {
        $firmado = $this->escenarioYemitir();

        // Inyectamos un evento manual posterior para verificar el orden.
        DB::table('sii_dte_emitido_evento')->insert([
            'dte_emitido_id'  => $firmado->id,
            'estado_anterior' => SiiDteEmitido::ESTADO_FIRMADO,
            'estado_nuevo'    => SiiDteEmitido::ESTADO_ENVIADO_SII,
            'glosa'           => 'Test orden',
            'payload'         => json_encode(['simulado' => true]),
            'created_at'      => now()->addMinute(),
        ]);

        $eventos = $firmado->eventos()->get();
        $this->assertCount(2, $eventos);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $eventos->first()->estado_nuevo);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $eventos->last()->estado_nuevo);
    }

    public function test_factory_registrarFirma_construye_evento_correcto(): void
    {
        $empresa = Empresa::create(['rut' => '76111222-K', 'razon_social' => 'X']);
        $dte = SiiDteEmitido::factory()->create([
            'empresa_id' => $empresa->id,
            'tipo_dte'   => 33,
            'caf_id'     => 99,
            'xml_path'   => 'sii/1/2026/05/33_5_envio.xml',
        ]);

        $evento = SiiDteEmitidoEvento::registrarFirma($dte, 5, 'abcdef0123');

        $this->assertSame($dte->id, $evento->dte_emitido_id);
        $this->assertSame(SiiDteEmitido::ESTADO_BORRADOR, $evento->estado_anterior);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $evento->estado_nuevo);
        $this->assertSame(5, $evento->payload['folio']);
        $this->assertSame('abcdef0123', $evento->payload['xml_hash_sha256']);
        $this->assertSame(99, $evento->payload['caf_id']);
        $this->assertSame('sii/1/2026/05/33_5_envio.xml', $evento->payload['xml_path']);
    }
}
