<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Rrhh\Models\Liquidacion;
use Carbon\Carbon;

class DashboardResumenTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Crea una factura de tipo VENTA asociada a la empresa indicada.
     * Usa un proveedor dummy que representa al cliente en el modelo actual.
     */
    private function crearFacturaVenta(
        int $empresaId,
        int $proveedorId,
        float $monto = 100000,
        string $fechaEmision = null,
        string $estado = 'REGISTRADA'
    ): Factura {
        static $seq = 0;
        $seq++;

        $f = new Factura();
        $f->empresa_id      = $empresaId;
        $f->proveedor_id    = $proveedorId;
        $f->numero_factura  = 'F-TEST-' . $seq . '-' . uniqid();
        $f->monto_bruto     = $monto;
        $f->monto_neto      = round($monto / 1.19, 2);
        $f->monto_iva       = round($monto - ($monto / 1.19), 2);
        $f->tipo            = 'VENTA';
        $f->estado          = $estado;
        $f->codigo_unico    = (int) (microtime(true) * 10000) + $seq;
        $f->fecha_emision   = $fechaEmision ?? Carbon::now()->toDateString();
        $f->save();

        return $f;
    }

    private function crearProveedor(int $empresaId, string $razonSocial = null): Proveedor
    {
        static $pSeq = 0;
        $pSeq++;
        return Proveedor::create([
            'empresa_id'     => $empresaId,
            'rut'            => $pSeq . '.111.' . $pSeq . '11-' . ($pSeq % 10),
            'razon_social'   => $razonSocial ?? 'Cliente Test ' . uniqid(),
            'codigo_interno' => 'C' . $pSeq,
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_resumen_devuelve_kpis_de_la_empresa(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)
            ->getJson('/api/dashboard/resumen');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'kpis' => [
                        'ventas_mes',
                        'ventas_mes_anterior',
                        'variacion_pct',
                        'facturas_emitidas_mes',
                        'facturas_pendientes',
                        'clientes_activos',
                        'cotizaciones_pendientes',
                    ],
                    'serie_ventas_12m',
                    'top_clientes',
                    'facturas_urgentes',
                ],
            ]);
    }

    public function test_serie_ventas_12m_agrupa_por_mes(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa->id);

        // Factura en el mes actual
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 50000, Carbon::now()->toDateString());
        // Factura en el mes anterior
        $mesAnterior = Carbon::now()->subMonth()->toDateString();
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 80000, $mesAnterior);

        $response = $this->actingAs($usuario)
            ->getJson('/api/dashboard/resumen?periodo=mes');

        $response->assertStatus(200);

        $serie = $response->json('data.serie_ventas_12m');
        $this->assertIsArray($serie);
        $this->assertCount(12, $serie, 'La serie debe tener exactamente 12 meses');

        // Verificar que el mes actual tiene el monto correcto
        $mesClave = Carbon::now()->format('Y-m');
        $mesActualEntry = collect($serie)->firstWhere('mes', $mesClave);
        $this->assertNotNull($mesActualEntry, "El mes $mesClave debe estar en la serie");
        $this->assertEquals(50000.0, $mesActualEntry['monto']);

        // Verificar que el mes anterior tiene el monto correcto
        $mesAntClave = Carbon::now()->subMonth()->format('Y-m');
        $mesAntEntry = collect($serie)->firstWhere('mes', $mesAntClave);
        $this->assertNotNull($mesAntEntry, "El mes $mesAntClave debe estar en la serie");
        $this->assertEquals(80000.0, $mesAntEntry['monto']);
    }

    public function test_solo_incluye_datos_de_la_empresa_del_usuario(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB, $usuarioB] = $this->crearEmpresaConAdmin();

        $proveedorA = $this->crearProveedor($empresaA->id, 'Cliente de A');
        $proveedorB = $this->crearProveedor($empresaB->id, 'Cliente de B');

        // Empresa A: 1 factura de $200.000
        $this->crearFacturaVenta($empresaA->id, $proveedorA->id, 200000);
        // Empresa B: 1 factura de $999.999
        $this->crearFacturaVenta($empresaB->id, $proveedorB->id, 999999);

        $responseA = $this->actingAs($usuarioA)
            ->getJson('/api/dashboard/resumen?periodo=mes');

        $responseA->assertStatus(200);

        $ventasA = $responseA->json('data.kpis.ventas_mes');
        $this->assertEquals(200000.0, (float) $ventasA, 'Usuario A solo debe ver sus propias ventas');

        // Usuario B debe ver solo sus ventas
        $responseB = $this->actingAs($usuarioB)
            ->getJson('/api/dashboard/resumen?periodo=mes');

        $responseB->assertStatus(200);
        $ventasB = $responseB->json('data.kpis.ventas_mes');
        $this->assertEquals(999999.0, (float) $ventasB, 'Usuario B solo debe ver sus propias ventas');
    }

    public function test_periodo_invalido_devuelve_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)
            ->getJson('/api/dashboard/resumen?periodo=invalido');

        $response->assertStatus(422);
    }

    public function test_incluye_compras_12m_en_respuesta(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa->id);

        // Crear una factura de tipo COMPRA en el mes actual
        $compra = new Factura();
        $compra->empresa_id     = $empresa->id;
        $compra->proveedor_id   = $proveedor->id;
        $compra->numero_factura = 'C-TEST-' . uniqid();
        $compra->monto_bruto    = 250000;
        $compra->monto_neto     = round(250000 / 1.19, 2);
        $compra->monto_iva      = round(250000 - (250000 / 1.19), 2);
        $compra->tipo           = 'COMPRA';
        $compra->estado         = 'REGISTRADA';
        $compra->codigo_unico   = (int) (microtime(true) * 10000) + 9999;
        $compra->fecha_emision  = Carbon::now()->toDateString();
        $compra->save();

        $response = $this->actingAs($usuario)
            ->getJson('/api/dashboard/resumen?periodo=mes');

        $response->assertStatus(200);

        $serie = $response->json('data.compras_12m');
        $this->assertIsArray($serie);
        $this->assertCount(12, $serie, 'La serie de compras debe tener exactamente 12 meses');

        $mesClave = Carbon::now()->format('Y-m');
        $mesActual = collect($serie)->firstWhere('mes', $mesClave);
        $this->assertNotNull($mesActual, "El mes {$mesClave} debe estar en la serie de compras");
        $this->assertEquals(250000.0, $mesActual['monto']);
    }

    public function test_rrhh_no_visible_sin_permiso_rrhh(): void
    {
        // El rol Usuario Básico no tiene permisos RRHH
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $usuarioSinRrhh = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        $response = $this->actingAs($usuarioSinRrhh)
            ->getJson('/api/dashboard/resumen');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayNotHasKey(
            'rrhh',
            $data,
            'La sección rrhh no debe aparecer si el usuario no tiene permiso rrhh.remuneraciones.ver'
        );
    }

    public function test_alerta_f29_visible_en_ventana_de_pago(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        // Simular que hoy es día 15 (dentro de la ventana de vencimiento F29: días 10-20)
        Carbon::setTestNow(Carbon::now()->startOfMonth()->addDays(14));

        try {
            $response = $this->actingAs($usuario)
                ->getJson('/api/dashboard/resumen');

            $response->assertStatus(200);

            $alertas = $response->json('data.alertas_pendientes');
            $this->assertIsArray($alertas);

            $alertaF29 = collect($alertas)->firstWhere('tipo', 'f29');
            $this->assertNotNull($alertaF29, 'Debe haber una alerta de tipo f29 en el día 15');
            $this->assertEquals('media', $alertaF29['urgencia']);
        } finally {
            Carbon::setTestNow(); // Restaurar la fecha real
        }
    }
}
