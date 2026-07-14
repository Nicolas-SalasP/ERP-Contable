<?php

namespace Tests\Feature\Sii;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Services\Mapping\FacturaAComercialDteMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Regresion del bug de auditoria: FacturaAComercialDteMapper tenia
 * `private const TASA_IVA = 19.00;` hardcodeada (en PORCENTAJE), independiente
 * de config('fiscal.tasa_iva') (guardado como FRACCION, ej. 0.19), que es el
 * unico punto de cambio de la tasa de IVA para el resto de la app
 * (ver FacturaService.php). Si el legislador cambiaba el IVA, la factura se
 * calculaba con la tasa nueva pero se declaraba al SII con 19.00 fijo.
 *
 * El test con tasa 0.21 es el que habria detectado el bug original: con el
 * bug, tasa_iva seguia siendo 19.00 sin importar el valor de config.
 */
class TasaIvaConfigurableTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    private FacturaAComercialDteMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->mapper = app(FacturaAComercialDteMapper::class);
    }

    private function crearEmpresaConSii(array $overrides = []): Empresa
    {
        return Empresa::create(array_merge([
            'rut' => '76123456-7',
            'razon_social' => 'EMPRESA EMISORA',
            'giro_emisor' => 'Servicios profesionales',
            'codigo_actividad_sii' => 471910,
            'direccion' => 'Av Siempre Viva 742',
            'comuna' => 'Santiago',
            'ciudad' => 'Santiago',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha' => '2024-08-22',
            'ambiente_sii' => 'certificacion',
        ], $overrides));
    }

    private function crearClienteCompleto(int $empresaId, array $overrides = []): Cliente
    {
        return Cliente::create(array_merge([
            'rut' => '11222333-4',
            'razon_social' => 'CLIENTE FINAL S.A.',
            'contacto_nombre' => 'Juan Perez',
            'contacto_email' => 'juan@cliente.cl',
            'direccion' => 'Calle Cliente 100',
            'telefono' => '+56222334455',
            'email' => 'general@cliente.cl',
            'estado' => 'ACTIVO',
            'empresa_id' => $empresaId,
            'comuna' => 'Providencia',
            'ciudad' => 'Santiago',
            'giro' => 'Comercio al por menor',
            'codigo_actividad' => 471910,
        ], $overrides));
    }

    private function crearFacturaVentaCompleta(
        Empresa $empresa,
        Cliente $cliente,
        array $overridesFactura = [],
        array $overridesDetalle = []
    ): Factura {
        $factura = Factura::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'proveedor_id' => null,
            'cliente_id' => $cliente->id,
            'numero_factura' => 'F-'.random_int(1000, 99999),
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'tipo_dte' => 33,
            'fecha_emision' => now()->toDateString(),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190,
            'monto_exento' => 0,
            'estado' => 'REGISTRADA',
            'moneda' => 'CLP',
        ], $overridesFactura));

        FacturaDetalle::create(array_merge([
            'factura_id' => $factura->id,
            'numero_linea' => 1,
            'nombre_item' => 'Servicio profesional',
            'descripcion' => 'Asesoria mes',
            'cantidad' => 1,
            'unidad_medida' => 'UN',
            'precio_unitario' => 1000,
            'descuento_pct' => 0,
            'descuento_monto' => 0,
            'recargo_pct' => 0,
            'recargo_monto' => 0,
            'exento' => false,
            'monto_item' => 1000,
        ], $overridesDetalle));

        return $factura->fresh(['detalles', 'cliente', 'empresa']);
    }

    public function test_tasa_iva_config_019_produce_1900_en_porcentaje(): void
    {
        config(['fiscal.tasa_iva' => 0.19]);

        $empresa = $this->crearEmpresaConSii();
        $cliente = $this->crearClienteCompleto($empresa->id);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente);

        $dte = $this->mapper->mapear($factura);

        $this->assertSame(19.00, (float) $dte->tasa_iva);
    }

    /**
     * Test clave: si el fix no estuviera aplicado (TASA_IVA hardcodeada a 19.00),
     * este test fallaria porque tasa_iva seguiria siendo 19.00 en vez de 21.00
     * al cambiar config('fiscal.tasa_iva') a 0.21.
     */
    public function test_tasa_iva_config_021_produce_2100_en_porcentaje(): void
    {
        config(['fiscal.tasa_iva' => 0.21]);

        $empresa = $this->crearEmpresaConSii(['rut' => '76777888-K']);
        $cliente = $this->crearClienteCompleto($empresa->id, ['rut' => '10000000-0']);
        // CuadraturaMontosValidator exige que iva == monto_neto * tasa_iva; con 21%
        // sobre neto=1000 el iva esperado es 210 (no 190, el 19% por defecto).
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'monto_iva' => 210,
            'monto_bruto' => 1210,
        ]);

        $dte = $this->mapper->mapear($factura);

        $this->assertSame(21.00, (float) $dte->tasa_iva);
    }

    /**
     * DTE exento (tipo 34): decision de diseno documentada en el mapper
     * (FacturaAComercialDteMapper::construirDte). El campo <TasaIVA> (campo 111)
     * del formato DTE del SII (docs/sii-normativa/formato_dte_202602.pdf) no
     * pudo confirmarse de forma concluyente via extraccion de texto para el
     * caso de documentos exentos (la tabla de obligatoriedad por tipo de
     * documento se desalineo en la extraccion). Se aplica 0 por CONSISTENCIA
     * INTERNA con monto_neto/iva (que ya son 0 para exentos en este mapper),
     * no por confirmacion explicita del PDF.
     */
    public function test_tasa_iva_en_dte_exento_tipo_34_es_cero_por_consistencia_interna(): void
    {
        config(['fiscal.tasa_iva' => 0.19]);

        $empresa = $this->crearEmpresaConSii(['rut' => '76333222-1']);
        $cliente = $this->crearClienteCompleto($empresa->id, ['rut' => '20000000-0']);
        $factura = $this->crearFacturaVentaCompleta($empresa, $cliente, [
            'tipo_dte' => 34,
            'monto_neto' => 0,
            'monto_iva' => 0,
            'monto_exento' => 5000,
            'monto_bruto' => 5000,
        ], [
            'exento' => true,
            'monto_item' => 5000,
            'precio_unitario' => 5000,
        ]);

        $dte = $this->mapper->mapear($factura);

        $this->assertSame(0.0, (float) $dte->tasa_iva);
    }
}
