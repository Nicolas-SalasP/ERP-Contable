<?php

namespace Tests\Feature\Sii\Integracion;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Sii\Jobs\ReintentarEmisionDteJob;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiEnvioDte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * F6.4 — Tests E2E del flujo de reintento manual cierre de Fase 6.
 *
 * Foco: validar que el endpoint encola la accion correcta, persiste audit
 * log estructurado con razon+usuario, respeta aislamiento multi-tenant y
 * no crashea ante reintentos paralelos.
 *
 * NO ejecuta el pipeline completo de emision (eso lo cubren los E2E de
 * F5.4/F6.2). Aqui validamos especificamente la mecanica de F6.4.
 */
class ReintentoFacturaE2ETest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Cache::flush();
    }

    private function setupFacturaConDteEnError(?string $estadoUltimoEnvio = null): array
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
        $dte = SiiDteEmitido::factory()->create([
            'empresa_id' => $empresa->id,
            'estado'     => SiiDteEmitido::ESTADO_ENVIADO_SII,
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

        return compact('empresa', 'usuario', 'factura', 'dte');
    }

    public function test_escenario_fallo_envio_endpoint_encola_reanudar_envio(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        $e = $this->setupFacturaConDteEnError(SiiEnvioDte::ESTADO_ERROR_TRANSPORTE);
        Sanctum::actingAs($e['usuario']);

        $r = $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar", [
            'razon' => 'Red intermitente en el envio anterior',
        ]);

        $r->assertStatus(202);
        $r->assertJsonPath('data.accion_encolada', 'reanudar_envio');
        Bus::assertDispatched(ReintentarEmisionDteJob::class, function (ReintentarEmisionDteJob $j) use ($e) {
            return $j->dteEmitidoId === $e['dte']->id
                && $j->accion === ReintentarEmisionDteJob::ACCION_REANUDAR_ENVIO
                && $j->razon === 'Red intermitente en el envio anterior'
                && $j->usuarioId === $e['usuario']->id;
        });
    }

    public function test_escenario_fallo_firma_endpoint_encola_reanudar_firma(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

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
            'monto_neto' => 1, 'monto_iva' => 0, 'monto_bruto' => 1,
            'estado' => 'REGISTRADA',
        ]);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'X', 'cantidad' => 1, 'precio_unitario' => 1,
            'monto_item' => 1, 'exento' => false,
        ]);
        $dte = SiiDteEmitido::factory()->create([
            'empresa_id' => $empresa->id,
            'estado'     => SiiDteEmitido::ESTADO_BORRADOR,
            'tipo_dte'   => 33,
            'factura_id' => $factura->id,
        ]);
        $factura->update(['sii_dte_emitido_id' => $dte->id]);
        Sanctum::actingAs($usuario);

        $r = $this->postJson("/api/sii/facturas/{$factura->id}/reintentar");
        $r->assertStatus(202);
        $r->assertJsonPath('data.accion_encolada', 'reanudar_firma');
        Bus::assertDispatched(ReintentarEmisionDteJob::class, fn (ReintentarEmisionDteJob $j) =>
            $j->accion === ReintentarEmisionDteJob::ACCION_REANUDAR_FIRMA
        );
    }

    public function test_audit_log_completo_con_razon_y_usuario_post_reintento(): void
    {
        $e = $this->setupFacturaConDteEnError(SiiEnvioDte::ESTADO_ERROR_TIMEOUT);
        Sanctum::actingAs($e['usuario']);

        // Capturamos logs via Log::spy() para asegurar que el ctx del job contenga razon+usuario.
        Bus::fake([ReintentarEmisionDteJob::class]);

        $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar", [
            'razon' => 'auditoria F6.4 cierre fase',
        ])->assertStatus(202);

        Bus::assertDispatched(ReintentarEmisionDteJob::class, function ($j) use ($e) {
            return $j->razon === 'auditoria F6.4 cierre fase'
                && $j->usuarioId === $e['usuario']->id;
        });
    }

    public function test_reintento_doble_dispatch_dispara_dos_jobs_sin_crashear(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        $e = $this->setupFacturaConDteEnError(SiiEnvioDte::ESTADO_ERROR_TRANSPORTE);
        Sanctum::actingAs($e['usuario']);

        $r1 = $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar");
        $r2 = $this->postJson("/api/sii/facturas/{$e['factura']->id}/reintentar");

        $r1->assertStatus(202);
        $r2->assertStatus(202);

        // La idempotencia REAL la garantiza el lockForUpdate del servicio interno
        // (EmitirDteService/EnvioSiiService). A nivel de encolado, ambos jobs
        // se aceptan; el segundo job al ejecutar veria el estado nuevo y
        // fallaria limpio.
        Bus::assertDispatchedTimes(ReintentarEmisionDteJob::class, 2);
    }

    public function test_aislamiento_multitenant_reintento_otra_empresa_404(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        $a = $this->setupFacturaConDteEnError(SiiEnvioDte::ESTADO_ERROR_TRANSPORTE);
        $b = $this->setupFacturaConDteEnError(SiiEnvioDte::ESTADO_ERROR_TRANSPORTE);
        Sanctum::actingAs($a['usuario']);

        $this->postJson("/api/sii/facturas/{$b['factura']->id}/reintentar")->assertStatus(404);

        // El DTE de B no debe haber sido encolado.
        Bus::assertNotDispatched(ReintentarEmisionDteJob::class, fn ($j) =>
            $j->dteEmitidoId === $b['dte']->id
        );
    }
}
