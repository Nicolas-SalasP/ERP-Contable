<?php

namespace Tests\Feature\Sii\Modelos;

use App\Domains\Core\Models\Empresa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class EmpresaSiiTraitTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    public function test_empresa_puede_guardar_campos_sii_via_trait(): void
    {
        $this->prepararEntornoBase();

        $empresa = Empresa::create([
            'rut'                      => '76086428-5',
            'razon_social'             => 'ACME SpA',
            'giro_emisor'              => 'Venta al por menor',
            'codigo_actividad_sii'     => 471910,
            'comuna'                   => 'Santiago',
            'ciudad'                   => 'Santiago',
            'resolucion_sii_numero'    => 80,
            'resolucion_sii_fecha'     => '2024-01-15',
            'email_intercambio_sii'    => 'intercambio@acme.cl',
            'rut_representante_legal'  => '12345678-9',
        ]);

        $persistida = Empresa::find($empresa->id);

        $this->assertSame('Venta al por menor', $persistida->giro_emisor);
        $this->assertSame(471910, $persistida->codigo_actividad_sii);
        $this->assertSame('Santiago', $persistida->comuna);
        $this->assertSame('Santiago', $persistida->ciudad);
        $this->assertSame(80, $persistida->resolucion_sii_numero);
        $this->assertSame('intercambio@acme.cl', $persistida->email_intercambio_sii);
        $this->assertSame('12345678-9', $persistida->rut_representante_legal);
    }

    public function test_resolucion_sii_fecha_se_castea_a_carbon(): void
    {
        $this->prepararEntornoBase();

        $empresa = Empresa::create([
            'rut'                  => '76086428-5',
            'razon_social'         => 'ACME SpA',
            'resolucion_sii_fecha' => '2024-01-15',
        ]);

        $this->assertInstanceOf(Carbon::class, $empresa->fresh()->resolucion_sii_fecha);
        $this->assertSame('2024-01-15', $empresa->fresh()->resolucion_sii_fecha->toDateString());
    }

    public function test_ambiente_sii_default_es_certificacion(): void
    {
        $this->prepararEntornoBase();

        $empresa = Empresa::create([
            'rut'          => '76086428-5',
            'razon_social' => 'ACME SpA',
        ]);

        $this->assertSame('certificacion', $empresa->fresh()->ambiente_sii);
    }

    public function test_fillable_de_empresa_incluye_los_9_campos_sii(): void
    {
        $empresa = new Empresa();
        $fillable = $empresa->getFillable();

        $camposSii = [
            'giro_emisor',
            'codigo_actividad_sii',
            'comuna',
            'ciudad',
            'resolucion_sii_numero',
            'resolucion_sii_fecha',
            'ambiente_sii',
            'email_intercambio_sii',
            'rut_representante_legal',
        ];

        foreach ($camposSii as $campo) {
            $this->assertContains($campo, $fillable, "El campo '$campo' deberia estar en fillable de Empresa.");
        }

        // Tambien preserva los originales.
        $this->assertContains('rut', $fillable);
        $this->assertContains('razon_social', $fillable);
    }
}
