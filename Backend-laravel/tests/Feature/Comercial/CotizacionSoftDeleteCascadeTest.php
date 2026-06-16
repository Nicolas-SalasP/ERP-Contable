<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\CotizacionDetalle;
use App\Domains\Comercial\Models\EstadoCotizacion;

class CotizacionSoftDeleteCascadeTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected Empresa $empresa;
    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
        ]);

        $this->empresa = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Cascade Test SpA']);
        $this->usuario = User::create([
            'nombre' => 'Tester',
            'email' => 'cascade@test.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
    }

    public function test_soft_delete_cotizacion_cascada_a_detalles(): void
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '9.9.9.9-9',
            'razon_social' => 'Cliente Cascade',
            'estado' => 'ACTIVO',
        ]);

        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'numero_cotizacion' => 'COT-CASCADE-01',
            'fecha_emision' => now()->format('Y-m-d'),
            'fecha_validez' => now()->addDays(30)->format('Y-m-d'),
            'validez' => 30,
            'subtotal' => 10000,
            'porcentaje_descuento' => 0,
            'monto_descuento' => 0,
            'monto_neto' => 10000,
            'porcentaje_iva' => 19,
            'monto_iva' => 1900,
            'monto_total' => 11900,
            'total' => 11900,
            'estado_id' => 1,
            'es_afecta' => true,
        ]);

        $detalle = CotizacionDetalle::create([
            'cotizacion_id' => $cotizacion->id,
            'producto_nombre' => 'Producto Cascade',
            'cantidad' => 2,
            'precio_unitario' => 5000,
            'subtotal' => 10000,
        ]);

        $cotizacion->delete();

        $this->assertSoftDeleted('cotizaciones', ['id' => $cotizacion->id]);
        $this->assertSoftDeleted('cotizacion_detalles', ['id' => $detalle->id]);
    }

    public function test_detalles_no_aparecen_en_consultas_normales_tras_soft_delete_cotizacion(): void
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '8.8.8.8-8',
            'razon_social' => 'Cliente Fantasma',
            'estado' => 'ACTIVO',
        ]);

        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'numero_cotizacion' => 'COT-CASCADE-02',
            'fecha_emision' => now()->format('Y-m-d'),
            'fecha_validez' => now()->addDays(30)->format('Y-m-d'),
            'validez' => 30,
            'subtotal' => 5000,
            'porcentaje_descuento' => 0,
            'monto_descuento' => 0,
            'monto_neto' => 5000,
            'porcentaje_iva' => 19,
            'monto_iva' => 950,
            'monto_total' => 5950,
            'total' => 5950,
            'estado_id' => 1,
            'es_afecta' => true,
        ]);

        CotizacionDetalle::create([
            'cotizacion_id' => $cotizacion->id,
            'producto_nombre' => 'Item Fantasma',
            'cantidad' => 1,
            'precio_unitario' => 5000,
            'subtotal' => 5000,
        ]);

        $cotizacion->delete();

        $detallesVisibles = CotizacionDetalle::where('cotizacion_id', $cotizacion->id)->count();
        $this->assertEquals(0, $detallesVisibles);
    }
}
