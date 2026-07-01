<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class LibroComprasVentasTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    /** @var \App\Domains\Core\Models\Empresa */
    private $empresa;

    /** @var \App\Domains\Core\Models\User */
    private $usuario;

    private Proveedor $proveedor;

    private int $facturaContador = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'rut'            => '76111222-3',
            'razon_social'   => 'Proveedor LCV Test',
            'codigo_interno' => 'PLCV',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    private function crearDteEmitido(array $override = []): SiiDteEmitido
    {
        return SiiDteEmitido::create(array_merge([
            'empresa_id'            => $this->empresa->id,
            'tipo_dte'              => SiiDteEmitido::TIPO_FACTURA,
            'folio'                 => random_int(1, 99999),
            'fecha_emision'         => '2026-06-15',
            'emisor_rut'            => $this->empresa->rut,
            'emisor_razon_social'   => $this->empresa->razon_social,
            'emisor_giro'           => 'Servicios',
            'emisor_direccion'      => 'Av. Principal 123',
            'emisor_comuna'         => 'Santiago',
            'receptor_rut'          => '12345678-9',
            'receptor_razon_social' => 'Cliente Test SA',
            'moneda'                => 'CLP',
            'monto_neto'            => 100000,
            'monto_exento'          => 0,
            'tasa_iva'              => 19.00,
            'iva'                   => 19000,
            'monto_total'           => 119000,
            'estado'                => SiiDteEmitido::ESTADO_ACEPTADO,
            'es_cedible'            => true,
        ], $override));
    }

    private function crearFacturaCompra(array $override = []): Factura
    {
        $this->facturaContador++;
        return Factura::create(array_merge([
            'empresa_id'            => $this->empresa->id,
            'proveedor_id'          => $this->proveedor->id,
            'numero_factura'        => 'FAC-' . $this->facturaContador,
            'tipo'                  => 'COMPRA',
            'codigo_unico'          => 100000 + $this->facturaContador,
            'fecha_emision'         => '2026-06-15',
            'monto_bruto'           => 119000,
            'monto_neto'            => 100000,
            'monto_iva'             => 19000,
            'estado'                => 'REGISTRADA',
            'es_documento_exterior' => false,
        ], $override));
    }

    public function test_lcv_ventas_retorna_estructura_correcta(): void
    {
        $this->crearDteEmitido(['estado' => SiiDteEmitido::ESTADO_ACEPTADO]);

        $respuesta = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/lcv/ventas/6/2026');

        $respuesta->assertStatus(200)
            ->assertJsonPath('tipo', 'VENTAS')
            ->assertJsonStructure([
                'periodo',
                'empresa' => ['razon_social', 'rut'],
                'tipo',
                'lineas',
                'totales' => ['monto_neto', 'iva', 'monto_exento', 'monto_total', 'cantidad'],
            ]);

        $this->assertCount(1, $respuesta->json('lineas'));
        $this->assertEquals(1, $respuesta->json('totales.cantidad'));
    }

    public function test_lcv_ventas_excluye_estados_borrador_y_rechazado(): void
    {
        $this->crearDteEmitido(['estado' => SiiDteEmitido::ESTADO_BORRADOR]);
        $this->crearDteEmitido(['estado' => SiiDteEmitido::ESTADO_RECHAZADO]);
        $this->crearDteEmitido(['estado' => SiiDteEmitido::ESTADO_ACEPTADO]);

        $respuesta = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/lcv/ventas/6/2026');

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('lineas'), 'Solo el DTE ACEPTADO debe aparecer');
    }

    public function test_lcv_ventas_incluye_notas_credito(): void
    {
        $this->crearDteEmitido([
            'tipo_dte' => SiiDteEmitido::TIPO_NOTA_CREDITO,
            'estado'   => SiiDteEmitido::ESTADO_ACEPTADO,
        ]);

        $respuesta = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/lcv/ventas/6/2026');

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('lineas'));
        $this->assertEquals(
            SiiDteEmitido::TIPO_NOTA_CREDITO,
            $respuesta->json('lineas.0.tipo_dte'),
        );
        $this->assertEquals('Nota de Crédito', $respuesta->json('lineas.0.tipo_dte_glosa'));
    }

    public function test_lcv_compras_retorna_facturas_compra(): void
    {
        $this->crearFacturaCompra();

        $respuesta = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/lcv/compras/6/2026');

        $respuesta->assertStatus(200)
            ->assertJsonPath('tipo', 'COMPRAS');

        $this->assertCount(1, $respuesta->json('lineas'));
        $this->assertArrayHasKey('iva_recuperable', $respuesta->json('lineas.0'));
        $this->assertEquals('GIRO', $respuesta->json('lineas.0.indicador_uso'));
    }

    public function test_lcv_compras_excluye_facturas_venta(): void
    {
        $this->crearFacturaCompra();
        $this->crearFacturaCompra(['tipo' => 'VENTA']);

        $respuesta = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/lcv/compras/6/2026');

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('lineas'), 'Solo la factura tipo COMPRA debe aparecer');
    }

    public function test_lcv_compras_excluye_exterior(): void
    {
        $this->crearFacturaCompra();
        $this->crearFacturaCompra(['es_documento_exterior' => true]);

        $respuesta = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/lcv/compras/6/2026');

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('lineas'), 'La factura del exterior no debe aparecer');
    }

    public function test_lcv_totales_coinciden_con_suma_lineas(): void
    {
        $this->crearDteEmitido([
            'monto_neto'  => 100000,
            'iva'         => 19000,
            'monto_total' => 119000,
            'estado'      => SiiDteEmitido::ESTADO_ACEPTADO,
        ]);
        $this->crearDteEmitido([
            'monto_neto'  => 200000,
            'iva'         => 38000,
            'monto_total' => 238000,
            'estado'      => SiiDteEmitido::ESTADO_ACEPTADO,
        ]);

        $respuesta = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/lcv/ventas/6/2026');

        $respuesta->assertStatus(200);
        $lineas  = $respuesta->json('lineas');
        $totales = $respuesta->json('totales');

        $sumaLineasNeto  = (int) array_sum(array_column($lineas, 'monto_neto'));
        $sumaLineasTotal = (int) array_sum(array_column($lineas, 'monto_total'));

        $this->assertEquals($sumaLineasNeto, $totales['monto_neto']);
        $this->assertEquals($sumaLineasTotal, $totales['monto_total']);
        $this->assertEquals(2, $totales['cantidad']);
    }

    public function test_aislamiento_multitenant(): void
    {
        [$empresaB] = $this->crearEmpresaConAdmin();

        // DTE creado para empresa B
        SiiDteEmitido::create([
            'empresa_id'            => $empresaB->id,
            'tipo_dte'              => SiiDteEmitido::TIPO_FACTURA,
            'folio'                 => 9999,
            'fecha_emision'         => '2026-06-15',
            'emisor_rut'            => $empresaB->rut,
            'emisor_razon_social'   => $empresaB->razon_social,
            'emisor_giro'           => 'Servicios',
            'emisor_direccion'      => 'Av. Test 1',
            'emisor_comuna'         => 'Santiago',
            'receptor_rut'          => '99999999-0',
            'receptor_razon_social' => 'Receptor B SA',
            'moneda'                => 'CLP',
            'monto_neto'            => 500000,
            'monto_exento'          => 0,
            'tasa_iva'              => 19.00,
            'iva'                   => 95000,
            'monto_total'           => 595000,
            'estado'                => SiiDteEmitido::ESTADO_ACEPTADO,
            'es_cedible'            => true,
        ]);

        // Autenticado como usuario de empresa A, NO debe ver datos de empresa B
        $respuesta = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/lcv/ventas/6/2026');

        $respuesta->assertStatus(200);
        $this->assertCount(0, $respuesta->json('lineas'), 'Empresa A no debe ver DTEs de empresa B');
        $this->assertEquals(0, $respuesta->json('totales.monto_total'));
    }

    public function test_descargar_csv_ventas_responde_200(): void
    {
        $this->crearDteEmitido(['estado' => SiiDteEmitido::ESTADO_ACEPTADO]);

        $respuesta = $this->actingAs($this->usuario)
            ->get('/api/impuestos/lcv/ventas/6/2026/descargar?formato=csv');

        $respuesta->assertStatus(200);
        $this->assertStringContainsString(
            'text/csv',
            (string) $respuesta->headers->get('Content-Type'),
        );
    }

    public function test_descargar_csv_compras_responde_200(): void
    {
        $this->crearFacturaCompra();

        $respuesta = $this->actingAs($this->usuario)
            ->get('/api/impuestos/lcv/compras/6/2026/descargar?formato=csv');

        $respuesta->assertStatus(200);
        $this->assertStringContainsString(
            'text/csv',
            (string) $respuesta->headers->get('Content-Type'),
        );
    }

    public function test_mes_invalido_retorna_422(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/lcv/ventas/13/2026');

        $respuesta->assertStatus(422);
    }
}
