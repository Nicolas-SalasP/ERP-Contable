<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\PlanCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Verifica que simularF29 retorna las líneas del Formulario 29 del SII
 * correctamente mapeadas desde los datos de ventas, compras, PPM y retenciones.
 */
class F29LineasFormularioTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'rut'            => '76123456-7',
            'razon_social'   => 'Proveedor Líneas F29',
            'codigo_interno' => 'PLF29',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        // Cuentas contables requeridas para el ciclo F29
        $this->crearCuenta('152540', 'IVA Crédito Fiscal', 'ACTIVO');
        $this->crearCuenta('353360', 'IVA Débito Fiscal', 'PASIVO');
        $this->crearCuenta('152542', 'Remanente IVA F29', 'ACTIVO');
        $this->crearCuenta('152541', 'PPM por Recuperar', 'ACTIVO');
        $this->crearCuenta('353365', 'Impuesto a Pagar F29', 'PASIVO');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function crearCuenta(string $codigo, string $nombre, string $tipo): void
    {
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo'     => $codigo,
            'nombre'     => $nombre,
            'tipo'       => $tipo,
            'imputable'  => true,
            'activo'     => true,
        ]);
    }

    private function insertarDteVenta(int $mes, int $anio, float $montoNeto, float $iva, int $folio): void
    {
        DB::table('sii_dte_emitido')->insert([
            'empresa_id'            => $this->empresa->id,
            'tipo_dte'              => 33,
            'folio'                 => $folio,
            'fecha_emision'         => "$anio-" . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "-15",
            'estado'                => 'ACEPTADO',
            'monto_neto'            => $montoNeto,
            'monto_exento'          => 0,
            'iva'                   => $iva,
            'monto_total'           => $montoNeto + $iva,
            'emisor_rut'            => '76000001-K',
            'emisor_razon_social'   => 'Empresa Test SA',
            'receptor_rut'          => '11111111-1',
            'receptor_razon_social' => 'Cliente Test',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    private function crearCompra(int $mes, int $anio, float $montoNeto, float $montoIva, int $num = 1): void
    {
        Factura::create([
            'empresa_id'            => $this->empresa->id,
            'proveedor_id'          => $this->proveedor->id,
            'numero_factura'        => "FC-LIN-{$mes}{$anio}-{$num}",
            'tipo'                  => 'COMPRA',
            'codigo_unico'          => Factura::generarCodigoUnico(),
            'fecha_emision'         => "$anio-" . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "-10",
            'monto_neto'            => $montoNeto,
            'monto_iva'             => $montoIva,
            'monto_bruto'           => $montoNeto + $montoIva,
            'estado'                => 'REGISTRADA',
            'es_documento_exterior' => false,
        ]);
    }

    /**
     * Llama al endpoint de simulación y devuelve el objeto interno (bajo la clave 'data').
     */
    private function simular(int $mes = 3, int $anio = 2026): array
    {
        $res = $this->actingAs($this->usuario)
            ->getJson("/api/impuestos/cierre-f29/simular/{$mes}/{$anio}");

        $res->assertStatus(200);
        $body = $res->json();
        // La respuesta envuelve el resultado en ['success' => true, 'data' => [...]]
        return $body['data'] ?? $body;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_lineas_f29_presentes_en_respuesta_simulacion(): void
    {
        $body = $this->simular();

        $this->assertArrayHasKey('lineas_f29', $body, 'La respuesta debe incluir la clave lineas_f29');

        $lineas = $body['lineas_f29'];
        foreach (['L1', 'L7', 'L11', 'L20', 'L24', 'L26', 'L27', 'L28', 'L36', 'L63', 'L64', 'L65', 'L49', 'L89', 'L91'] as $clave) {
            $this->assertArrayHasKey($clave, $lineas, "Falta la línea {$clave} en lineas_f29");
            $this->assertArrayHasKey('desc', $lineas[$clave], "La línea {$clave} debe tener 'desc'");
            $this->assertArrayHasKey('valor', $lineas[$clave], "La línea {$clave} debe tener 'valor'");
        }
    }

    public function test_linea_1_es_neto_ventas(): void
    {
        $this->insertarDteVenta(3, 2026, 1_000_000.0, 190_000.0, 1);

        $body = $this->simular();

        $this->assertEquals(
            1_000_000,
            $body['lineas_f29']['L1']['valor'],
            'L1 debe ser el neto de ventas del período'
        );
    }

    public function test_linea_7_es_iva_debito(): void
    {
        $this->insertarDteVenta(3, 2026, 1_000_000.0, 190_000.0, 2);

        $body = $this->simular();

        $this->assertEquals(
            190_000,
            $body['lineas_f29']['L7']['valor'],
            'L7 debe ser el IVA débito (19% del neto ventas)'
        );
    }

    public function test_linea_20_es_neto_compras(): void
    {
        $this->crearCompra(3, 2026, 500_000.0, 95_000.0);

        $body = $this->simular();

        $this->assertEquals(
            500_000,
            $body['lineas_f29']['L20']['valor'],
            'L20 debe ser el neto de compras del período'
        );
    }

    public function test_linea_28_es_diferencia_cuando_debito_mayor(): void
    {
        // IVA débito 190.000, IVA crédito 19.000 → diferencia = 171.000
        $this->insertarDteVenta(3, 2026, 1_000_000.0, 190_000.0, 3);
        $this->crearCompra(3, 2026, 100_000.0, 19_000.0);

        $body = $this->simular();

        $this->assertGreaterThan(0, $body['lineas_f29']['L28']['valor'], 'L28 debe ser > 0 cuando el débito supera al crédito');
        $this->assertEquals(0, $body['lineas_f29']['L36']['valor'], 'L36 debe ser 0 cuando hay IVA determinado positivo');
        $this->assertEquals(171_000, $body['lineas_f29']['L28']['valor'], 'L28 = IVA débito - IVA crédito = 171.000');
    }

    public function test_linea_36_es_remanente_cuando_credito_mayor(): void
    {
        // Sin ventas, compra con IVA 95.000 → crédito > débito → remanente
        $this->crearCompra(3, 2026, 500_000.0, 95_000.0);

        $body = $this->simular();

        $this->assertEquals(0, $body['lineas_f29']['L28']['valor'], 'L28 debe ser 0 cuando el crédito supera al débito');
        $this->assertGreaterThan(0, $body['lineas_f29']['L36']['valor'], 'L36 debe ser > 0 cuando el crédito supera al débito');
        $this->assertEquals(95_000, $body['lineas_f29']['L36']['valor'], 'L36 debe ser igual al exceso de crédito sobre débito');
    }

    public function test_linea_89_igual_a_total_a_pagar(): void
    {
        $this->insertarDteVenta(3, 2026, 1_000_000.0, 190_000.0, 4);
        $this->crearCompra(3, 2026, 100_000.0, 19_000.0);

        $body = $this->simular();

        $this->assertEquals(
            $body['resumen']['total_a_pagar'],
            $body['lineas_f29']['L89']['valor'],
            'L89 debe ser igual a resumen.total_a_pagar'
        );
        $this->assertEquals(
            $body['lineas_f29']['L89']['valor'],
            $body['lineas_f29']['L91']['valor'],
            'L89 y L91 deben ser iguales'
        );
    }

    public function test_linea_27_es_suma_de_L24_y_L26(): void
    {
        $this->crearCompra(3, 2026, 100_000.0, 19_000.0);

        $body = $this->simular();

        $lineas = $body['lineas_f29'];
        $this->assertEquals(
            $lineas['L24']['valor'] + $lineas['L26']['valor'],
            $lineas['L27']['valor'],
            'L27 debe ser exactamente L24 + L26'
        );
    }

    public function test_L28_y_L36_son_mutuamente_excluyentes(): void
    {
        $this->insertarDteVenta(3, 2026, 1_000_000.0, 190_000.0, 5);
        $this->crearCompra(3, 2026, 100_000.0, 19_000.0);

        $body = $this->simular();
        $lineas = $body['lineas_f29'];

        $this->assertFalse(
            $lineas['L28']['valor'] > 0 && $lineas['L36']['valor'] > 0,
            'L28 y L36 no pueden ser ambos > 0 simultáneamente'
        );
    }
}
