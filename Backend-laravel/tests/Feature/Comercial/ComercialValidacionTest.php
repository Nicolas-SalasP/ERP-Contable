<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Core\Models\Pais;

class ComercialValidacionTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Val SpA', 'regimen_tributario' => '14_D3']);
        $this->usuario = User::create(['nombre' => 'Admin', 'email' => 'a@v.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_capa8_rechaza_cantidades_y_precios_negativos_o_cero_en_cotizacion()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '99.555.555-5', 'razon_social' => 'Cliente Valido', 'estado' => 'ACTIVO']);
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-NEGATIVA',
            'detalles' => [['producto_nombre' => 'X', 'cantidad' => -5, 'precio_unitario' => -10000, 'subtotal' => 50000]]
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['detalles.0.cantidad', 'detalles.0.precio_unitario']);
    }

    public function test_capa8_rechaza_descuentos_mayores_al_100_por_ciento()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.2.3.4-5', 'razon_social' => 'Cliente Feliz', 'estado' => 'ACTIVO']);
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'porcentaje_descuento' => 150,
            'detalles' => [['producto_nombre' => 'A', 'cantidad' => 1, 'precio_unitario' => 1000]]
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['porcentaje_descuento']);
    }

    public function test_capa8_rechaza_fechas_con_formatos_basura_o_inexistentes()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'PR-F', 'rut' => '2.2.2.2-2', 'razon_social' => 'Prov Fecha', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-FECHA',
            'tipo_documento' => 'COMPRA',
            'fecha_emision' => 'esto-no-es-una-fecha',
            'monto_neto' => 100,
            'monto_iva' => 19,
            'monto_bruto' => 119,
            'cuentaDestino' => '410101',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['fecha_emision']);
    }

    public function test_capa8_rechaza_letras_en_montos_financieros()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'PR-NUM', 'rut' => '3.3.3.3-3', 'razon_social' => 'Prov Num', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-TEXTO',
            'tipo_documento' => 'COMPRA',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 'cien mil',
            'monto_iva' => 'diecinueve mil',
            'monto_bruto' => 'ciento diecinueve mil',
            'cuentaDestino' => '410101',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['monto_neto', 'monto_bruto']);
    }

    public function test_capa8_rechaza_creacion_de_cliente_solo_con_espacios_en_blanco()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/clientes', ['rut' => '   ', 'razon_social' => '    ']);
        $response->assertStatus(422)->assertJsonValidationErrors(['rut', 'razon_social']);
    }

    public function test_capa8_rechaza_textos_excesivamente_largos_que_romperian_la_bd()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'PR-LONG', 'rut' => '3.3.3.3-3', 'razon_social' => 'Prov', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => str_repeat('A', 300),
            'tipo_documento' => 'COMPRA',
            'monto_neto' => 100,
            'monto_iva' => 19,
            'monto_bruto' => 119
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['numero_factura']);
    }

    public function test_capa8_rechaza_arrays_donde_se_esperan_textos_type_juggling()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/clientes', [
            'rut' => '11.111.111-1',
            'razon_social' => ['Soy un Array', 'Malicioso'],
            'email' => 'array@hack.cl'
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['razon_social']);
    }

    public function test_rechaza_cotizacion_sin_detalles_o_productos()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '88.123.123-1', 'razon_social' => 'Cliente', 'estado' => 'ACTIVO']);
        $response = $this->actingAs($this->usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-VACIA',
            'monto_neto' => 1000,
            'detalles' => []
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['detalles']);
    }
}