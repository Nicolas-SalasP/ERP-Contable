<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Contabilidad\Models\PlanCuenta;
use Illuminate\Support\Facades\DB;

class ComercialTest extends TestCase
{
    use RefreshDatabase;

    protected $empresaA;
    protected $usuarioAdmin;
    protected $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rolAdmin = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        $rolVentas = Rol::create(['id' => 2, 'nombre' => 'Vendedor', 'jerarquia' => 10]);

        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]);

        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
            ['id' => 2, 'nombre' => 'Enviada'],
            ['id' => 3, 'nombre' => 'Aprobada'],
            ['id' => 4, 'nombre' => 'Rechazada'],
            ['id' => 5, 'nombre' => 'Facturada']
        ]);

        $this->empresaA = Empresa::create([
            'rut' => '77.777.777-7',
            'razon_social' => 'Comercializadora Sur SpA',
            'regimen_tributario' => '14_D3',
            'tasa_impuesto' => 25.00
        ]);

        $this->usuarioAdmin = User::create([
            'nombre' => 'Gerente Admin',
            'email' => 'admin@sur.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $rolAdmin->id,
            'estado_suscripcion_id' => 1
        ]);

        $this->vendedor = User::create([
            'nombre' => 'Ejecutivo Ventas',
            'email' => 'ventas@sur.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $rolVentas->id,
            'estado_suscripcion_id' => 1
        ]);
    }

    // PRUEBA DE AISLAMIENTO MULTITENANT

    public function test_aislamiento_multitenant_en_clientes_y_proveedores()
    {
        $empresaB = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Competencia SpA']);
        $hacker = User::create(['nombre' => 'Hacker', 'email' => 'hacker@comp.cl', 'password' => bcrypt('123'), 'empresa_id' => $empresaB->id, 'rol_id' => 1, 'estado_suscripcion_id' => 1]);

        Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '11.111.111-1', 'razon_social' => 'Cliente Oro', 'email' => 'oro@cliente.cl', 'estado' => 'ACTIVO']);
        Proveedor::create(['empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-1', 'rut' => '22.222.222-2', 'razon_social' => 'Prov Estratégico', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $responseClientes = $this->actingAs($hacker)->getJson('/api/clientes');
        $responseProveedores = $this->actingAs($hacker)->getJson('/api/proveedores');

        $responseClientes->assertStatus(200);
        $this->assertEmpty($responseClientes->json('data'));
        $this->assertEmpty($responseProveedores->json('data'));
    }

    // PRUEBA DE LÓGICA DE NEGOCIO (CLIENTES)

    public function test_rechaza_creacion_de_cliente_con_rut_duplicado_en_la_misma_empresa()
    {
        $this->actingAs($this->vendedor)->postJson('/api/clientes', [
            'rut' => '76.543.210-K',
            'razon_social' => 'Constructora Original',
            'email' => 'contacto@original.cl'
        ]);

        $response = $this->actingAs($this->vendedor)->postJson('/api/clientes', [
            'rut' => '76.543.210-K',
            'razon_social' => 'Constructora Clon',
            'email' => 'clon@clon.cl'
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertSee('ya se encuentra registrado');
    }

    // PRUEBA DE LÓGICA TRIBUTARIA (FACTURAS DE COMPRA)
    public function test_factura_compra_rechaza_montos_matematicamente_inconsistentes()
    {
        Proveedor::unguard();
        $proveedor = Proveedor::create([
            'empresa_id' => $this->empresaA->id,
            'razon_social' => 'Proveedor de Prueba',
            'rut' => '77.777.777-7',
            'codigo_interno' => 'P-TEST',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP'
        ]);
        Proveedor::reguard();

        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresaA->id, 'codigo' => '410101'], ['nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresaA->id, 'codigo' => '353350'], ['nombre' => 'IVA', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresaA->id, 'codigo' => '352105'], ['nombre' => 'Proveedor', 'tipo' => 'PASIVO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'F-MATH',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 10000,
            'monto_iva' => 1900,
            'monto_bruto' => 999999,
            'tipo_documento' => 'FACTURA',
            'cuentaDestino' => '410101',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertSee('Inconsistencia tributaria');
    }

    // PRUEBA E2E: CREACIÓN DE COTIZACIÓN CON DETALLES

    public function test_crear_cotizacion_guarda_correctamente_cabecera_y_detalles()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '33.333.333-3', 'razon_social' => 'Cliente Nuevo', 'email' => 'nuevo@cliente.cl', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-TEST-01',
            'fecha_emision' => now()->format('Y-m-d'),
            'subtotal' => 20000,
            'monto_neto' => 20000,
            'monto_iva' => 3800,
            'monto_total' => 23800,
            'es_afecta' => true,
            'detalles' => [
                [
                    'producto_nombre' => 'Servicio A',
                    'cantidad' => 2,
                    'precio_unitario' => 5000,
                    'subtotal' => 10000
                ],
                [
                    'producto_nombre' => 'Servicio B',
                    'cantidad' => 1,
                    'precio_unitario' => 10000,
                    'subtotal' => 10000
                ]
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cotizaciones', [
            'numero_cotizacion' => 'COT-TEST-01',
            'monto_total' => 23800
        ]);

        $this->assertDatabaseCount('cotizacion_detalles', 2);
    }

    // PRUEBA DE LÓGICA DE NEGOCIO (PROVEEDORES)

    public function test_proveedor_genera_codigo_interno_automaticamente_al_crear()
    {
        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/proveedores', [
            'rut' => '99.999.999-9',
            'razon_social' => 'Distribuidora Central'
        ]);

        $response->assertStatus(201);

        $this->assertStringStartsWith('PROV-', $response->json('codigo_generado'));

        $this->assertDatabaseHas('proveedores', [
            'rut' => '99.999.999-9',
            'codigo_interno' => $response->json('codigo_generado')
        ]);
    }

    // PRUEBA DE INTEGRIDAD HISTÓRICA (Soft Delete)

    public function test_inactivar_cliente_cambia_su_estado_sin_eliminarlo_de_bd()
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresaA->id,
            'rut' => '44.444.444-4',
            'razon_social' => 'Cliente A Borrar',
            'estado' => 'ACTIVO'
        ]);

        $response = $this->actingAs($this->usuarioAdmin)->deleteJson("/api/clientes/{$cliente->id}");

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'estado' => 'INACTIVO'
        ]);
    }

    // PRUEBA MATEMÁTICA COMPLEJA (Cotizaciones)

    public function test_cotizacion_calcula_correctamente_descuentos_e_iva()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '55.555.555-5', 'razon_social' => 'Cliente Descuento']);

        $response = $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'porcentaje_descuento' => 10,
            'es_afecta' => true,
            'detalles' => [
                ['producto_nombre' => 'Consultoría', 'cantidad' => 1, 'precio_unitario' => 100000] // Subtotal = 100,000
            ]
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('cotizaciones', [
            'id' => $response->json('data.id'),
            'subtotal' => 100000,
            'monto_descuento' => 10000,
            'monto_neto' => 90000,
            'monto_iva' => 17100,
            'monto_total' => 107100
        ]);
    }

    // PRUEBA DE PREVENCIÓN DE FRAUDE (Facturas)

    public function test_evita_registrar_factura_de_compra_duplicada_para_el_mismo_proveedor()
    {
        Proveedor::unguard();
        $proveedor = Proveedor::create([
            'empresa_id' => $this->empresaA->id,
            'razon_social' => 'Proveedor Duplicado',
            'rut' => '88.888.888-8',
            'codigo_interno' => 'P-DUP',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP'
        ]);
        Proveedor::reguard();

        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresaA->id, 'codigo' => '410101'], ['nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresaA->id, 'codigo' => '353350'], ['nombre' => 'IVA', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresaA->id, 'codigo' => '352105'], ['nombre' => 'Proveedor', 'tipo' => 'PASIVO', 'imputable' => true, 'activo' => true]);

        $payload = [
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'F-DUP',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 10000,
            'monto_iva' => 1900,
            'monto_bruto' => 11900,
            'tipo_documento' => 'FACTURA',
            'cuentaDestino' => '410101',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ];

        $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', $payload)->assertStatus(201);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', $payload);
        $response->assertStatus(422)
            ->assertSee('ya se encuentra registrada para este proveedor');
    }

    // PRUEBA DE INTEGRACIÓN INTER-DOMINIOS (Comercial -> Contabilidad)

    public function test_registro_de_factura_compra_genera_asiento_contable_automaticamente()
    {
        Proveedor::unguard();
        $proveedor = Proveedor::create([
            'empresa_id' => $this->empresaA->id,
            'razon_social' => 'Proveedor Contable',
            'rut' => '99.999.999-9',
            'codigo_interno' => 'P-CONT',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP'
        ]);
        Proveedor::reguard();

        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresaA->id, 'codigo' => '410101'], ['nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresaA->id, 'codigo' => '353350'], ['nombre' => 'IVA', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresaA->id, 'codigo' => '352105'], ['nombre' => 'Proveedor', 'tipo' => 'PASIVO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'F-ASIENTO',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 50000,
            'monto_iva' => 9500,
            'monto_bruto' => 59500,
            'tipo_documento' => 'FACTURA',
            'cuentaDestino' => '410101',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ]);

        $response->assertStatus(201);
        $facturaId = $response->json('data.id');

        $this->assertDatabaseHas('asientos_contables', [
            'empresa_id' => $this->empresaA->id,
            'origen_modulo' => 'compras',
            'origen_id' => $facturaId
        ]);
    }

    // PRUEBA DE SEGURIDAD (IDOR) EN PROVEEDORES

    public function test_no_se_puede_ver_ficha_de_proveedor_de_otra_empresa()
    {
        $empresaB = Empresa::create(['rut' => '99.111.111-1', 'razon_social' => 'Otra SpA']);
        $provAjeno = Proveedor::create(['empresa_id' => $empresaB->id, 'codigo_interno' => 'PR-AJENO', 'rut' => '12.345.678-9', 'razon_social' => 'Prov Ajeno', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuarioAdmin)->getJson("/api/proveedores/{$provAjeno->id}/ficha");

        $response->assertStatus(404);
    }

    // PRUEBA DE LÓGICA DE NEGOCIO (Cotizaciones)

    public function test_rechaza_cotizacion_sin_detalles_o_productos()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '88.123.123-1', 'razon_social' => 'Cliente Fantasma', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-VACIA',
            'monto_neto' => 1000,
            'detalles' => []
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['detalles']);
    }

    // PRUEBA TRIBUTARIA DE LÍMITES (Facturas)

    public function test_rechaza_factura_con_monto_neto_cero_o_negativo()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-CERO', 'rut' => '11.111.111-1', 'razon_social' => 'Prov Cero', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-000',
            'tipo_documento' => 'COMPRA',
            'monto_neto' => 0,
            'monto_iva' => 0,
            'monto_bruto' => 0
        ]);

        $response->assertStatus(422)
            ->assertSee('mayor a 0');
    }

    // PRUEBA DE SEGURIDAD (IDOR) EN LECTURA DE FACTURAS

    public function test_idor_no_se_puede_ver_factura_de_otra_empresa()
    {
        $empresaB = Empresa::create(['rut' => '22.222.222-2', 'razon_social' => 'Empresa Hacker']);
        $hacker = User::create(['nombre' => 'Hacker', 'email' => 'hacker@b.cl', 'password' => bcrypt('1'), 'empresa_id' => $empresaB->id, 'rol_id' => 1, 'estado_suscripcion_id' => 1]);

        $prov = Proveedor::create(['empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-A', 'rut' => '33.333.333-3', 'razon_social' => 'Prov A', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $facturaA = Factura::create([
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-SECRETA',
            'codigo_unico' => 99999999,
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_bruto' => 119000,
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'tipo' => 'COMPRA'
        ]);

        $response = $this->actingAs($hacker)->getJson("/api/facturas/{$facturaA->id}");
        $response->assertStatus(404);
    }

    // PRUEBA DE INTEGRIDAD DE DATOS (Proveedores)

    public function test_rechaza_creacion_de_proveedor_con_rut_duplicado()
    {
        Proveedor::create([
            'empresa_id' => $this->empresaA->id,
            'codigo_interno' => 'PR-1',
            'rut' => '77.123.456-7',
            'razon_social' => 'Proveedor Original',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP'
        ]);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/proveedores', [
            'rut' => '77.123.456-7',
            'razon_social' => 'Proveedor Clon'
        ]);

        $response->assertStatus(422)
            ->assertSee('ya se encuentra registrado');
    }

    // PRUEBA DE TRANSACCIÓN DE BASE DE DATOS (ACID Rollback)

    public function test_factura_hace_rollback_si_falla_la_centralizacion()
    {
        Proveedor::unguard();
        $proveedor = Proveedor::create([
            'empresa_id' => $this->empresaA->id,
            'razon_social' => 'Proveedor Fallo',
            'rut' => '11.111.111-1',
            'codigo_interno' => 'P-FAIL',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP'
        ]);
        Proveedor::reguard();

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'F-ROLLBACK',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190,
            'tipo_documento' => 'FACTURA',
            'cuentaDestino' => '999999', // Fuerza el error de BD
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ]);

        $response->assertStatus(422)
            ->assertSee('Verifique que las cuentas');

        $this->assertDatabaseMissing('facturas', [
            'numero_factura' => 'F-ROLLBACK'
        ]);
    }

    // PRUEBA DE PREVENCIÓN DE DOBLE CLIC (Race Conditions)

    public function test_prevencion_doble_clic_bloquea_cotizacion_con_numero_duplicado()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '10.100.100-1', 'razon_social' => 'Cliente Fast', 'estado' => 'ACTIVO']);

        $payload = [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-DOBLE-CLICK',
            'fecha_emision' => now()->format('Y-m-d'),
            'subtotal' => 1000,
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_total' => 1190,
            'es_afecta' => true,
            'detalles' => [['producto_nombre' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000, 'subtotal' => 1000]]
        ];

        $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', $payload)->assertStatus(201);

        $response = $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', $payload);
        $response->assertStatus(422);
    }

    // PRUEBA DE SEGURIDAD (IDOR - Inyección de Llaves Foráneas)

    public function test_idor_rechaza_usar_proveedor_perteneciente_a_otra_empresa()
    {
        $empresaB = Empresa::create(['rut' => '33.888.888-8', 'razon_social' => 'Otra Empresa']);
        $provAjeno = Proveedor::create(['empresa_id' => $empresaB->id, 'codigo_interno' => 'PR-HACK', 'rut' => '44.444.444-4', 'razon_social' => 'Prov Ajeno', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'proveedor_id' => $provAjeno->id,
            'numero_factura' => 'F-HACK',
            'tipo_documento' => 'COMPRA',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 10000,
            'monto_iva' => 1900,
            'monto_bruto' => 11900
        ]);

        $response->assertStatus(422);
    }

    // PRUEBA: Clientes Fantasma

    public function test_rechaza_operar_con_clientes_inactivos()
    {
        $clienteInactivo = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '88.000.000-0', 'razon_social' => 'Cliente Muerto', 'estado' => 'INACTIVO']);

        $response = $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', [
            'cliente_id' => $clienteInactivo->id,
            'numero_cotizacion' => 'COT-ZOMBIE',
            'detalles' => [['producto_nombre' => 'Z', 'cantidad' => 1, 'precio_unitario' => 100, 'subtotal' => 100]]
        ]);

        $response->assertStatus(422);
    }

    // PRUEBA: Cantidades negativas

    public function test_rechaza_cantidades_y_precios_negativos_o_cero_en_los_detalles()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '99.555.555-5', 'razon_social' => 'Cliente Valido', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-NEGATIVA',
            'detalles' => [
                [
                    'producto_nombre' => 'Consultoría',
                    'cantidad' => -5,
                    'precio_unitario' => -10000,
                    'subtotal' => 50000
                ]
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['detalles.0.cantidad', 'detalles.0.precio_unitario']);
    }

    // PRUEBA: Proteccion de flujo contable

    public function test_rechaza_reclasificar_factura_que_no_ha_sido_centralizada()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-NULA', 'rut' => '33.123.123-3', 'razon_social' => 'Prov Nulo', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $facturaSinAsiento = Factura::create([
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-SIN-ASIENTO',
            'codigo_unico' => 1234567,
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_bruto' => 100,
            'monto_neto' => 100,
            'monto_iva' => 0,
            'tipo' => 'COMPRA',
            'comprobante_contable' => null
        ]);

        $response = $this->actingAs($this->usuarioAdmin)->postJson("/api/facturas/{$facturaSinAsiento->id}/reclasificar", [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'Intento de reclasificacion',
            'cambios' => ['352130' => '410101']
        ]);

        $response->assertStatus(400)
            ->assertSee('no tiene un asiento contable vinculado');
    }

    // PRUEBA UX: Busqueda y paginacion en historial

    public function test_ux_historial_permite_buscar_y_paginar_facturas()
    {
        $prov1 = Proveedor::create(['empresa_id' => $this->empresaA->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Apple Chile', 'codigo_interno' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $prov2 = Proveedor::create(['empresa_id' => $this->empresaA->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Microsoft Chile', 'codigo_interno' => 'P2', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        Factura::create(['empresa_id' => $this->empresaA->id, 'proveedor_id' => $prov1->id, 'numero_factura' => 'F-MAC', 'monto_bruto' => 100, 'monto_neto' => 84, 'monto_iva' => 16, 'tipo' => 'COMPRA', 'codigo_unico' => 101010, 'fecha_emision' => now()]);
        Factura::create(['empresa_id' => $this->empresaA->id, 'proveedor_id' => $prov2->id, 'numero_factura' => 'F-WIN', 'monto_bruto' => 100, 'monto_neto' => 84, 'monto_iva' => 16, 'tipo' => 'COMPRA', 'codigo_unico' => 202020, 'fecha_emision' => now()]);

        // Buscamos específicamente "Apple"
        $response = $this->actingAs($this->usuarioAdmin)->getJson('/api/facturas/historial?search=Apple');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('F-MAC', $response->json('data.0.numero_factura'));

        $response->assertJsonStructure(['data', 'pagination' => ['total', 'totalPages', 'page']]);
    }

    // PRUEBA UX: Validador Asincronico del Frontend

    public function test_ux_validador_asincrono_detecta_facturas_duplicadas_en_tiempo_real()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresaA->id, 'rut' => '3.3.3.3-3', 'razon_social' => 'Prov Test', 'codigo_interno' => 'P3', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        Factura::create(['empresa_id' => $this->empresaA->id, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-EXISTE', 'monto_bruto' => 100, 'monto_neto' => 100, 'monto_iva' => 0, 'tipo' => 'COMPRA', 'codigo_unico' => 303030, 'fecha_emision' => now()]);

        $response = $this->actingAs($this->vendedor)->getJson("/api/facturas/check?proveedorId={$prov->id}&numeroFactura=F-EXISTE");

        $response->assertStatus(200)
            ->assertJsonPath('exists', true);
    }

    // PRUEBA UX: Manejo de errores controlados

    public function test_ux_maneja_correctamente_la_solicitud_de_documentos_inexistentes()
    {
        $response = $this->actingAs($this->usuarioAdmin)->getJson('/api/facturas/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message']);
    }

    // PRUEBA UX: Trazabilidad y Auditoria

    public function test_ux_permite_visualizar_la_auditoria_completa_de_una_factura()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresaA->id, 'rut' => '4.4.4.4-4', 'razon_social' => 'Prov Audi', 'codigo_interno' => 'P4', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create(['empresa_id' => $this->empresaA->id, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-AUDI', 'monto_bruto' => 100, 'monto_neto' => 100, 'monto_iva' => 0, 'tipo' => 'COMPRA', 'codigo_unico' => 404040, 'fecha_emision' => now(), 'estado' => 'REGISTRADA']);

        $response = $this->actingAs($this->usuarioAdmin)->getJson("/api/facturas/{$factura->id}/auditoria");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'factura' => ['id', 'numero_factura', 'proveedor', 'estado'],
                    'historial'
                ]
            ]);
    }

    // PRUEBA UX: Eager Loading

    public function test_ux_listado_de_cotizaciones_carga_relaciones_para_evitar_n_mas_1()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '5.5.5.5-5', 'razon_social' => 'Cliente Coti', 'estado' => 'ACTIVO']);
        Cotizacion::create([
            'empresa_id' => $this->empresaA->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'estado_id' => 1,
            'numero_cotizacion' => 'COT-UX-01',
            'subtotal' => 1000,
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_total' => 1190,
            'total' => 1190,
            'fecha_emision' => now(),
            'fecha_validez' => now()
        ]);

        $response = $this->actingAs($this->vendedor)->getJson('/api/cotizaciones');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertArrayHasKey('cliente', $response->json('data.0'), 'Falta relación Cliente en la respuesta');
        $this->assertArrayHasKey('estado', $response->json('data.0'), 'Falta relación Estado en la respuesta');
    }

    // PRUEBA: Matemática Absurda

    public function test_capa8_rechaza_descuentos_mayores_al_100_por_ciento()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '1.2.3.4-5', 'razon_social' => 'Cliente Feliz', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'porcentaje_descuento' => 150,
            'detalles' => [['producto_nombre' => 'A', 'cantidad' => 1, 'precio_unitario' => 1000]]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['porcentaje_descuento']);
    }

    // PRUEBA: Fechas Inexistentes

    public function test_capa8_rechaza_fechas_con_formatos_basura_o_inexistentes()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-F', 'rut' => '2.2.2.2-2', 'razon_social' => 'Prov Fecha', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-FECHA',
            'tipo_documento' => 'COMPRA',
            'fecha_emision' => '31-febrero-2026',
            'monto_neto' => 100,
            'monto_iva' => 19,
            'monto_bruto' => 119
        ]);

        $response->assertStatus(422);
    }

    // PRUEBA: Strings en campos numéricos

    public function test_capa8_rechaza_letras_en_montos_financieros()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-NUM', 'rut' => '3.3.3.3-3', 'razon_social' => 'Prov Num', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-TEXTO',
            'tipo_documento' => 'COMPRA',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 'cien mil',
            'monto_iva' => 'diecinueve mil',
            'monto_bruto' => 'ciento diecinueve mil'
        ]);

        $response->assertStatus(422);
    }

    // PRUEBA: Campos solo con espacios en blanco

    public function test_capa8_rechaza_creacion_de_cliente_solo_con_espacios_en_blanco()
    {
        $response = $this->actingAs($this->vendedor)->postJson('/api/clientes', [
            'rut' => '   ',
            'razon_social' => '    '
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rut']);
    }

    // PRUEBA: Descarga IDOR

    public function test_capa8_bloquea_descarga_de_pdf_de_cotizacion_ajena()
    {
        $empresaB = Empresa::create(['rut' => '7.7.7.7-7', 'razon_social' => 'Empresa Hacker']);
        $hacker = User::create(['nombre' => 'Hacker', 'email' => 'h@h.cl', 'password' => bcrypt('1'), 'empresa_id' => $empresaB->id, 'rol_id' => 1, 'estado_suscripcion_id' => 1]);

        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Cliente Blindado', 'estado' => 'ACTIVO']);
        $cotiA = Cotizacion::create([
            'empresa_id' => $this->empresaA->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => 'Cliente Blindado',
            'estado_id' => 1,
            'numero_cotizacion' => 'COT-A',
            'subtotal' => 100,
            'monto_neto' => 100,
            'monto_iva' => 19,
            'monto_total' => 119,
            'total' => 119,
            'fecha_emision' => now(),
            'fecha_validez' => now()
        ]);

        $response = $this->actingAs($hacker)->getJson("/api/cotizaciones/{$cotiA->id}/pdf");

        $this->assertNotEquals(200, $response->getStatusCode());
    }

    // PRUEBA: Manipulación de Totales

    public function test_capa8_ignora_totales_falsos_del_frontend_y_recalcula_todo()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Hack Corp', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'numero_cotizacion' => 'COT-HACK',
            'subtotal' => 1,
            'monto_neto' => 1,
            'monto_iva' => 0,
            'monto_total' => 1,
            'detalles' => [
                ['producto_nombre' => 'Servidor Premium', 'cantidad' => 2, 'precio_unitario' => 500000]
            ]
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('cotizaciones', [
            'numero_cotizacion' => 'COT-HACK',
            'subtotal' => 1000000,
            'monto_total' => 1190000
        ]);
    }

    // PRUEBA: Fechas Incoherentes

    public function test_capa8_rechaza_fechas_de_validez_anteriores_a_la_emision()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresaA->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Time Traveler', 'estado' => 'ACTIVO']);

        $response = $this->actingAs($this->vendedor)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'fecha_emision' => '2026-05-10',
            'fecha_validez' => '2026-05-01',
            'detalles' => [['producto_nombre' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000]]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_validez']);
    }

    // PRUEBA: Exceso de Caracteres

    public function test_capa8_rechaza_textos_excesivamente_largos_que_romperian_la_bd()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-LONG', 'rut' => '3.3.3.3-3', 'razon_social' => 'Prov Largo', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => str_repeat('A', 300),
            'tipo_documento' => 'COMPRA',
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['numero_factura']);
    }

    // PRUEBA: Tipos de Documento Falsos

    public function test_capa8_rechaza_tipos_de_documento_inventados()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-TIPO', 'rut' => '4.4.4.4-4', 'razon_social' => 'Prov Tipo', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuarioAdmin)->postJson('/api/facturas', [
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-TIPO',
            'tipo_documento' => 'CONTRABANDO',
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_documento']);
    }

    // PRUEBA: Vulnerabilidad PHP

    public function test_capa8_rechaza_arrays_donde_se_esperan_textos()
    {
        $response = $this->actingAs($this->vendedor)->postJson('/api/clientes', [
            'rut' => '11.111.111-1',
            'razon_social' => ['Soy un Array', 'Malicioso'],
            'email' => 'array@hack.cl'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['razon_social']);
    }
}