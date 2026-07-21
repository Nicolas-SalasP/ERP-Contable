<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Models\TasaPpmPropyme;
use App\Domains\Contabilidad\Services\Propyme\ResultadoTributarioPropymeService;
use App\Domains\Core\Models\Propietario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ResultadoPropymeTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected ResultadoTributarioPropymeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = new ResultadoTributarioPropymeService();

        // Tasa PPM Propyme rebajada 2026 (Ley 21.210)
        TasaPpmPropyme::create([
            'anio'                  => 2026,
            'tasa_base_pct'         => 0.125,
            'tasa_sobre_50000uf_pct' => 0.250,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function crearEmpresa14D8(array $extra = [])
    {
        return $this->crearEmpresa(array_merge(['regimen_tributario' => '14_D8'], $extra));
    }

    private function insertarDte(int $empresaId, int $tipoDte, int $folio, string $fechaEmision, float $montoNeto, string $estado = 'ACEPTADO'): void
    {
        DB::table('sii_dte_emitido')->insert([
            'empresa_id'          => $empresaId,
            'tipo_dte'            => $tipoDte,
            'folio'               => $folio,
            'fecha_emision'       => $fechaEmision,
            'estado'              => $estado,
            'monto_neto'          => $montoNeto,
            'monto_exento'        => 0,
            'monto_total'         => $montoNeto * 1.19,
            'emisor_rut'          => '76000001-K',
            'emisor_razon_social' => 'Empresa Test SA',
            'receptor_rut'        => '11111111-1',
            'receptor_razon_social' => 'Cliente Test',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    private function insertarFactura(int $empresaId, float $montoNeto, string $fechaEmision, int $folioSeq = 1): void
    {
        // Necesitamos un proveedor para la FK
        $proveedorId = DB::table('proveedores')->insertGetId([
            'empresa_id'     => $empresaId,
            'codigo_interno' => 'PROV-' . $folioSeq . '-' . $empresaId,
            'rut'            => '99' . $folioSeq . '00001-K',
            'razon_social'   => 'Proveedor Test ' . $folioSeq,
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
            'created_at'     => now(),
        ]);

        DB::table('facturas')->insert([
            'empresa_id'     => $empresaId,
            'codigo_unico'   => $folioSeq * 1000 + $empresaId,
            'proveedor_id'   => $proveedorId,
            'numero_factura' => 'F-' . $folioSeq,
            'fecha_emision'  => $fechaEmision,
            'monto_bruto'    => $montoNeto * 1.19,
            'monto_neto'     => $montoNeto,
            'monto_iva'      => $montoNeto * 0.19,
            'estado'         => 'REGISTRADA',
            'created_at'     => now(),
        ]);
    }

    private function crearPropietario(int $empresaId, string $rut, string $nombre, float $pct): Propietario
    {
        return Propietario::withoutGlobalScopes()->create([
            'empresa_id'               => $empresaId,
            'rut'                      => $rut,
            'nombre'                   => $nombre,
            'porcentaje_participacion' => $pct,
        ]);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function test_calcula_base_imponible_ingresos_menos_gastos(): void
    {
        $empresa = $this->crearEmpresa14D8();

        // Ingresos: factura 33 por $1.000.000 neto
        $this->insertarDte($empresa->id, 33, 1, '2026-03-15', 1_000_000.0);

        // Gastos: factura de compra por $400.000 neto
        $this->insertarFactura($empresa->id, 400_000.0, '2026-05-10', 1);

        $this->crearPropietario($empresa->id, '12345678-9', 'Socio Único', 100.0);

        $resultado = $this->service->calcular($empresa->id, 2026);

        $this->assertEquals(1_000_000, $resultado['ingresos_totales']);
        $this->assertEquals(400_000, $resultado['gastos_totales']);
        $this->assertEquals(600_000, $resultado['base_imponible']);
    }

    public function test_atribuye_base_a_propietarios_segun_participacion(): void
    {
        $empresa = $this->crearEmpresa14D8();

        $this->insertarDte($empresa->id, 33, 1, '2026-04-01', 1_000_000.0);
        $this->insertarFactura($empresa->id, 200_000.0, '2026-04-15', 1);

        // Dos propietarios: 60% y 40%
        $this->crearPropietario($empresa->id, '11111111-1', 'Socio A', 60.0);
        $this->crearPropietario($empresa->id, '22222222-2', 'Socio B', 40.0);

        $resultado = $this->service->calcular($empresa->id, 2026);

        $this->assertCount(2, $resultado['distribucion']);

        $socioA = collect($resultado['distribucion'])->firstWhere('rut', '11111111-1');
        $socioB = collect($resultado['distribucion'])->firstWhere('rut', '22222222-2');

        // Base = 800.000; 60% = 480.000; 40% = 320.000
        $this->assertEquals(480_000, $socioA['base_atribuida']);
        $this->assertEquals(320_000, $socioB['base_atribuida']);
    }

    public function test_lanza_error_si_participaciones_no_suman_100(): void
    {
        $empresa = $this->crearEmpresa14D8();

        $this->insertarDte($empresa->id, 33, 1, '2026-01-10', 500_000.0);

        // 60 + 30 = 90%, no suma 100
        $this->crearPropietario($empresa->id, '11111111-1', 'Socio A', 60.0);
        $this->crearPropietario($empresa->id, '22222222-2', 'Socio B', 30.0);

        $this->expectException(DjException::class);
        $this->expectExceptionMessageMatches('/100%/');

        $this->service->calcular($empresa->id, 2026);
    }

    public function test_lanza_error_si_empresa_no_es_14d8(): void
    {
        // Empresa en régimen 14_D3 (default)
        $empresa = $this->crearEmpresa(['regimen_tributario' => '14_D3']);

        $this->expectException(DjException::class);
        $this->expectExceptionMessageMatches('/14 D N°8/');

        $this->service->calcular($empresa->id, 2026);
    }

    public function test_usa_tasa_ppm_propyme_rebajada_2026(): void
    {
        $empresa = $this->crearEmpresa14D8();

        // 1.000.000 neto de ingresos; tasa rebajada 0.125% → PPM = 1.250
        $this->insertarDte($empresa->id, 33, 1, '2026-06-01', 1_000_000.0);

        $this->crearPropietario($empresa->id, '12345678-9', 'Socio Único', 100.0);

        $resultado = $this->service->calcular($empresa->id, 2026);

        $this->assertEquals(0.125, $resultado['tasa_ppm']);
        $this->assertEquals(1_250, $resultado['ppm_total']); // 1.000.000 * 0.00125 = 1.250
    }

    /**
     * Regresión (auditoría 2026-07-21): gastos_totales sumaba TODA la tabla facturas sin
     * filtrar tipo='COMPRA'. Una factura de VENTA (proveedor_id "espejo" compartido por RUT
     * con un Cliente, ver ProveedorAislamientoVentaCompraTest) inflaba silenciosamente los
     * gastos deducibles y reducía la base imponible tributaria.
     */
    public function test_gastos_totales_ignora_factura_de_venta(): void
    {
        $empresa = $this->crearEmpresa14D8();

        $this->insertarDte($empresa->id, 33, 1, '2026-03-15', 1_000_000.0);
        $this->insertarFactura($empresa->id, 400_000.0, '2026-05-10', 1);

        // Factura de VENTA colada en la misma tabla: no debe contarse como gasto.
        DB::table('facturas')->insert([
            'empresa_id'     => $empresa->id,
            'codigo_unico'   => 999000 + $empresa->id,
            'tipo'           => 'VENTA',
            'numero_factura' => 'FV-COLADA',
            'fecha_emision'  => '2026-05-11',
            'monto_bruto'    => 238_000,
            'monto_neto'     => 200_000,
            'monto_iva'      => 38_000,
            'estado'         => 'REGISTRADA',
            'created_at'     => now(),
        ]);

        $this->crearPropietario($empresa->id, '12345678-9', 'Socio Único', 100.0);

        $resultado = $this->service->calcular($empresa->id, 2026);

        $this->assertEquals(400_000, $resultado['gastos_totales']);
        $this->assertEquals(600_000, $resultado['base_imponible']);
    }
}
