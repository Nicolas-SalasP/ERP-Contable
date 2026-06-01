<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoTraslado;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteXmlBuilderTipo52Test extends TestCase
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

    private function guia(bool $conTraslado = true): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->guiaDespacho()->create([
            'emisor_acteco'    => 471910,
            'emisor_giro'      => 'Distribucion',
            'emisor_direccion' => 'Bodega 1',
            'emisor_comuna'    => 'Quilicura',
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Mercaderia',
        ]);

        if ($conTraslado) {
            SiiDteEmitidoTraslado::factory()->create([
                'dte_emitido_id'     => $dte->id,
                'indicador_traslado' => SiiDteEmitidoTraslado::IND_OPERACION_CONSTITUYE_VENTA,
                'rut_chofer'         => '11111111-1',
                'nombre_chofer'      => 'Pedro Chofer',
                'patente'            => 'AB1234',
                'direccion_destino'  => 'Av Destino 200',
                'comuna_destino'     => 'Las Condes',
            ]);
        }

        return $dte->fresh(['detalles', 'referencias', 'traslado.madera']);
    }

    public function test_guia_sin_traslado_lanza_excepcion(): void
    {
        $this->expectException(DteIncompletoException::class);
        $this->builder->build($this->guia(false));
    }

    public function test_guia_con_traslado_incluye_IndTraslado_RUTChofer_Patente(): void
    {
        $xml = $this->builder->build($this->guia(true));

        $this->assertStringContainsString('<IndTraslado>1</IndTraslado>', $xml);
        $this->assertStringContainsString('<RUTChofer>11111111-1</RUTChofer>', $xml);
        $this->assertStringContainsString('<NombreChofer>Pedro Chofer</NombreChofer>', $xml);
        $this->assertStringContainsString('<Patente>AB1234</Patente>', $xml);
    }

    public function test_guia_pasa_xsd(): void
    {
        $xml = $this->builder->build($this->guia(true));
        $this->assertStringContainsString('<TipoDTE>52</TipoDTE>', $xml);
    }
}
