<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use Laravel\Sanctum\Sanctum;

/**
 * Tests de integracion para el motor de sugerencias de conciliacion bancaria.
 */
class SugerenciasConciliacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $cuenta;
    protected $proveedor;
    protected int $movimientoId;

    protected string $fechaMovimiento = '2026-06-01';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->cuenta = CuentaBancariaEmpresa::create([
            'empresa_id'    => $this->empresa->id,
            'banco'         => 'Banco Test',
            'tipo_cuenta'   => 'CORRIENTE',
            'numero_cuenta' => '11223344',
            'titular'       => $this->empresa->razon_social,
            'rut_titular'   => $this->empresa->rut,
        ]);

        $this->proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'rut'            => '76.999.888-7',
            'razon_social'   => 'Proveedor Sugerencia Test',
            'codigo_interno' => 'PST-001',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->movimientoId = DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id'         => $this->empresa->id,
            'cuenta_bancaria_id' => $this->cuenta->id,
            'fecha'              => $this->fechaMovimiento,
            'hora'               => '10:00:00',
            'descripcion'        => 'Pago proveedor test',
            'cargo'              => 100000,
            'abono'              => 0,
            'estado'             => 'PENDIENTE',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function crearFactura(float $montoBruto, string $fechaEmision, string $numero = null): Factura
    {
        static $contador = 1;
        $montoNeto = round($montoBruto / 1.19, 2);
        $montoIva  = round($montoBruto - $montoNeto, 2);

        return Factura::create([
            'empresa_id'     => $this->empresa->id,
            'proveedor_id'   => $this->proveedor->id,
            'numero_factura' => $numero ?? ('SUG-' . $contador++),
            'tipo'           => 'COMPRA',
            'codigo_unico'   => 90000000 + $contador++,
            'fecha_emision'  => $fechaEmision,
            'monto_neto'     => $montoNeto,
            'monto_iva'      => $montoIva,
            'monto_bruto'    => $montoBruto,
            'estado'         => 'REGISTRADA',
        ]);
    }



    public function test_sugerencia_alta_monto_exacto_fecha_proxima(): void
    {
        $this->crearFactura(100000, '2026-06-03', 'ALTA-001');
        Sanctum::actingAs($this->usuario);
        $response = $this->getJson("/api/banco/movimientos/{$this->movimientoId}/sugerencias");
        $response->assertStatus(200)->assertJsonPath('success', true);
        $sugerencias = $response->json('data');
        $this->assertNotEmpty($sugerencias, 'Debe retornar al menos una sugerencia');
        $alta = collect($sugerencias)->firstWhere('score', 'alta');
        $this->assertNotNull($alta, 'Debe existir una sugerencia con score alta');
        $this->assertEquals(100000, $alta['monto_bruto']);
    }

    public function test_sugerencia_media_monto_exacto_fecha_lejana(): void
    {
        $this->crearFactura(100000, '2026-05-10', 'MED-001');
        Sanctum::actingAs($this->usuario);
        $response = $this->getJson("/api/banco/movimientos/{$this->movimientoId}/sugerencias");
        $response->assertStatus(200);
        $sugerencias = $response->json('data');
        $media = collect($sugerencias)->firstWhere('score', 'media');
        $this->assertNotNull($media, 'Debe existir una sugerencia con score media');
        $this->assertEquals(100000, $media['monto_bruto']);
        $this->assertGreaterThan(7, $media['diferencia_dias']);
    }

    public function test_sugerencia_baja_monto_aproximado(): void
    {
        $this->crearFactura(105000, '2026-06-04', 'BAJA-001');
        Sanctum::actingAs($this->usuario);
        $response = $this->getJson("/api/banco/movimientos/{$this->movimientoId}/sugerencias");
        $response->assertStatus(200);
        $sugerencias = $response->json('data');
        $baja = collect($sugerencias)->firstWhere('score', 'baja');
        $this->assertNotNull($baja, 'Debe existir una sugerencia con score baja');
        $this->assertEquals(105000, $baja['monto_bruto']);
    }

    public function test_sin_sugerencias_monto_sin_match(): void
    {
        $this->crearFactura(200000, '2026-06-01', 'NOMA-001');
        Sanctum::actingAs($this->usuario);
        $response = $this->getJson("/api/banco/movimientos/{$this->movimientoId}/sugerencias");
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'), 'No debe retornar sugerencias fuera de tolerancia');
    }

    public function test_requiere_permiso(): void
    {
        $response = $this->getJson("/api/banco/movimientos/{$this->movimientoId}/sugerencias");
        $response->assertStatus(401);
    }

    public function test_no_filtra_facturas_de_otra_empresa(): void
    {
        // Empresa B con factura que coincide exactamente en monto y fecha
        [$empresaB, $usuarioB] = $this->crearEmpresaConAdmin();
        $proveedorB = Proveedor::create([
            'empresa_id'     => $empresaB->id,
            'rut'            => '76.111.222-3',
            'razon_social'   => 'Proveedor Empresa B',
            'codigo_interno' => 'PEB-001',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
        $montoNeto = round(100000 / 1.19, 2);
        Factura::create([
            'empresa_id'     => $empresaB->id,
            'proveedor_id'   => $proveedorB->id,
            'numero_factura' => 'CROSS-001',
            'tipo'           => 'COMPRA',
            'codigo_unico'   => 99999999,
            'fecha_emision'  => $this->fechaMovimiento,
            'monto_neto'     => $montoNeto,
            'monto_iva'      => round(100000 - $montoNeto, 2),
            'monto_bruto'    => 100000,
            'estado'         => 'REGISTRADA',
        ]);

        // Empresa A no tiene facturas — sugerencias deben estar vacías
        Sanctum::actingAs($this->usuario);
        $response = $this->getJson("/api/banco/movimientos/{$this->movimientoId}/sugerencias");
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'), 'No debe exponer facturas de otra empresa');
    }

    public function test_descarta_monto_aproximado_fecha_lejana(): void
    {
        // Monto dentro del 10% pero fecha > 7 días → debe descartarse (no aparece)
        $this->crearFactura(105000, '2026-04-01', 'DESC-001');
        Sanctum::actingAs($this->usuario);
        $response = $this->getJson("/api/banco/movimientos/{$this->movimientoId}/sugerencias");
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'), 'Monto aproximado + fecha lejana debe descartarse');
    }
}