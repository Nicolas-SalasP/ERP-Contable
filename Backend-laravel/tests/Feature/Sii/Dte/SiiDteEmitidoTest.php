<?php

namespace Tests\Feature\Sii\Dte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiiDteEmitidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_dte_emitido_minimo_persiste(): void
    {
        $dte = SiiDteEmitido::factory()->create();

        $this->assertNotNull($dte->id);
        $this->assertSame(SiiDteEmitido::TIPO_FACTURA, $dte->tipo_dte);
        $this->assertSame(SiiDteEmitido::ESTADO_BORRADOR, $dte->estado);
        $this->assertGreaterThan(0, $dte->monto_total);
    }

    public function test_unique_compuesto_empresa_tipo_folio_bloquea_duplicados(): void
    {
        $dte1 = SiiDteEmitido::factory()->create([
            'tipo_dte' => SiiDteEmitido::TIPO_FACTURA,
            'folio'    => 100,
        ]);

        $this->expectException(QueryException::class);

        SiiDteEmitido::factory()->create([
            'empresa_id' => $dte1->empresa_id,
            'tipo_dte'   => SiiDteEmitido::TIPO_FACTURA,
            'folio'      => 100,
        ]);
    }

    public function test_estado_por_defecto_es_borrador(): void
    {
        $dte = SiiDteEmitido::factory()->create();

        $this->assertSame('BORRADOR', $dte->fresh()->estado);
    }

    public function test_relacion_belongs_to_empresa_funciona(): void
    {
        $dte = SiiDteEmitido::factory()->create();

        $this->assertInstanceOf(Empresa::class, $dte->empresa);
        $this->assertSame($dte->empresa_id, $dte->empresa->id);
    }

    public function test_relacion_belongs_to_factura_nullable_funciona(): void
    {
        $dte = SiiDteEmitido::factory()->create(['factura_id' => null]);

        $this->assertNull($dte->factura);
        $this->assertNull($dte->factura_id);
    }

    public function test_scope_aceptados_filtra_correctamente(): void
    {
        SiiDteEmitido::factory()->aceptado()->create();
        SiiDteEmitido::factory()->rechazado()->create();
        SiiDteEmitido::factory()->create(); // borrador

        $aceptados = SiiDteEmitido::aceptados()->get();

        $this->assertCount(1, $aceptados);
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO, $aceptados->first()->estado);
    }

    public function test_scope_por_empresa_aisla_tenant(): void
    {
        $dteA = SiiDteEmitido::factory()->create();
        $dteB = SiiDteEmitido::factory()->create();

        $this->assertNotEquals($dteA->empresa_id, $dteB->empresa_id);

        $resultadosA = SiiDteEmitido::porEmpresa($dteA->empresa_id)->get();

        $this->assertCount(1, $resultadosA);
        $this->assertSame($dteA->id, $resultadosA->first()->id);
    }

    public function test_constantes_de_tipo_dte_coinciden_con_xsd(): void
    {
        $this->assertSame(33,  SiiDteEmitido::TIPO_FACTURA);
        $this->assertSame(34,  SiiDteEmitido::TIPO_FACTURA_EXENTA);
        $this->assertSame(39,  SiiDteEmitido::TIPO_BOLETA);
        $this->assertSame(41,  SiiDteEmitido::TIPO_BOLETA_EXENTA);
        $this->assertSame(43,  SiiDteEmitido::TIPO_LIQUIDACION_FACTURA);
        $this->assertSame(46,  SiiDteEmitido::TIPO_FACTURA_COMPRA);
        $this->assertSame(52,  SiiDteEmitido::TIPO_GUIA_DESPACHO);
        $this->assertSame(56,  SiiDteEmitido::TIPO_NOTA_DEBITO);
        $this->assertSame(61,  SiiDteEmitido::TIPO_NOTA_CREDITO);
        $this->assertSame(110, SiiDteEmitido::TIPO_FACTURA_EXPORTACION);
        $this->assertSame(111, SiiDteEmitido::TIPO_NOTA_DEBITO_EXPORTACION);
        $this->assertSame(112, SiiDteEmitido::TIPO_NOTA_CREDITO_EXPORTACION);
    }

    public function test_casts_de_fechas_devuelven_carbon(): void
    {
        $dte = SiiDteEmitido::factory()->aceptado()->create();

        $this->assertInstanceOf(Carbon::class, $dte->fresh()->fecha_emision);
        $this->assertInstanceOf(Carbon::class, $dte->fresh()->fecha_aceptacion_sii);
    }

    public function test_es_cedible_default_es_true(): void
    {
        $dte = SiiDteEmitido::factory()->create();

        $this->assertTrue($dte->fresh()->es_cedible);
    }
}
