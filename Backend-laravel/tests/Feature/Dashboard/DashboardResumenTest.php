<?php

namespace Tests\Feature\Dashboard;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DashboardResumenTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

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
        ?string $fechaEmision = null,
        string $estado = 'REGISTRADA'
    ): Factura {
        static $seq = 0;
        $seq++;

        $f = new Factura;
        $f->empresa_id = $empresaId;
        $f->proveedor_id = $proveedorId;
        $f->numero_factura = 'F-TEST-'.$seq.'-'.uniqid();
        $f->monto_bruto = $monto;
        $f->monto_neto = round($monto / 1.19, 2);
        $f->monto_iva = round($monto - ($monto / 1.19), 2);
        $f->tipo = 'VENTA';
        $f->estado = $estado;
        $f->codigo_unico = (int) (microtime(true) * 10000) + $seq;
        $f->fecha_emision = $fechaEmision ?? Carbon::now()->toDateString();
        $f->save();

        return $f;
    }

    private function crearProveedor(int $empresaId, ?string $razonSocial = null): Proveedor
    {
        static $pSeq = 0;
        $pSeq++;

        return Proveedor::create([
            'empresa_id' => $empresaId,
            'rut' => $pSeq.'.111.'.$pSeq.'11-'.($pSeq % 10),
            'razon_social' => $razonSocial ?? 'Cliente Test '.uniqid(),
            'codigo_interno' => 'C'.$pSeq,
            'pais_iso' => 'CL',
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
                    'aging_ar',
                    'aging_ap',
                    'flujo_caja_30d',
                    'ordenes_compra_pendientes',
                    'distribucion_facturas',
                    'pipeline_cotizaciones',
                    'clientes_nuevos_6m',
                    'proximas_vencer_7d',
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
        $compra = new Factura;
        $compra->empresa_id = $empresa->id;
        $compra->proveedor_id = $proveedor->id;
        $compra->numero_factura = 'C-TEST-'.uniqid();
        $compra->monto_bruto = 250000;
        $compra->monto_neto = round(250000 / 1.19, 2);
        $compra->monto_iva = round(250000 - (250000 / 1.19), 2);
        $compra->tipo = 'COMPRA';
        $compra->estado = 'REGISTRADA';
        $compra->codigo_unico = (int) (microtime(true) * 10000) + 9999;
        $compra->fecha_emision = Carbon::now()->toDateString();
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

    public function test_aging_ar_agrupa_facturas_por_antiguedad(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa->id);

        // Factura reciente (< 30 días)
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 100000, Carbon::now()->subDays(10)->toDateString());
        // Factura media (31-60 días)
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 200000, Carbon::now()->subDays(45)->toDateString());
        // Factura antigua (>90 días)
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 300000, Carbon::now()->subDays(100)->toDateString());

        $response = $this->actingAs($usuario)->getJson('/api/dashboard/resumen');

        $response->assertOk();
        $aging = $response->json('data.aging_ar');
        $this->assertIsArray($aging);
        $this->assertCount(4, $aging);

        $tramo0_30 = collect($aging)->firstWhere('tramo', '0-30');
        $tramo31_60 = collect($aging)->firstWhere('tramo', '31-60');
        $tramo91plus = collect($aging)->firstWhere('tramo', '91+');

        $this->assertEquals(100000.0, $tramo0_30['monto']);
        $this->assertEquals(200000.0, $tramo31_60['monto']);
        $this->assertEquals(300000.0, $tramo91plus['monto']);
    }

    public function test_pipeline_cotizaciones_retorna_etapas_y_tasa(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)->getJson('/api/dashboard/resumen');

        $response->assertOk();
        $pipeline = $response->json('data.pipeline_cotizaciones');
        $this->assertIsArray($pipeline);
        $this->assertArrayHasKey('etapas', $pipeline);
        $this->assertArrayHasKey('tasa_conversion', $pipeline);
        $this->assertIsArray($pipeline['etapas']);
    }

    public function test_distribucion_facturas_tiene_cuatro_categorias(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)->getJson('/api/dashboard/resumen');

        $response->assertOk();
        $dist = $response->json('data.distribucion_facturas');
        $this->assertArrayHasKey('pagadas', $dist);
        $this->assertArrayHasKey('pendientes', $dist);
        $this->assertArrayHasKey('vencidas', $dist);
        $this->assertArrayHasKey('anuladas', $dist);
    }

    public function test_clientes_nuevos_6m_tiene_seis_meses(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)->getJson('/api/dashboard/resumen');

        $response->assertOk();
        $clientes = $response->json('data.clientes_nuevos_6m');
        $this->assertIsArray($clientes);
        $this->assertCount(6, $clientes);
    }

    /**
     * Caracterización: una segunda request al mismo dashboard (misma empresa,
     * periodo y permisos) debe usar cache y NO volver a ejecutar las ~30
     * queries del calculo (Cache::remember evita recalcular).
     */
    public function test_segunda_request_al_dashboard_usa_cache(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa->id);
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 100000);

        $primera = $this->actingAs($usuario)->getJson('/api/dashboard/resumen?periodo=mes');
        $primera->assertOk();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $segunda = $this->actingAs($usuario)->getJson('/api/dashboard/resumen?periodo=mes');
        $segunda->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // La segunda respuesta debe ser identica (viene de cache) y no debe
        // haber disparado las decenas de queries del calculo del dashboard.
        $this->assertEquals($primera->json('data'), $segunda->json('data'));
        $this->assertLessThan(
            10,
            count($queries),
            'La segunda request no deberia recalcular el dashboard (debe venir de cache).'
        );
    }

    /** El cache es por empresa: dos empresas distintas nunca deben compartir resultado. */
    public function test_cache_del_dashboard_no_mezcla_empresas(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB, $usuarioB] = $this->crearEmpresaConAdmin();

        $proveedorA = $this->crearProveedor($empresaA->id, 'Cliente de A');
        $proveedorB = $this->crearProveedor($empresaB->id, 'Cliente de B');

        $this->crearFacturaVenta($empresaA->id, $proveedorA->id, 111000);
        $this->crearFacturaVenta($empresaB->id, $proveedorB->id, 222000);

        $respA = $this->actingAs($usuarioA)->getJson('/api/dashboard/resumen?periodo=mes');
        $respB = $this->actingAs($usuarioB)->getJson('/api/dashboard/resumen?periodo=mes');

        $respA->assertOk();
        $respB->assertOk();

        $this->assertEquals(111000.0, (float) $respA->json('data.kpis.ventas_mes'));
        $this->assertEquals(222000.0, (float) $respB->json('data.kpis.ventas_mes'));
    }

    // -------------------------------------------------------------------------
    // Rango de fechas custom + comparación interanual
    // -------------------------------------------------------------------------

    public function test_fecha_desde_y_fecha_hasta_explicitas_tienen_prioridad_sobre_periodo(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa->id);

        // Factura dentro del rango explícito pero fuera del mes actual (periodo preset)
        $fechaEnRango = Carbon::now()->subMonths(2)->toDateString();
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 150000, $fechaEnRango);
        // Factura en el mes actual, que quedaría fuera del rango explícito
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 999000, Carbon::now()->toDateString());

        $desde = Carbon::now()->subMonths(3)->startOfMonth()->toDateString();
        $hasta = Carbon::now()->subMonths(2)->endOfMonth()->toDateString();

        $response = $this->actingAs($usuario)->getJson(
            "/api/dashboard/resumen?periodo=mes&fecha_desde={$desde}&fecha_hasta={$hasta}"
        );

        $response->assertOk();
        $this->assertEquals(150000.0, (float) $response->json('data.kpis.ventas_mes'));
    }

    public function test_fecha_hasta_menor_a_fecha_desde_devuelve_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)->getJson(
            '/api/dashboard/resumen?fecha_desde=2026-06-01&fecha_hasta=2026-05-01'
        );

        $response->assertStatus(422);
    }

    public function test_rango_de_fechas_mayor_a_366_dias_devuelve_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)->getJson(
            '/api/dashboard/resumen?fecha_desde=2020-01-01&fecha_hasta=2026-01-01'
        );

        $response->assertStatus(422);
    }

    public function test_solo_fecha_desde_sin_fecha_hasta_devuelve_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)->getJson(
            '/api/dashboard/resumen?fecha_desde=2026-01-01'
        );

        $response->assertStatus(422);
    }

    public function test_comparar_anio_anterior_agrega_clave_con_kpis_y_serie(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa->id);

        // Ventas del mes actual
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 200000, Carbon::now()->toDateString());
        // Ventas del mismo mes, un año atrás
        $this->crearFacturaVenta($empresa->id, $proveedor->id, 100000, Carbon::now()->subYear()->toDateString());

        $response = $this->actingAs($usuario)->getJson(
            '/api/dashboard/resumen?periodo=mes&comparar_anio_anterior=1'
        );

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'comparacion_anio_anterior' => [
                    'ventas',
                    'variacion_pct',
                    'facturas_emitidas',
                    'facturas_emitidas_variacion_pct',
                    'serie',
                    'periodo' => ['desde', 'hasta'],
                ],
            ],
        ]);

        $this->assertEquals(200000.0, (float) $response->json('data.kpis.ventas_mes'));
        $this->assertEquals(100000.0, (float) $response->json('data.comparacion_anio_anterior.ventas'));
        $this->assertEquals(100.0, (float) $response->json('data.comparacion_anio_anterior.variacion_pct'));
    }

    public function test_sin_comparar_anio_anterior_no_incluye_la_clave(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)->getJson('/api/dashboard/resumen?periodo=mes');

        $response->assertOk();
        $this->assertArrayNotHasKey('comparacion_anio_anterior', $response->json('data'));
    }

    /**
     * Regresion: topClientes() seleccionaba proveedores.id sin incluirlo en el
     * groupBy. Bajo SQLite (motor de tests por defecto) no falla, pero bajo MySQL
     * con ONLY_FULL_GROUP_BY (modo por defecto) lanza SQLSTATE 42000/1055 y el
     * dashboard completo devolvia 500 en produccion. Este test necesita >1 factura
     * para el mismo cliente, que es lo que fuerza la agregacion real.
     */
    public function test_top_clientes_agrupa_multiples_facturas_del_mismo_cliente(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $cliente = $this->crearProveedor($empresa->id, 'Cliente Top');

        $this->crearFacturaVenta($empresa->id, $cliente->id, 100000);
        $this->crearFacturaVenta($empresa->id, $cliente->id, 50000);

        $response = $this->actingAs($usuario)->getJson('/api/dashboard/resumen');

        $response->assertOk();
        $topClientes = $response->json('data.top_clientes');

        $this->assertCount(1, $topClientes);
        $this->assertEquals('Cliente Top', $topClientes[0]['nombre']);
        $this->assertEquals(150000.0, (float) $topClientes[0]['monto']);
    }
}
