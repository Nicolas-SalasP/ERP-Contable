<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use App\Domains\Sii\Support\Iso88591Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteXmlBuilderEncodingTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private DteXmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->builder = new DteXmlBuilder(new DteXsdValidator());
    }

    private function dteConRazonSocial(string $razon, ?string $direccion = 'Calle Test 1'): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->factura()->create([
            'emisor_acteco'         => 471910,
            'emisor_giro'           => 'Comercio',
            'emisor_direccion'      => $direccion,
            'emisor_comuna'         => 'Santiago',
            'receptor_razon_social' => $razon,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Item',
        ]);

        return $dte->fresh(['detalles', 'referencias', 'traslado.madera']);
    }

    public function test_razon_social_con_eñe_se_persiste_en_iso8859_1(): void
    {
        $dte = $this->dteConRazonSocial('Empresa España y Cía Ltda');
        $xml = $this->builder->build($dte);

        // El XML esta en ISO-8859-1. Para verificar el contenido, decodifico a UTF-8.
        $utf8 = Iso88591Helper::convertToUtf8(preg_replace('/^<\?xml[^?]*\?>/', '', $xml));
        $this->assertStringContainsString('Empresa España y Cía Ltda', $utf8);
    }

    public function test_direccion_con_acentos_se_persiste_correctamente(): void
    {
        $dte = $this->dteConRazonSocial('Cliente X', 'Av. José Joaquín Pérez 1234');
        $xml = $this->builder->build($dte);

        $utf8 = Iso88591Helper::convertToUtf8(preg_replace('/^<\?xml[^?]*\?>/', '', $xml));
        $this->assertStringContainsString('José Joaquín Pérez', $utf8);
    }

    public function test_razon_social_con_emoji_lanza_excepcion_clara(): void
    {
        $this->expectException(DteIncompletoException::class);
        $dte = $this->dteConRazonSocial('Empresa 🎉 LTDA');
        $this->builder->build($dte);
    }

    public function test_round_trip_decodificado_recupera_string_original(): void
    {
        $dte = $this->dteConRazonSocial('Ñandú Ñoño & Compañía');
        $xml = $this->builder->build($dte);

        $utf8 = Iso88591Helper::convertToUtf8(preg_replace('/^<\?xml[^?]*\?>/', '', $xml));
        $this->assertStringContainsString('Ñandú Ñoño', $utf8);
    }

    public function test_declaracion_xml_es_ISO_8859_1(): void
    {
        $xml = $this->builder->build($this->dteConRazonSocial('Cliente'));

        $this->assertStringStartsWith('<?xml version="1.0" encoding="ISO-8859-1"', $xml);
        $this->assertStringNotContainsString('encoding="UTF-8"', $xml);
    }
}
