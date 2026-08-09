<?php

namespace Tests\Feature\Integraciones;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * GET /ventas/{facturaId} (Fase 2, bloqueante 2): estado actual del DTE de una venta creada via
 * la API de integraciones, para que el canal externo haga polling de folio/pdf_url una vez que el
 * SII responda (al confirmar la venta todavia no existen, ver VentaIntegracionApiTest).
 */
class VentaEstadoIntegracionApiTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function habilitarModuloYEmitirKey(Empresa $empresa, array $scopes): string
    {
        $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['integraciones.api'],
        ]);

        $emitida = app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Test', $scopes);

        return $emitida['token'];
    }

    private function crearFactura(Empresa $empresa, array $overrides = []): Factura
    {
        return Factura::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'numero_factura' => 'FV-INT-'.strtoupper(substr(uniqid(), -6)),
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'tipo_dte' => 33,
            'fecha_emision' => now()->toDateString(),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190,
            'estado' => 'REGISTRADA',
        ], $overrides));
    }

    public function test_estado_devuelve_folio_y_pdf_url_null_mientras_el_dte_esta_pendiente(): void
    {
        $empresa = $this->crearEmpresa();
        $factura = $this->crearFactura($empresa);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/integraciones/v2/ventas/{$factura->id}");

        $respuesta->assertOk();
        $respuesta->assertJsonPath('data.factura_id', $factura->id);
        $respuesta->assertJsonPath('data.tipo_dte', 33);
        $respuesta->assertJsonPath('data.dte_estado', 'pendiente');
        $respuesta->assertJsonPath('data.folio', null);
        $respuesta->assertJsonPath('data.pdf_url', null);
    }

    public function test_estado_devuelve_folio_y_pdf_url_cuando_el_dte_ya_los_tiene(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sii/dte/factura-1.pdf', 'contenido-pdf-de-prueba');

        $empresa = $this->crearEmpresa();

        $dte = SiiDteEmitido::factory()->create([
            'empresa_id' => $empresa->id,
            'tipo_dte' => 33,
            'folio' => 1234,
            'estado' => SiiDteEmitido::ESTADO_ACEPTADO,
            'pdf_path' => 'sii/dte/factura-1.pdf',
        ]);

        $factura = $this->crearFactura($empresa, ['sii_dte_emitido_id' => $dte->id]);

        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/integraciones/v2/ventas/{$factura->id}");

        $respuesta->assertOk();
        $respuesta->assertJsonPath('data.folio', 1234);
        $respuesta->assertJsonPath('data.dte_estado', 'ACEPTADO');
        $this->assertNotNull($respuesta->json('data.pdf_url'));
        $this->assertStringContainsString('/storage/sii/dte/factura-1.pdf', $respuesta->json('data.pdf_url'));
    }

    public function test_estado_de_factura_de_otra_empresa_devuelve_404(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();
        $facturaB = $this->crearFactura($empresaB);

        $tokenA = $this->habilitarModuloYEmitirKey($empresaA, ['ventas:leer']);

        $this->withHeaders(['Authorization' => 'Bearer '.$tokenA])
            ->getJson("/api/integraciones/v2/ventas/{$facturaB->id}")
            ->assertNotFound();
    }

    public function test_estado_sin_scope_ventas_leer_es_rechazado(): void
    {
        $empresa = $this->crearEmpresa();
        $factura = $this->crearFactura($empresa);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/integraciones/v2/ventas/{$factura->id}")
            ->assertForbidden();
    }
}
