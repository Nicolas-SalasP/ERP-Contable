<?php

namespace Tests\Feature\Sii\Catalogos;

use App\Domains\Sii\Database\Seeders\SiiCatalogosSeeder;
use App\Domains\Sii\Models\Catalogos\ActecoSii;
use App\Domains\Sii\Models\Catalogos\ComunaSii;
use App\Domains\Sii\Models\Catalogos\FormaPagoSii;
use App\Domains\Sii\Models\Catalogos\ImpuestoSii;
use App\Domains\Sii\Models\Catalogos\UnidadSii;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => SiiCatalogosSeeder::class]);
    }

    public function test_sii_cat_forma_pago_se_carga_con_tres_registros(): void
    {
        $this->assertSame(3, FormaPagoSii::count());

        $this->assertDatabaseHas('sii_cat_forma_pago', [
            'codigo' => FormaPagoSii::CONTADO,
            'nombre' => 'Contado',
        ]);
        $this->assertDatabaseHas('sii_cat_forma_pago', [
            'codigo' => FormaPagoSii::CREDITO,
            'nombre' => 'Crédito',
        ]);
        $this->assertDatabaseHas('sii_cat_forma_pago', [
            'codigo' => FormaPagoSii::SIN_COSTO,
            'nombre' => 'Sin Costo',
        ]);
    }

    public function test_sii_cat_impuestos_contiene_codigo_14_iva_con_tasa_19(): void
    {
        $iva = ImpuestoSii::where('codigo', ImpuestoSii::CODIGO_IVA)->first();

        $this->assertNotNull($iva, 'No se encontro el impuesto IVA (codigo 14) en sii_cat_impuestos.');
        $this->assertSame('IVA', $iva->nombre);
        $this->assertSame('19.00', (string) $iva->tasa);
        $this->assertSame('iva', $iva->tipo);
        $this->assertFalse((bool) $iva->es_adicional);
    }

    public function test_sii_cat_unidades_contiene_un_y_kg(): void
    {
        $this->assertDatabaseHas('sii_cat_unidades', ['codigo' => 'UN', 'nombre' => 'Unidad']);
        $this->assertDatabaseHas('sii_cat_unidades', ['codigo' => 'KG', 'nombre' => 'Kilogramo']);

        $this->assertGreaterThanOrEqual(21, UnidadSii::count());
    }

    public function test_sii_cat_comunas_tabla_creada_y_vacia(): void
    {
        $this->assertTrue(
            Schema::hasTable('sii_cat_comunas'),
            'La tabla sii_cat_comunas no existe.'
        );

        $this->assertSame(
            0,
            ComunaSii::count(),
            'sii_cat_comunas deberia quedar vacia en esta sub-OT (carga real en F1.1-bis).'
        );
    }

    public function test_sii_cat_acteco_tabla_creada_y_vacia(): void
    {
        $this->assertTrue(
            Schema::hasTable('sii_cat_acteco'),
            'La tabla sii_cat_acteco no existe.'
        );

        $this->assertSame(
            0,
            ActecoSii::count(),
            'sii_cat_acteco deberia quedar vacia en esta sub-OT (carga real en F1.1-bis).'
        );
    }
}
