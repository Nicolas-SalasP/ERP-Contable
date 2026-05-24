<?php

namespace Tests\Feature\Sii\Mapping;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\FacturaIncompletaParaSii;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Mapping\FacturaAComercialDteMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class FacturaAComercialDteMapperTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private FacturaAComercialDteMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->mapper = app(FacturaAComercialDteMapper::class);
    }

    // -------- helpers --------

    private function crearEmpresaConSii(array $overrides = []): Empresa
    {
        return Empresa::create(array_merge([
            'rut'                  => '76123456-7',
            'razon_social'         => 'EMPRESA EMISORA',
            'giro_emisor'          => 'Servicios profesionales',
            'codigo_actividad_sii' => 471910,
            'direccion'            => 'Av Siempre Viva 742',
            'comuna'               => 'Santiago',
            'ciudad'               => 'Santiago',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'         => 'certificacion',
        ], $overrides));
    }

    private function crearClienteCompleto(int $empresaId, array $overrides = []): Cliente
    {
        return Cliente::create(array_merge([
            'rut'             => '11222333-4',
            'razon_social'    => 'CLIENTE FINAL S.A.',
            'contacto_nombre' => 'Juan Perez',
            'contacto_email'  => 'juan@cliente.cl',
            'direccion'       => 'Calle Cliente 100',
            'telefono'        => '+56222334455',
            'email'           => 'general@cliente.cl',
            'estado'          => 'ACTIVO',
            'empresa_id'      => $empresaId,
            'comuna'          => 'Providencia',
            'ciudad'          => 'Santiago',
            'giro'            => 'Comercio al por menor',
            'codigo_actividad' => 471910,
        ], $overrides));
    }

    /**
     * Crea una factura VENTA + 1 detalle, montos cuadrados, lista para mapeo.
     * @param array $overridesFactura  para campos de la factura
     * @param array $overridesDetalle  para campos del detalle unico
     */
    private function crearFacturaVentaCompleta(
        Empresa $empresa,
        Cliente $cliente,
        array $overridesFactura = [],
        array $overridesDetalle = []
    ): Factura {
        // Proveedor placeholder (proveedor_id es nullable post F1.x SII).
        $factura = Factura::create(array_merge([
            'empresa_id'          => $empresa->id,
            'codigo_unico'        => Factura::generarCodigoUnico(),
            'proveedor_id'        => null,
            'cliente_id'          => $cliente->id,
            'numero_factura'      => 'F-' . random_int(1000, 99999),
            'tipo'                => 'VENTA',
            'tipo_documento'      => 'FACTURA',
            'tipo_dte'            => 33,
            'fecha_emision'       => now()->toDateString(),
            'monto_neto'          => 1000,
            'monto_iva'           => 190,
            'monto_bruto'         => 1190,
            'monto_exento'        => 0,
            'estado'              => 'REGISTRADA',
            'moneda'              => 'CLP',
        ], $overridesFactura));

        FacturaDetalle::create(array_merge([
            'factura_id'      => $factura->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Servicio profesional',
            'descripcion'     => 'Asesoria mes',
            'cantidad'        => 1,
            'unidad_medida'   => 'UN',
            'precio_unitario' => 1000,
            'descuento_pct'   => 0,
            'descuento_monto' => 0,
            'recargo_pct'     => 0,
            'recargo_monto'   => 0,
            'exento'          => false,
            'monto_item'      => 1000,
        ], $overridesDetalle));

        return $factura->fresh(['detalles', 'cliente', 'empresa']);
    }

    // ============================================================
    // GRUPO 1 — Mapeo exitoso por tipo DTE
    // ============================================================

    public function test_mapea_factura_tipo_33_completa_con_todos_los_campos(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);

        $this->assertSame(SiiDteEmitido::ESTADO_BORRADOR, $dte->estado);
        $this->assertSame(33, (int) $dte->tipo_dte);
        $this->assertSame($factura->id, (int) $dte->factura_id);
        $this->assertSame('76123456-7', $dte->emisor_rut);
        $this->assertSame('11222333-4', $dte->receptor_rut);
        $this->assertSame('CLIENTE FINAL S.A.', $dte->receptor_razon_social);
        $this->assertSame(1000.0, (float) $dte->monto_neto);
        $this->assertSame(190.0, (float) $dte->iva);
        $this->assertSame(1190.0, (float) $dte->monto_total);
        $this->assertCount(1, $dte->detalles);
    }

    public function test_mapea_factura_tipo_34_exenta_sin_iva(): void
    {
        $empresa = $this->crearEmpresaConSii(['rut' => '76777888-K']);
        $cliente = $this->crearClienteCompleto($empresa->id, ['rut' => '11111111-1']);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_dte'    => 34,
            'monto_neto'  => 0,
            'monto_iva'   => 0,
            'monto_exento' => 5000,
            'monto_bruto' => 5000,
        ], [
            'exento'     => true,
            'monto_item' => 5000,
            'precio_unitario' => 5000,
        ]);

        $dte = $this->mapper->mapear($factura);

        $this->assertSame(34, (int) $dte->tipo_dte);
        $this->assertSame(0.0, (float) $dte->monto_neto);
        $this->assertSame(0.0, (float) $dte->iva);
        $this->assertSame(5000.0, (float) $dte->monto_exento);
        $this->assertSame(5000.0, (float) $dte->monto_total);
    }

    public function test_mapea_factura_tipo_39_boleta_afecta(): void
    {
        $empresa = $this->crearEmpresaConSii(['rut' => '76333222-1']);
        $cliente = $this->crearClienteCompleto($empresa->id, ['rut' => '12345678-9']);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_dte'       => 39,
            'tipo_documento' => 'BOLETA',
        ]);

        $dte = $this->mapper->mapear($factura);
        $this->assertSame(39, (int) $dte->tipo_dte);
    }

    public function test_mapea_factura_tipo_41_boleta_exenta(): void
    {
        $empresa = $this->crearEmpresaConSii(['rut' => '76333000-K']);
        $cliente = $this->crearClienteCompleto($empresa->id, ['rut' => '22222222-2']);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_dte'       => 41,
            'tipo_documento' => 'BOLETA',
            'monto_neto'  => 0,
            'monto_iva'   => 0,
            'monto_exento' => 3000,
            'monto_bruto' => 3000,
        ], [
            'exento'     => true,
            'monto_item' => 3000,
            'precio_unitario' => 3000,
        ]);

        $dte = $this->mapper->mapear($factura);
        $this->assertSame(41, (int) $dte->tipo_dte);
        $this->assertSame(3000.0, (float) $dte->monto_exento);
    }

    public function test_mapea_factura_tipo_61_nota_credito_requiere_referencia(): void
    {
        $empresa = $this->crearEmpresaConSii(['rut' => '76998877-K']);
        $cliente = $this->crearClienteCompleto($empresa->id, ['rut' => '33333333-3']);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_dte'       => 61,
            'tipo_documento' => 'NOTA_CREDITO',
        ]);

        $referencias = [
            ['tipo_doc' => 33, 'folio_ref' => '1234', 'fecha_ref' => '2026-01-15', 'cod_ref' => 1, 'razon_ref' => 'Anulacion'],
        ];
        $dte = $this->mapper->mapear($factura, $referencias);

        $this->assertSame(61, (int) $dte->tipo_dte);
        $this->assertCount(1, $dte->referencias);
        $this->assertSame('1234', $dte->referencias->first()->folio_referencia);
        $this->assertSame('33', $dte->referencias->first()->tipo_documento_referencia);
    }

    public function test_mapea_factura_tipo_56_nota_debito_requiere_referencia(): void
    {
        $empresa = $this->crearEmpresaConSii(['rut' => '76888777-1']);
        $cliente = $this->crearClienteCompleto($empresa->id, ['rut' => '44444444-4']);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_dte'       => 56,
            'tipo_documento' => 'NOTA_DEBITO',
        ]);

        $referencias = [
            ['tipo_doc' => 33, 'folio_ref' => '500', 'fecha_ref' => '2026-02-01'],
        ];
        $dte = $this->mapper->mapear($factura, $referencias);

        $this->assertSame(56, (int) $dte->tipo_dte);
        $this->assertCount(1, $dte->referencias);
    }

    // ============================================================
    // GRUPO 2 — Mapeo de campos específicos
    // ============================================================

    public function test_emisor_se_snapshotea_desde_empresa_no_se_referencia(): void
    {
        $empresa = $this->crearEmpresaConSii(['razon_social' => 'EMPRESA ORIGINAL']);
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);

        // Cambiamos la empresa: el DTE NO debe cambiar.
        $empresa->update(['razon_social' => 'EMPRESA CAMBIADA']);
        $this->assertSame('EMPRESA ORIGINAL', $dte->fresh()->emisor_razon_social);
    }

    public function test_receptor_se_snapshotea_desde_cliente_no_se_referencia(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id, ['razon_social' => 'CLIENTE ORIGINAL']);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);

        $cliente->update(['razon_social' => 'CLIENTE CAMBIADO']);
        $this->assertSame('CLIENTE ORIGINAL', $dte->fresh()->receptor_razon_social);
    }

    public function test_razon_social_cliente_de_255_chars_se_trunca_a_100(): void
    {
        $largo = str_repeat('A', 255);
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id, ['razon_social' => $largo]);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);

        $this->assertLessThanOrEqual(100, mb_strlen($dte->receptor_razon_social));
    }

    public function test_correo_recep_prioriza_contacto_email_sobre_email(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id, [
            'contacto_email' => 'preferred@x.cl',
            'email'          => 'fallback@x.cl',
        ]);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);
        $this->assertSame('preferred@x.cl', $dte->receptor_correo);
    }

    public function test_correo_recep_es_null_si_cliente_no_tiene_ningun_correo(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id, [
            'contacto_email' => null,
            'email'          => null,
        ]);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);
        $this->assertNull($dte->receptor_correo);
    }

    public function test_cliente_sin_giro_deja_receptor_giro_null(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id, ['giro' => null]);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);
        $this->assertNull($dte->receptor_giro);
    }

    public function test_tasa_iva_es_19_hardcoded_independiente_del_calculo(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);
        $this->assertSame('19.00', (string) $dte->tasa_iva);
    }

    public function test_descuento_global_monto_se_persiste_en_encabezado(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        // Para que cuadre: monto_item del detalle debe ser monto_neto + descuento_global_monto
        // pero el validador de cuadratura suma monto_item == monto_neto.
        // En F6.1 el descuento global se persiste pero NO se reduce de monto_neto en el DTE
        // (eso lo hace el constructor XML en F4.1 con <DscRcgGlobal>).
        // Para este test usamos monto_neto = 1000 (sin descuento aplicado en el header).
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'descuento_global_monto' => 50,
        ]);

        $dte = $this->mapper->mapear($factura);
        $this->assertSame(50.0, (float) $dte->descuento_global_monto);
    }

    // ============================================================
    // GRUPO 3 — Mapeo de detalles
    // ============================================================

    public function test_detalles_se_persisten_ordenados_por_numero_linea(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        // Factura con monto_neto=3000 (3 lineas de 1000)
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'monto_neto' => 3000,
            'monto_iva'  => 570,
            'monto_bruto' => 3570,
        ], [
            'numero_linea' => 3,
            'nombre_item'  => 'Item C',
        ]);
        // Agregar 2 detalles mas con numeros 1 y 2 (orden inverso a creacion).
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'Item A', 'cantidad' => 1, 'precio_unitario' => 1000, 'monto_item' => 1000, 'exento' => false,
        ]);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 2,
            'nombre_item' => 'Item B', 'cantidad' => 1, 'precio_unitario' => 1000, 'monto_item' => 1000, 'exento' => false,
        ]);

        $dte = $this->mapper->mapear($factura->fresh(['detalles']));

        $detalles = $dte->detalles->sortBy('numero_linea')->values();
        $this->assertCount(3, $detalles);
        $this->assertSame('Item A', $detalles[0]->nombre_item);
        $this->assertSame('Item B', $detalles[1]->nombre_item);
        $this->assertSame('Item C', $detalles[2]->nombre_item);
    }

    public function test_detalle_exento_marca_exento_true(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_dte'   => 34,
            'monto_neto' => 0, 'monto_iva' => 0, 'monto_exento' => 1000, 'monto_bruto' => 1000,
        ], ['exento' => true]);

        $dte = $this->mapper->mapear($factura);
        $this->assertTrue((bool) $dte->detalles->first()->exento);
    }

    public function test_detalle_con_codigo_item_persiste_tipo_codigo_y_codigo(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [], [
            'codigo_item' => 'SKU-123',
            'tipo_codigo' => 'INT1',
        ]);

        $dte = $this->mapper->mapear($factura);
        $det = $dte->detalles->first();
        $this->assertSame('SKU-123', $det->codigo_item);
        $this->assertSame('INT1', $det->tipo_codigo);
    }

    public function test_detalle_sin_codigo_omite_campos_de_codigo(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [], [
            'codigo_item' => null,
            'tipo_codigo' => null,
        ]);

        $dte = $this->mapper->mapear($factura);
        $det = $dte->detalles->first();
        $this->assertNull($det->codigo_item);
        $this->assertNull($det->tipo_codigo);
    }

    public function test_detalle_con_descuento_pct_y_monto_persiste_ambos(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [], [
            'descuento_pct'   => 10,
            'descuento_monto' => 100,
        ]);

        $dte = $this->mapper->mapear($factura);
        $det = $dte->detalles->first();
        $this->assertSame(10.0, (float) $det->descuento_pct);
        $this->assertSame(100.0, (float) $det->descuento_monto);
    }

    // ============================================================
    // GRUPO 4 — Validaciones que lanzan FacturaIncompletaParaSii
    // ============================================================

    public function test_lanza_si_tipo_dte_es_null(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, ['tipo_dte' => null]);

        try {
            $this->mapper->mapear($factura);
            $this->fail('Debio lanzar tipoDteFaltante');
        } catch (FacturaIncompletaParaSii $e) {
            $this->assertSame(FacturaIncompletaParaSii::TIPO_DTE_FALTANTE, $e->razon);
            $this->assertSame($factura->id, $e->facturaId);
        }
    }

    public function test_lanza_si_tipo_dte_es_99_invalido(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, ['tipo_dte' => 99]);

        try {
            $this->mapper->mapear($factura);
            $this->fail('Debio lanzar tipoDteInvalido');
        } catch (FacturaIncompletaParaSii $e) {
            $this->assertSame(FacturaIncompletaParaSii::TIPO_DTE_INVALIDO, $e->razon);
        }
    }

    public function test_lanza_si_cliente_id_es_null(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, ['cliente_id' => null]);

        $this->expectException(FacturaIncompletaParaSii::class);
        $this->mapper->mapear($factura);
    }

    public function test_lanza_si_estado_es_ANULADA(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, ['estado' => 'ANULADA']);

        try {
            $this->mapper->mapear($factura);
            $this->fail('Debio lanzar estadoInvalido');
        } catch (FacturaIncompletaParaSii $e) {
            $this->assertSame(FacturaIncompletaParaSii::ESTADO_INVALIDO, $e->razon);
        }
    }

    public function test_lanza_si_sii_dte_emitido_id_ya_seteado_yaEmitida(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        // Primer mapeo OK
        $dte1 = $this->mapper->mapear($factura);
        $this->assertNotNull($dte1->id);

        // Segundo mapeo lanza yaEmitida (factura ya tiene sii_dte_emitido_id)
        try {
            $this->mapper->mapear($factura->fresh());
            $this->fail('Debio lanzar yaEmitida');
        } catch (FacturaIncompletaParaSii $e) {
            $this->assertSame(FacturaIncompletaParaSii::YA_EMITIDA, $e->razon);
            $this->assertSame($dte1->id, $e->contexto['dte_emitido_id']);
        }
    }

    public function test_lanza_si_detalles_vacios(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);
        $factura->detalles()->delete();

        try {
            $this->mapper->mapear($factura->fresh(['detalles']));
            $this->fail('Debio lanzar sinDetalles');
        } catch (FacturaIncompletaParaSii $e) {
            $this->assertSame(FacturaIncompletaParaSii::SIN_DETALLES, $e->razon);
        }
    }

    public function test_lanza_si_tipo_documento_FACTURA_con_tipo_dte_61_inconsistente(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_documento' => 'FACTURA',
            'tipo_dte'       => 61,
        ]);

        try {
            $this->mapper->mapear($factura, [['tipo_doc' => 33, 'folio_ref' => '1', 'fecha_ref' => '2026-01-01']]);
            $this->fail('Debio lanzar tipoDocumentoInconsistente');
        } catch (FacturaIncompletaParaSii $e) {
            $this->assertSame(FacturaIncompletaParaSii::TIPO_DOCUMENTO_INCONSISTENTE, $e->razon);
        }
    }

    public function test_lanza_si_tipo_documento_NOTA_CREDITO_con_tipo_dte_33_inconsistente(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_documento' => 'NOTA_CREDITO',
            'tipo_dte'       => 33,
        ]);

        $this->expectException(FacturaIncompletaParaSii::class);
        $this->mapper->mapear($factura);
    }

    public function test_lanza_si_tipo_61_sin_referencias(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_dte' => 61, 'tipo_documento' => 'NOTA_CREDITO',
        ]);

        try {
            $this->mapper->mapear($factura, referencias: []);
            $this->fail('Debio lanzar referenciasFaltantes');
        } catch (FacturaIncompletaParaSii $e) {
            $this->assertSame(FacturaIncompletaParaSii::REFERENCIAS_FALTANTES, $e->razon);
        }
    }

    public function test_lanza_si_tipo_56_sin_referencias(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_dte' => 56, 'tipo_documento' => 'NOTA_DEBITO',
        ]);

        $this->expectException(FacturaIncompletaParaSii::class);
        $this->mapper->mapear($factura);
    }

    public function test_lanza_si_montos_no_cuadran_al_centavo(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        // monto_neto declarado 1000 pero detalle suma 800: discrepancia 200.
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'monto_neto'  => 1000,
            'monto_iva'   => 190,
            'monto_bruto' => 1190,
        ], [
            'monto_item'      => 800,
            'precio_unitario' => 800,
        ]);

        try {
            $this->mapper->mapear($factura);
            $this->fail('Debio lanzar montosNoCuadran');
        } catch (FacturaIncompletaParaSii $e) {
            $this->assertSame(FacturaIncompletaParaSii::MONTOS_NO_CUADRAN, $e->razon);
        }
    }

    // ============================================================
    // GRUPO 5 — Atomicidad y snapshot inmutable
    // ============================================================

    public function test_lanza_no_persiste_dte_si_validacion_falla(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, ['tipo_dte' => null]);

        $cuentaPre = SiiDteEmitido::count();
        try {
            $this->mapper->mapear($factura);
        } catch (FacturaIncompletaParaSii) {
        }

        $this->assertSame($cuentaPre, SiiDteEmitido::count(), 'Validacion fallida NO debe haber persistido DTE.');
    }

    public function test_lock_for_update_previene_doble_emision(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        // Simulamos la condicion: primer mapeo exitoso commitea, segundo ve
        // sii_dte_emitido_id != null y lanza yaEmitida. Es la version
        // sincronica (SQLite no soporta verdadero locking concurrente, pero
        // si la idempotencia logica del check).
        $this->mapper->mapear($factura);

        $this->expectException(FacturaIncompletaParaSii::class);
        $this->mapper->mapear($factura->fresh());
    }

    public function test_aislamiento_multitenant_factura_de_otra_empresa_se_mapea_a_su_propio_dte(): void
    {
        $empresaA = $this->crearEmpresaConSii(['rut' => '76111111-1']);
        $empresaB = $this->crearEmpresaConSii(['rut' => '77222222-2']);
        $clienteA = $this->crearClienteCompleto($empresaA->id, ['rut' => '10000000-0']);
        $clienteB = $this->crearClienteCompleto($empresaB->id, ['rut' => '20000000-0']);
        $facturaA = $this->crearFacturaVentaCompleta($empresaA, $clienteA);
        $facturaB = $this->crearFacturaVentaCompleta($empresaB, $clienteB);

        $dteA = $this->mapper->mapear($facturaA);
        $dteB = $this->mapper->mapear($facturaB);

        $this->assertSame($empresaA->id, (int) $dteA->empresa_id);
        $this->assertSame($empresaB->id, (int) $dteB->empresa_id);
        $this->assertNotSame($dteA->id, $dteB->id);
        $this->assertSame($facturaA->id, (int) $dteA->factura_id);
        $this->assertSame($facturaB->id, (int) $dteB->factura_id);
    }

    public function test_factura_queda_vinculada_a_dte_emitido_id_post_mapeo(): void
    {
        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);

        $this->assertSame($dte->id, (int) $factura->fresh()->sii_dte_emitido_id);
    }
}
