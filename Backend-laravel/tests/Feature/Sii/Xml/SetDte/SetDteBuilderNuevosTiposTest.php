<?php

namespace Tests\Feature\Sii\Xml\SetDte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoTraslado;
use App\Domains\Sii\Services\Xml\DteSigner;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\EnvioDteXsdValidator;
use App\Domains\Sii\Services\Xml\SetDte\SetDteBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Round-trip completo (Documento -> EnvioDTE firmado -> validado contra DTE_v10.xsd real) para
 * los tipos agregados: Factura de Compra (46) y Guia de Despacho (52). A diferencia de boleta
 * (39/41), ambos reusan el mismo EnvioDTE/DTEDefType que factura -- no hay XSD ni envelope
 * nuevo, solo el mapper de negocio (receptor=Proveedor para 46, bloque Transporte para 52).
 */
class SetDteBuilderNuevosTiposTest extends TestCase
{
    use GeneraCertificadoParaTests;
    use PreparaEntornoBase;
    use RefreshDatabase;

    private SetDteBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        $this->builder = app(SetDteBuilder::class);
    }

    /** @return array{0: Empresa, 1: SiiDteEmitido, 2: string} */
    private function dteFirmado(int $tipoDte, string $rut, int $folio, ?callable $overridesDte = null): array
    {
        $empresa = Empresa::create([
            'rut' => $rut,
            'razon_social' => 'EMPRESA NUEVOS TIPOS',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha' => '2024-08-22',
            'ambiente_sii' => 'certificacion',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'OPERADOR '.$rut);
        [$caf] = $this->crearCafActivoParaEmpresa($empresa, $tipoDte, 1, 50);

        $datosDte = [
            'empresa_id' => $empresa->id,
            'tipo_dte' => $tipoDte,
            'emisor_rut' => $rut,
            'emisor_acteco' => 471910,
            'emisor_giro' => 'X',
            'emisor_direccion' => 'X',
            'emisor_comuna' => 'X',
            'folio' => $folio,
            'monto_neto' => 1000,
            'iva' => 190,
            'monto_total' => 1190,
        ];
        if ($overridesDte !== null) {
            $datosDte = array_merge($datosDte, $overridesDte());
        }

        $dte = SiiDteEmitido::factory()->create($datosDte);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea' => 1,
            'nombre_item' => 'X',
            'cantidad' => 1,
            'precio_unitario' => 1000,
            'monto_item' => 1000,
        ]);

        if ($tipoDte === SiiDteEmitido::TIPO_GUIA_DESPACHO) {
            SiiDteEmitidoTraslado::factory()->create([
                'dte_emitido_id' => $dte->id,
                'indicador_traslado' => SiiDteEmitidoTraslado::IND_OPERACION_CONSTITUYE_VENTA,
            ]);
        }

        $dte = $dte->fresh(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales']);

        $xmlConTed = app(DteXmlBuilder::class)->build($dte, $caf);
        $xmlFirmado = app(DteSigner::class)->firmar($xmlConTed, $empresa);

        return [$empresa, $dte, $xmlFirmado];
    }

    public function test_factura_de_compra_46_pasa_xsd_real_de_extremo_a_extremo(): void
    {
        [$empresa, $dte, $xml] = $this->dteFirmado(46, '76666111-2', 10, fn () => [
            'receptor_rut' => '65432100-8',
            'receptor_razon_social' => 'PROVEEDOR AGRICOLA',
        ]);

        $this->assertStringContainsString('<TipoDTE>46</TipoDTE>', $xml);

        $sinFirma = $this->builder->build($empresa, [['dte' => $dte, 'xml' => $xml]]);
        $firmado = app(SetDteSigner::class)->firmar($sinFirma, $empresa);

        $this->assertStringContainsString('<EnvioDTE', $firmado);
        app(EnvioDteXsdValidator::class)->validar($firmado);
        $this->assertTrue(true);
    }

    public function test_guia_de_despacho_52_pasa_xsd_real_de_extremo_a_extremo(): void
    {
        [$empresa, $dte, $xml] = $this->dteFirmado(52, '76666222-3', 20);

        $this->assertStringContainsString('<TipoDTE>52</TipoDTE>', $xml);
        $this->assertStringContainsString('<IndTraslado>1</IndTraslado>', $xml);

        $sinFirma = $this->builder->build($empresa, [['dte' => $dte, 'xml' => $xml]]);
        $firmado = app(SetDteSigner::class)->firmar($sinFirma, $empresa);

        app(EnvioDteXsdValidator::class)->validar($firmado);
        $this->assertTrue(true);
    }

    // NOTA: Liquidacion Factura (43) y Exportaciones (110-112) NO se agregan aqui -- a
    // diferencia de 46/52, el XSD oficial (DTE_v10.xsd) los define bajo raices GLOBALES propias
    // (<Liquidacion>/LIQType, <Exportaciones>/EXPType), no <DTE>/DTEDefType. TipoDTE=43 incluso
    // falla la validacion contra el enum DTEType (que solo admite 33/34/46/52/56/61) --
    // verificado empiricamente, no es una omision menor de DteXmlBuilder. Requieren un builder
    // XML separado (patron similar a boleta) + datos de negocio que Comercial no modela hoy
    // (mandante/comision para 43; aduana/moneda extranjera para 110-112). Alcance de otra sesion.
}
