<?php

namespace Tests\Feature\Sii\Modelos;

use App\Domains\Comercial\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ClienteSiiTraitTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    public function test_cliente_puede_guardar_comuna_ciudad_giro_y_codigo_actividad(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();

        $cliente = Cliente::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '11111111-1',
            'razon_social'     => 'Cliente Test',
            'comuna'           => 'Providencia',
            'ciudad'           => 'Santiago',
            'giro'             => 'Servicios profesionales',
            'codigo_actividad' => 749900,
        ]);

        $persistido = Cliente::find($cliente->id);

        $this->assertSame('Providencia', $persistido->comuna);
        $this->assertSame('Santiago', $persistido->ciudad);
        $this->assertSame('Servicios profesionales', $persistido->giro);
        $this->assertSame(749900, $persistido->codigo_actividad);
    }

    public function test_fillable_de_cliente_incluye_campos_originales_y_los_sii(): void
    {
        $cliente  = new Cliente();
        $fillable = $cliente->getFillable();

        // Originales del modelo Cliente.
        $this->assertContains('rut', $fillable);
        $this->assertContains('razon_social', $fillable);
        $this->assertContains('estado', $fillable);
        $this->assertContains('empresa_id', $fillable);

        // Anadidos por el trait.
        $this->assertContains('comuna', $fillable);
        $this->assertContains('ciudad', $fillable);
        $this->assertContains('giro', $fillable);
        $this->assertContains('codigo_actividad', $fillable);
    }

    public function test_cliente_acepta_campos_sii_nullable(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();

        $cliente = Cliente::create([
            'empresa_id'   => $empresa->id,
            'rut'          => '22222222-2',
            'razon_social' => 'Cliente Sin SII',
        ]);

        $persistido = Cliente::find($cliente->id);

        $this->assertNull($persistido->comuna);
        $this->assertNull($persistido->ciudad);
        $this->assertNull($persistido->giro);
        $this->assertNull($persistido->codigo_actividad);
    }
}
