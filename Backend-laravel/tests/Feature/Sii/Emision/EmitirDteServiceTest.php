<?php

namespace Tests\Feature\Sii\Emision;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\CertificadoInvalidoException;
use App\Domains\Sii\Exceptions\DteEstadoInvalidoException;
use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Exceptions\SinFoliosDisponiblesException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Xml\SetDte\SetDteSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class EmitirDteServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    private EmitirDteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        Storage::fake(config('sii.storage.disk', 'local'));
        $this->service = app(EmitirDteService::class);
    }

    /**
     * @return array{empresa: Empresa, dte: SiiDteEmitido, caf: SiiCaf}
     */
    private function escenarioCompleto(string $rut = '76555444-3'): array
    {
        $empresa = Empresa::create([
            'rut'                   => $rut,
            'razon_social'          => 'EMPRESA EMITIR',
            'giro_emisor'           => 'Comercio',
            'codigo_actividad_sii'  => 471910,
            'direccion'             => 'Av Principal 100',
            'comuna'                => 'Santiago',
            'ciudad'                => 'Santiago',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'TEST EMITIR ' . $rut);
        [$caf] = $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $dte = $this->crearDteBorrador($empresa);

        return compact('empresa', 'dte', 'caf');
    }

    private function crearDteBorrador(Empresa $empresa, int $tipo = 33): SiiDteEmitido
    {
        $dte = SiiDteEmitido::create([
            'empresa_id'           => $empresa->id,
            'tipo_dte'             => $tipo,
            'folio'                => random_int(900_000, 999_999),
            'fecha_emision'        => now()->toDateString(),
            'emisor_rut'           => $empresa->rut,
            'emisor_razon_social'  => $empresa->razon_social,
            'emisor_giro'          => 'Comercio',
            'emisor_acteco'        => 471910,
            'emisor_direccion'     => 'Calle 1',
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
            'nombre_item'     => 'Producto',
            'cantidad'        => 1,
            'precio_unitario' => 1000,
            'monto_item'      => 1000,
        ]);

        return $dte->fresh();
    }

    public function test_emite_dte_en_borrador_transiciona_a_firmado(): void
    {
        $e = $this->escenarioCompleto();
        $firmado = $this->service->emitir($e['dte']->id);

        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $firmado->estado);
        $this->assertNotNull($firmado->fecha_firma);
    }

    public function test_persiste_xml_en_disco_en_path_estructurado(): void
    {
        $e = $this->escenarioCompleto();
        $firmado = $this->service->emitir($e['dte']->id);

        $this->assertNotEmpty($firmado->xml_path);
        $this->assertMatchesRegularExpression(
            '#^sii/\d+/\d{4}/\d{2}/33_\d+_envio\.xml$#',
            $firmado->xml_path
        );
        Storage::disk(config('sii.storage.disk', 'local'))->assertExists($firmado->xml_path);
    }

    public function test_persiste_backup_cifrado_en_columna_xml_completo_cifrado(): void
    {
        $e = $this->escenarioCompleto();
        $firmado = $this->service->emitir($e['dte']->id);

        $this->assertNotNull($firmado->xml_completo_cifrado);
        // No debe ser legible directamente (debe ser cifrado por Crypt).
        $this->assertStringNotContainsString('<EnvioDTE', $firmado->xml_completo_cifrado);
        // Pero descifrable con APP_KEY.
        $xmlPlain = Crypt::decryptString($firmado->xml_completo_cifrado);
        $this->assertStringContainsString('<EnvioDTE', $xmlPlain);
    }

    public function test_calcula_hash_sha256_correcto_del_xml_en_claro(): void
    {
        $e = $this->escenarioCompleto();
        $firmado = $this->service->emitir($e['dte']->id);

        $this->assertSame(64, strlen($firmado->xml_hash_sha256));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $firmado->xml_hash_sha256);

        $xmlPlain = Crypt::decryptString($firmado->xml_completo_cifrado);
        $this->assertSame(hash('sha256', $xmlPlain), $firmado->xml_hash_sha256);

        // Y el archivo en disco tambien debe tener el mismo hash.
        $xmlDisco = Storage::disk(config('sii.storage.disk', 'local'))->get($firmado->xml_path);
        $this->assertSame($firmado->xml_hash_sha256, hash('sha256', $xmlDisco));
    }

    public function test_reserva_folio_caf_y_lo_vincula_al_dte(): void
    {
        $e = $this->escenarioCompleto();
        $firmado = $this->service->emitir($e['dte']->id);

        $this->assertSame($e['caf']->id, $firmado->caf_id);
        $this->assertGreaterThanOrEqual($e['caf']->folio_desde, $firmado->folio);
        $this->assertLessThanOrEqual($e['caf']->folio_hasta, $firmado->folio);
    }

    public function test_marca_folio_como_USADO_post_emision(): void
    {
        $e = $this->escenarioCompleto();
        $firmado = $this->service->emitir($e['dte']->id);

        $folioUso = SiiCafFolioUso::where('caf_id', $firmado->caf_id)
            ->where('folio', $firmado->folio)
            ->first();

        $this->assertNotNull($folioUso);
        $this->assertSame(SiiCafFolioUso::ESTADO_USADO, $folioUso->estado);
        $this->assertSame($firmado->id, $folioUso->dte_emitido_id);
        $this->assertNotNull($folioUso->usado_at);
    }

    public function test_lanza_DteEstadoInvalidoException_si_dte_no_esta_en_borrador(): void
    {
        $e = $this->escenarioCompleto();
        $e['dte']->update(['estado' => SiiDteEmitido::ESTADO_FIRMADO]);

        try {
            $this->service->emitir($e['dte']->id);
            $this->fail('Debio lanzar DteEstadoInvalidoException');
        } catch (DteEstadoInvalidoException $ex) {
            $this->assertSame(DteEstadoInvalidoException::MOTIVO_NO_ES_BORRADOR, $ex->motivo);
            $this->assertSame($e['dte']->id, $ex->dteId);
            $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $ex->estadoActual);
        }
    }

    public function test_lanza_CertificadoInvalidoException_si_empresa_sin_cert_activo(): void
    {
        $empresa = Empresa::create([
            'rut'                   => '77111222-3',
            'razon_social'          => 'SIN CERT',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
        $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);
        $dte = $this->crearDteBorrador($empresa);

        $this->expectException(CertificadoInvalidoException::class);
        $this->service->emitir($dte->id);

        // El DTE NO debe haber cambiado de estado (precondicion falla antes de reservar folio).
        $this->assertSame(
            SiiDteEmitido::ESTADO_BORRADOR,
            $dte->fresh()->estado,
            'DTE no debe transicionar si cert falta.'
        );
        // Ningun folio debe haberse consumido.
        $this->assertSame(0, SiiCafFolioUso::count(), 'No deben haberse reservado folios.');
    }

    public function test_lanza_SinFoliosDisponiblesException_si_caf_agotado(): void
    {
        $empresa = Empresa::create([
            'rut'                   => '77333444-5',
            'razon_social'          => 'SIN FOLIOS',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $empresa->rut);
        // NO crear CAF.
        $dte = $this->crearDteBorrador($empresa);

        $this->expectException(SinFoliosDisponiblesException::class);
        $this->service->emitir($dte->id);
    }

    public function test_lanza_DteIncompletoException_si_dte_sin_detalles(): void
    {
        $e = $this->escenarioCompleto();
        $e['dte']->detalles()->delete();

        $this->expectException(DteIncompletoException::class);
        $this->service->emitir($e['dte']->id);
    }

    public function test_rollback_si_falla_firma_setdte_estado_vuelve_a_borrador_y_folio_huerfano(): void
    {
        $e = $this->escenarioCompleto();

        // Inyectamos un SetDteSigner mock que SIEMPRE lanza, simulando un
        // fallo posterior a la reserva del folio. Esto es el escenario clave
        // de la spec: post-reserva debe quedar folio HUERFANO y DTE en BORRADOR.
        $this->app->instance(SetDteSigner::class, Mockery::mock(SetDteSigner::class)
            ->shouldReceive('firmar')->andThrow(new \RuntimeException('boom en firma SetDTE'))
            ->getMock());

        // Re-crear service con el mock inyectado.
        $service = app(EmitirDteService::class);

        try {
            $service->emitir($e['dte']->id);
            $this->fail('Debio propagar la excepcion del SetDteSigner.');
        } catch (\RuntimeException $ex) {
            $this->assertSame('boom en firma SetDTE', $ex->getMessage());
        }

        $dteFresh = $e['dte']->fresh();
        $this->assertSame(SiiDteEmitido::ESTADO_BORRADOR, $dteFresh->estado);
        $this->assertNull($dteFresh->xml_path);
        $this->assertNull($dteFresh->xml_hash_sha256);
        $this->assertNull($dteFresh->fecha_firma);

        // El folio reservado debe quedar como HUERFANO (no desaparecio del row).
        $huerfanos = SiiCafFolioUso::where('caf_id', $e['caf']->id)
            ->where('estado', SiiCafFolioUso::ESTADO_HUERFANO)
            ->get();
        $this->assertCount(1, $huerfanos, 'Debe existir 1 folio HUERFANO post-fallo.');
        $this->assertStringContainsString('Fallo en EmitirDteService', $huerfanos->first()->razon_liberacion);
    }

    public function test_log_canal_sii_incluye_dte_id_folio_y_hash(): void
    {
        // Capturamos los log records del canal sii via un handler en memoria.
        // Esto evita los problemas conocidos de Log::spy() con channel().
        $records = [];
        Log::channel('sii')->getLogger()->pushHandler(
            new \Monolog\Handler\TestHandler()
        );

        $e = $this->escenarioCompleto();
        $firmado = $this->service->emitir($e['dte']->id);

        // El handler TestHandler captura todos los records del canal sii
        // del logger Monolog subyacente.
        /** @var \Monolog\Handler\TestHandler $handler */
        $handler = collect(Log::channel('sii')->getLogger()->getHandlers())
            ->first(fn ($h) => $h instanceof \Monolog\Handler\TestHandler);

        $this->assertNotNull($handler, 'TestHandler debe estar registrado.');

        $registro = collect($handler->getRecords())
            ->first(fn ($r) => str_contains((string) $r['message'], 'DTE emitido y firmado'));

        $this->assertNotNull($registro, 'Debe existir log "DTE emitido y firmado" en canal sii.');
        $contexto = $registro['context'];

        $this->assertSame($firmado->id, $contexto['dte_id']);
        $this->assertSame($firmado->folio, $contexto['folio']);
        $this->assertSame($firmado->xml_hash_sha256, $contexto['xml_hash_sha256']);
        $this->assertSame($firmado->caf_id, $contexto['caf_id']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
