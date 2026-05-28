<?php

namespace Tests\Feature\Sii\Http;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class FacturaSiiControllerTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Cache::flush();
    }

    private function escenarioConFactura(int $empresaIdOverride = null, ?int $tipoDte = 33, ?string $estadoDte = null): array
    {
        if ($empresaIdOverride === null) {
            [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        } else {
            $empresa = Empresa::find($empresaIdOverride);
            $usuario = null;
        }

        $cliente = Cliente::create([
            'rut' => '11222333-4', 'razon_social' => 'CLI',
            'empresa_id' => $empresa->id, 'estado' => 'ACTIVO',
        ]);
        $factura = Factura::create([
            'empresa_id' => $empresa->id, 'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $cliente->id, 'numero_factura' => 'F-' . random_int(100, 9999),
            'tipo' => 'VENTA', 'tipo_documento' => 'FACTURA', 'tipo_dte' => $tipoDte,
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
                'tipo_dte'   => $tipoDte,
                'factura_id' => $factura->id,
            ]);
            $factura->update(['sii_dte_emitido_id' => $dte->id]);
        }

        return compact('empresa', 'usuario', 'cliente', 'factura', 'dte');
    }

    public function test_autorizacion_requerida_sin_auth_401(): void
    {
        $this->getJson('/api/sii/facturas/1/estado')->assertStatus(401);
        $this->getJson('/api/sii/facturas/1')->assertStatus(401);
        $this->getJson('/api/sii/facturas')->assertStatus(401);
    }

    public function test_index_lista_facturas_de_empresa_actual_paginadas(): void
    {
        $e = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->getJson('/api/sii/facturas');
        $r->assertStatus(200);
        $r->assertJsonStructure([
            'data' => [['factura_id', 'numero_factura', 'fecha_emision', 'estado_sii']],
            'paginacion' => ['total', 'por_pagina', 'pagina_actual', 'ultima_pagina'],
        ]);
        $this->assertSame(1, $r->json('paginacion.total'));
        $this->assertTrue((bool) $r->json('data.0.estado_sii.tiene_dte'));
    }

    public function test_index_NO_lista_facturas_de_otra_empresa(): void
    {
        $a = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        $b = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        Sanctum::actingAs($a['usuario']);

        $r = $this->getJson('/api/sii/facturas');
        $this->assertSame(1, $r->json('paginacion.total'));
        $idsVisibles = collect($r->json('data'))->pluck('factura_id')->all();
        $this->assertContains($a['factura']->id, $idsVisibles);
        $this->assertNotContains($b['factura']->id, $idsVisibles);
    }

    public function test_estado_factura_sin_dte_retorna_tiene_dte_false(): void
    {
        $e = $this->escenarioConFactura(estadoDte: null);
        Sanctum::actingAs($e['usuario']);

        $r = $this->getJson("/api/sii/facturas/{$e['factura']->id}/estado");
        $r->assertStatus(200);
        $r->assertJsonPath('data.tiene_dte', false);
        $r->assertJsonPath('data.dte_id', null);
        $r->assertJsonPath('data.estado', null);
        $r->assertJsonPath('data.es_terminal', false);
        $r->assertJsonPath('data.es_pollable', false);
    }

    public function test_estado_factura_con_dte_BORRADOR_es_pollable_true(): void
    {
        $e = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_BORRADOR);
        Sanctum::actingAs($e['usuario']);

        $r = $this->getJson("/api/sii/facturas/{$e['factura']->id}/estado");
        $r->assertJsonPath('data.tiene_dte', true);
        $r->assertJsonPath('data.estado', 'BORRADOR');
        $r->assertJsonPath('data.es_terminal', false);
        $r->assertJsonPath('data.es_pollable', true);
    }

    public function test_estado_factura_con_dte_ACEPTADO_es_terminal_true_pollable_false(): void
    {
        $e = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->getJson("/api/sii/facturas/{$e['factura']->id}/estado");
        $r->assertJsonPath('data.estado', 'ACEPTADO');
        $r->assertJsonPath('data.es_terminal', true);
        $r->assertJsonPath('data.es_pollable', false);
        $r->assertJsonPath('data.estado_glosa_humana', 'Aceptado por el SII');
    }

    public function test_estado_factura_con_dte_RECHAZADO_incluye_glosa_sii(): void
    {
        $e = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_RECHAZADO);
        $e['dte']->update(['glosa_sii' => 'Schema invalido X']);
        Sanctum::actingAs($e['usuario']);

        $r = $this->getJson("/api/sii/facturas/{$e['factura']->id}/estado");
        $r->assertJsonPath('data.estado', 'RECHAZADO');
        $r->assertJsonPath('data.glosa_sii', 'Schema invalido X');
        $r->assertJsonPath('data.es_terminal', true);
    }

    public function test_estado_retorna_404_si_factura_pertenece_a_otra_empresa(): void
    {
        $a = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        $b = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        Sanctum::actingAs($a['usuario']);

        $this->getJson("/api/sii/facturas/{$b['factura']->id}/estado")->assertStatus(404);
    }

    public function test_mostrar_carga_relaciones_anidadas_completas(): void
    {
        $e = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        Sanctum::actingAs($e['usuario']);

        $r = $this->getJson("/api/sii/facturas/{$e['factura']->id}");
        $r->assertStatus(200);
        $r->assertJsonStructure([
            'data' => [
                'factura_id', 'cliente_completo', 'detalles_factura',
                'estado_sii',
                'dte' => ['id', 'estado', 'folio', 'tipo_dte', 'monto_total', 'detalles', 'referencias', 'eventos', 'envios'],
            ],
        ]);
    }

    public function test_mostrar_incluye_eventos_dte_ordenados(): void
    {
        $e = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        // Insertar 2 eventos en orden cronologico.
        SiiDteEmitidoEvento::create([
            'dte_emitido_id' => $e['dte']->id,
            'estado_anterior' => 'BORRADOR', 'estado_nuevo' => 'FIRMADO',
            'glosa' => 'Primero', 'payload' => [],
        ]);
        SiiDteEmitidoEvento::create([
            'dte_emitido_id' => $e['dte']->id,
            'estado_anterior' => 'FIRMADO', 'estado_nuevo' => 'ENVIADO_SII',
            'glosa' => 'Segundo', 'payload' => [],
        ]);
        Sanctum::actingAs($e['usuario']);

        $r = $this->getJson("/api/sii/facturas/{$e['factura']->id}");
        $eventos = $r->json('data.dte.eventos');
        $this->assertCount(2, $eventos);
        $this->assertSame('BORRADOR', $eventos[0]['estado_anterior']);
        $this->assertSame('FIRMADO',  $eventos[1]['estado_anterior']);
    }

    public function test_aislamiento_multitenant_mostrar_404(): void
    {
        $a = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        $b = $this->escenarioConFactura(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);
        Sanctum::actingAs($a['usuario']);

        $this->getJson("/api/sii/facturas/{$b['factura']->id}")->assertStatus(404);
    }

    public function test_throttle_aplicado_correctamente(): void
    {
        // sii-empresa: 60 req/min. Hacemos 60 a /api/sii/ping (que tambien tiene throttle).
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/sii/facturas');
        }
        // 61va debe 429
        $r = $this->getJson('/api/sii/facturas');
        $this->assertSame(429, $r->status());
    }

    public function test_paginacion_respeta_por_pagina(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $cliente = Cliente::create([
            'rut' => '11', 'razon_social' => 'C', 'empresa_id' => $empresa->id, 'estado' => 'ACTIVO',
        ]);
        for ($i = 0; $i < 5; $i++) {
            Factura::create([
                'empresa_id' => $empresa->id, 'codigo_unico' => Factura::generarCodigoUnico(),
                'cliente_id' => $cliente->id, 'numero_factura' => 'F' . $i,
                'tipo' => 'VENTA', 'tipo_documento' => 'FACTURA', 'tipo_dte' => 33,
                'fecha_emision' => now()->toDateString(),
                'monto_neto' => 1, 'monto_iva' => 0, 'monto_bruto' => 1,
                'estado' => 'REGISTRADA',
            ]);
        }
        Sanctum::actingAs($usuario);

        $r = $this->getJson('/api/sii/facturas?por_pagina=2');
        $this->assertSame(5, $r->json('paginacion.total'));
        $this->assertSame(2, $r->json('paginacion.por_pagina'));
        $this->assertCount(2, $r->json('data'));
    }
}
