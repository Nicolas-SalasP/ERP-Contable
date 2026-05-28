<?php

namespace Tests\Feature\Sii\Http;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Sii\Jobs\ReintentarEmisionDteJob;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiEnvioDte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class FacturaSiiControllerReintentarTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Cache::flush();
    }

    private function escenario(?string $estadoDte = null, ?string $estadoUltimoEnvio = null): array
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $cliente = Cliente::create([
            'rut' => '11222333-4', 'razon_social' => 'CLI',
            'empresa_id' => $empresa->id, 'estado' => 'ACTIVO',
        ]);
        $factura = Factura::create([
            'empresa_id' => $empresa->id, 'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $cliente->id, 'numero_factura' => 'F-' . random_int(100, 9999),
            'tipo' => 'VENTA', 'tipo_documento' => 'FACTURA', 'tipo_dte' => 33,
            'fecha_emision' => now()->toDateString(),
            'monto_neto' => 1000, 'monto_iva' => 190, 'monto_bruto' => 1190,
            'estado' => 'REGISTRADA',
        ]);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000,
            'monto_item' => 1000, 'exento' => false,
        ]);

        $dte = null;
        if ($estadoDte !== null) {
            $dte = SiiDteEmitido::factory()->create([
                'empresa_id' => $empresa->id,
                'estado'     => $estadoDte,
                'tipo_dte'   => 33,
                'factura_id' => $factura->id,
            ]);
            $factura->update(['sii_dte_emitido_id' => $dte->id]);
            if ($estadoUltimoEnvio !== null) {
                SiiEnvioDte::create([
                    'empresa_id'     => $empresa->id,
                    'dte_emitido_id' => $dte->id,
                    'ambiente_sii'   => $empresa->ambiente_sii ?? 'certificacion',
                    'estado_envio'   => $estadoUltimoEnvio,
                    'intentos_envio' => 1, 'intentos_polling' => 0,
                    'fecha_envio'    => now(),
                ]);
            }
        }
        return compact('empresa', 'usuario', 'factura', 'dte');
    }

    public function test_autorizacion_requerida_sin_auth_401(): void
    {
        $this->postJson('/api/sii/facturas/1/reintentar')->assertStatus(401);
    }

    public function test_reintento_sobre_factura_emisible_retorna_202(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_BORRADOR);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar", [
            'razon' => 'red intermitente',
        ]);

        $r->assertStatus(202);
        $r->assertJsonPath('data.factura_id', $e['factura']->id);
        $r->assertJsonPath('data.accion_encolada', 'reanudar_firma');
        Bus::assertDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_reintento_sin_razon_es_valido(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_FIRMADO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar", []);
        $r->assertStatus(202);
        $r->assertJsonPath('data.accion_encolada', 'reanudar_envio');
    }

    public function test_reintento_sobre_dte_terminal_retorna_422_con_razon_estructurada(): void
    {
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar");
        $r->assertStatus(422);
        $r->assertJsonPath('error.razon', 'estado_terminal');
        $r->assertJsonPath('error.estado_actual', 'ACEPTADO');
    }

    public function test_reintento_sobre_dte_ENVIADO_SII_proceso_normal_retorna_422_ya_en_proceso(): void
    {
        $e = $this->escenario(
            estadoDte: SiiDteEmitido::ESTADO_ENVIADO_SII,
            estadoUltimoEnvio: SiiEnvioDte::ESTADO_ENVIADO
        );
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar");
        $r->assertStatus(422);
        $r->assertJsonPath('error.razon', 'ya_en_proceso');
        $r->assertJsonPath('error.estado_actual', SiiEnvioDte::ESTADO_ENVIADO);
    }

    public function test_reintento_sobre_factura_otra_empresa_retorna_404(): void
    {
        $a = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_BORRADOR);
        $b = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_BORRADOR);
        Sanctum::actingAs($a['usuario']);

        $this->postJson("/api/sii/facturas/{$b['factura']->id}/reintentar")->assertStatus(404);
    }

    public function test_razon_excede_200_chars_retorna_422_validacion(): void
    {
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_BORRADOR);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar", [
            'razon' => str_repeat('A', 201),
        ]);
        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['razon']);
    }

    public function test_throttle_aplicado_correctamente(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_BORRADOR);
        Sanctum::actingAs($e['usuario']);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/sii/facturas');
        }
        $r = $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar");
        $this->assertSame(429, $r->status());
    }

    public function test_payload_estado_incluye_ultimo_envio_estado_error_true(): void
    {
        $e = $this->escenario(
            estadoDte: SiiDteEmitido::ESTADO_ENVIADO_SII,
            estadoUltimoEnvio: SiiEnvioDte::ESTADO_ERROR_TRANSPORTE
        );
        Sanctum::actingAs($e['usuario']);

        $r = $this->getJson("/api/sii/facturas/{$e['factura']->id}/estado");
        $r->assertStatus(200);
        $r->assertJsonPath('data.ultimo_envio_estado', SiiEnvioDte::ESTADO_ERROR_TRANSPORTE);
        $r->assertJsonPath('data.ultimo_envio_estado_error', true);
    }

    public function test_payload_estado_ultimo_envio_estado_error_false_si_envio_normal(): void
    {
        $e = $this->escenario(
            estadoDte: SiiDteEmitido::ESTADO_ENVIADO_SII,
            estadoUltimoEnvio: SiiEnvioDte::ESTADO_ENVIADO
        );
        Sanctum::actingAs($e['usuario']);

        $r = $this->getJson("/api/sii/facturas/{$e['factura']->id}/estado");
        $r->assertJsonPath('data.ultimo_envio_estado', SiiEnvioDte::ESTADO_ENVIADO);
        $r->assertJsonPath('data.ultimo_envio_estado_error', false);
    }
}
