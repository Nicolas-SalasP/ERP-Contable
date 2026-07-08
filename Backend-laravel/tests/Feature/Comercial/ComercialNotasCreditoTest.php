<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\AnticipoCliente;
use App\Domains\Comercial\Services\AnticipoClienteService;
use App\Domains\Contabilidad\Models\PlanCuenta;

class ComercialNotasCreditoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected Empresa $empresa;
    protected User $usuario;
    protected Proveedor $prov;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Notas SpA']);
        $this->usuario = User::create([
            'nombre' => 'Admin',
            'email' => 'nc@nc.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
        $this->prov = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '1.1.1.1-1',
            'razon_social' => 'Prov NC',
            'codigo_interno' => 'P1',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        foreach ([
            ['410101', 'Gasto Genérico', 'GASTO'],
            ['353350', 'IVA Crédito Fiscal', 'ACTIVO'],
            ['353360', 'IVA Débito Fiscal', 'PASIVO'],
            ['352105', 'Proveedores', 'PASIVO'],
            ['152005', 'Clientes CxC', 'ACTIVO'],
            ['501105', 'Ventas', 'INGRESO'],
        ] as [$cod, $nom, $tipo]) {
            PlanCuenta::create([
                'empresa_id' => $this->empresa->id,
                'codigo' => $cod,
                'nombre' => $nom,
                'tipo' => $tipo,
                'imputable' => true,
                'activo' => true,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // NC de COMPRA (vía POST /facturas)
    // -------------------------------------------------------------------------

    public function test_crear_nota_de_credito_compra_asociada_a_factura_responde_correctamente(): void
    {
        $factura = Factura::create([
            'empresa_id'      => $this->empresa->id,
            'proveedor_id'    => $this->prov->id,
            'numero_factura'  => 'F-ORIGINAL',
            'tipo_documento'  => 'FACTURA',
            'tipo'            => 'COMPRA',
            'monto_bruto'     => 1190,
            'monto_neto'      => 1000,
            'monto_iva'       => 190,
            'fecha_emision'   => now(),
            'estado'          => 'REGISTRADA',
            'codigo_unico'    => 1,
        ]);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id'           => $this->prov->id,
            'numero_factura'         => 'NC-001',
            'tipo_documento'         => 'NOTA_CREDITO',
            'factura_referencia_id'  => $factura->id,
            'fecha_emision'          => now()->format('Y-m-d'),
            'monto_neto'             => 500,
            'monto_iva'              => 95,
            'monto_bruto'            => 595,
            'cuentaDestino'          => '410101',
            'cuentaIva'              => '353350',
            'cuentaProveedor'        => '352105',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('facturas', [
            'empresa_id'             => $this->empresa->id,
            'numero_factura'         => 'NC-001',
            'tipo_documento'         => 'NOTA_CREDITO',
            'factura_referencia_id'  => $factura->id,
        ]);
    }

    public function test_rechaza_nc_compra_mayor_al_monto_de_la_factura_original(): void
    {
        $factura = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'proveedor_id'   => $this->prov->id,
            'numero_factura' => 'F-ORIGINAL2',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'COMPRA',
            'monto_bruto'    => 1000,
            'monto_neto'     => 1000,
            'monto_iva'      => 0,
            'fecha_emision'  => now(),
            'estado'         => 'REGISTRADA',
            'codigo_unico'   => 2,
        ]);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id'          => $this->prov->id,
            'numero_factura'        => 'NC-FRAUDE',
            'tipo_documento'        => 'NOTA_CREDITO',
            'factura_referencia_id' => $factura->id,
            'fecha_emision'         => now()->format('Y-m-d'),
            'monto_neto'            => 2000,
            'monto_iva'             => 0,
            'monto_bruto'           => 2000,
            'cuentaDestino'         => '410101',
            'cuentaIva'             => '353350',
            'cuentaProveedor'       => '352105',
        ]);

        $this->assertNotEquals(201, $response->getStatusCode());
    }

    public function test_rechaza_segunda_nc_compra_activa_sobre_la_misma_factura(): void
    {
        // Regresión (auditoría 2026-07-07, crítico): antes no existía ningún chequeo de NC
        // duplicada del lado compra (a diferencia de venta) -- se podía sobre-acreditar una
        // factura registrando varias NC sin límite.
        $factura = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'proveedor_id'   => $this->prov->id,
            'numero_factura' => 'F-DUP',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'COMPRA',
            'monto_bruto'    => 100000,
            'monto_neto'     => 100000,
            'monto_iva'      => 0,
            'fecha_emision'  => now(),
            'estado'         => 'REGISTRADA',
            'codigo_unico'   => 20,
        ]);

        $datosNc = [
            'proveedor_id'          => $this->prov->id,
            'fecha_emision'         => now()->format('Y-m-d'),
            'factura_referencia_id' => $factura->id,
            'tipo_documento'        => 'NOTA_CREDITO',
            'monto_neto'            => 80000,
            'monto_iva'             => 0,
            'monto_bruto'           => 80000,
            'cuentaDestino'         => '410101',
            'cuentaIva'             => '353350',
            'cuentaProveedor'       => '352105',
        ];

        $this->actingAs($this->usuario)->postJson('/api/facturas', array_merge($datosNc, ['numero_factura' => 'NC-DUP-1']))
            ->assertStatus(201);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', array_merge($datosNc, ['numero_factura' => 'NC-DUP-2']));

        $this->assertNotEquals(201, $response->getStatusCode());
        $this->assertDatabaseMissing('facturas', ['numero_factura' => 'NC-DUP-2']);
    }

    public function test_nc_compra_que_cubre_el_100_por_ciento_anula_la_factura_origen(): void
    {
        // Regresión: antes la factura de compra origen nunca se marcaba ANULADA aunque la NC
        // cubriera el 100% del monto -- quedaba REGISTRADA para siempre sin saldo real.
        $factura = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'proveedor_id'   => $this->prov->id,
            'numero_factura' => 'F-TOTAL',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'COMPRA',
            'monto_bruto'    => 50000,
            'monto_neto'     => 50000,
            'monto_iva'      => 0,
            'fecha_emision'  => now(),
            'estado'         => 'REGISTRADA',
            'codigo_unico'   => 21,
        ]);

        $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id'          => $this->prov->id,
            'numero_factura'        => 'NC-TOTAL',
            'tipo_documento'        => 'NOTA_CREDITO',
            'factura_referencia_id' => $factura->id,
            'fecha_emision'         => now()->format('Y-m-d'),
            'monto_neto'            => 50000,
            'monto_iva'             => 0,
            'monto_bruto'           => 50000,
            'cuentaDestino'         => '410101',
            'cuentaIva'             => '353350',
            'cuentaProveedor'       => '352105',
        ])->assertStatus(201);

        $this->assertDatabaseHas('facturas', [
            'id'     => $factura->id,
            'estado' => 'ANULADA',
        ]);
    }

    public function test_rechaza_nc_compra_sobre_factura_ya_anulada(): void
    {
        $facturaAnulada = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'proveedor_id'   => $this->prov->id,
            'numero_factura' => 'F-YA-ANULADA',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'COMPRA',
            'monto_bruto'    => 10000,
            'monto_neto'     => 10000,
            'monto_iva'      => 0,
            'fecha_emision'  => now(),
            'estado'         => 'ANULADA',
            'codigo_unico'   => 22,
        ]);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'proveedor_id'          => $this->prov->id,
            'numero_factura'        => 'NC-SOBRE-ANULADA',
            'tipo_documento'        => 'NOTA_CREDITO',
            'factura_referencia_id' => $facturaAnulada->id,
            'fecha_emision'         => now()->format('Y-m-d'),
            'monto_neto'            => 5000,
            'monto_iva'             => 0,
            'monto_bruto'           => 5000,
            'cuentaDestino'         => '410101',
            'cuentaIva'             => '353350',
            'cuentaProveedor'       => '352105',
        ]);

        $this->assertNotEquals(201, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // NC de VENTA (vía POST /facturas/{id}/nota-credito)
    // -------------------------------------------------------------------------

    public function test_emitir_nc_venta_revierte_asiento_y_crea_factura_nc(): void
    {
        $cliente = Cliente::create([
            'empresa_id'   => $this->empresa->id,
            'rut'          => '9.9.9.9-9',
            'razon_social' => 'Cliente Venta',
            'estado'       => 'ACTIVO',
        ]);

        $facturaVenta = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'cliente_id'     => $cliente->id,
            'numero_factura' => 'FV-001',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'VENTA',
            'tipo_dte'       => 33,
            'monto_bruto'    => 1190,
            'monto_neto'     => 1000,
            'monto_iva'      => 190,
            'fecha_emision'  => now(),
            'estado'         => 'REGISTRADA',
            'codigo_unico'   => 10,
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaVenta->id}/nota-credito", [
            'numero_nc'   => 'NC-VTA-001',
            'monto_neto'  => 1000,
            'monto_iva'   => 190,
            'monto_bruto' => 1190,
            'razon'       => 'Anulación total por error en precio',
        ]);

        $response->assertStatus(201);

        // NC creada correctamente
        $this->assertDatabaseHas('facturas', [
            'empresa_id'            => $this->empresa->id,
            'numero_factura'        => 'NC-VTA-001',
            'tipo_documento'        => 'NOTA_CREDITO',
            'tipo_dte'              => 61,
            'tipo'                  => 'VENTA',
            'factura_referencia_id' => $facturaVenta->id,
        ]);

        // Factura original anulada (NC cubre el 100%)
        $this->assertDatabaseHas('facturas', [
            'id'     => $facturaVenta->id,
            'estado' => 'ANULADA',
        ]);
    }

    public function test_nc_venta_parcial_no_anula_factura_original(): void
    {
        $cliente = Cliente::create([
            'empresa_id'   => $this->empresa->id,
            'rut'          => '8.8.8.8-8',
            'razon_social' => 'Cliente Parcial',
            'estado'       => 'ACTIVO',
        ]);

        $facturaVenta = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'cliente_id'     => $cliente->id,
            'numero_factura' => 'FV-002',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'VENTA',
            'monto_bruto'    => 1190,
            'monto_neto'     => 1000,
            'monto_iva'      => 190,
            'fecha_emision'  => now(),
            'estado'         => 'REGISTRADA',
            'codigo_unico'   => 11,
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaVenta->id}/nota-credito", [
            'numero_nc'   => 'NC-VTA-PARCIAL',
            'monto_neto'  => 500,
            'monto_iva'   => 95,
            'monto_bruto' => 595,
            'razon'       => 'Corrección parcial de ítem',
        ]);

        $response->assertStatus(201);

        // Factura original NO anulada (NC parcial)
        $this->assertDatabaseHas('facturas', [
            'id'     => $facturaVenta->id,
            'estado' => 'REGISTRADA',
        ]);
    }

    public function test_nc_venta_rechaza_monto_mayor_al_original(): void
    {
        $cliente = Cliente::create([
            'empresa_id'   => $this->empresa->id,
            'rut'          => '7.7.7.7-7',
            'razon_social' => 'Cliente Fraude',
            'estado'       => 'ACTIVO',
        ]);

        $facturaVenta = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'cliente_id'     => $cliente->id,
            'numero_factura' => 'FV-003',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'VENTA',
            'monto_bruto'    => 1000,
            'monto_neto'     => 1000,
            'monto_iva'      => 0,
            'fecha_emision'  => now(),
            'estado'         => 'REGISTRADA',
            'codigo_unico'   => 12,
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaVenta->id}/nota-credito", [
            'numero_nc'   => 'NC-FRAUDE-VTA',
            'monto_neto'  => 5000,
            'monto_iva'   => 0,
            'monto_bruto' => 5000,
            'razon'       => 'Intento de fraude',
        ]);

        $response->assertStatus(422);
    }

    public function test_nc_venta_rechaza_factura_ya_anulada(): void
    {
        $facturaAnulada = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'numero_factura' => 'FV-ANULADA',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'VENTA',
            'monto_bruto'    => 1000,
            'monto_neto'     => 1000,
            'monto_iva'      => 0,
            'fecha_emision'  => now(),
            'estado'         => 'ANULADA',
            'codigo_unico'   => 13,
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaAnulada->id}/nota-credito", [
            'numero_nc'   => 'NC-VTA-ERR',
            'monto_neto'  => 500,
            'monto_iva'   => 0,
            'monto_bruto' => 500,
            'razon'       => 'Error en factura anulada',
        ]);

        $response->assertStatus(422);
    }

    public function test_nc_venta_rechaza_monto_neto_cero(): void
    {
        $cliente = Cliente::create([
            'empresa_id'   => $this->empresa->id,
            'rut'          => '6.6.6.6-6',
            'razon_social' => 'Cliente Monto Cero',
            'estado'       => 'ACTIVO',
        ]);

        $facturaVenta = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'cliente_id'     => $cliente->id,
            'numero_factura' => 'FV-004',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'VENTA',
            'monto_bruto'    => 1190,
            'monto_neto'     => 1000,
            'monto_iva'      => 190,
            'fecha_emision'  => now(),
            'estado'         => 'REGISTRADA',
            'codigo_unico'   => 14,
        ]);

        // Antes del fix: monto_neto=0 pasaba la validacion HTTP (min:0, no gt:0)
        // y no habia ningun guard en el servicio -- generaba una NC de $0 que
        // igual creaba un asiento contable.
        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaVenta->id}/nota-credito", [
            'numero_nc'   => 'NC-CERO',
            'monto_neto'  => 0,
            'monto_iva'   => 0,
            'monto_bruto' => 1,
            'razon'       => 'Intento de NC con monto neto cero',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('facturas', ['numero_factura' => 'NC-CERO']);
    }

    public function test_nc_venta_100_por_ciento_libera_anticipo_y_revierte_cotizacion(): void
    {
        // Regresión (auditoría, hallazgo ALTO): a diferencia de anularFactura(), cuando la NC de venta
        // cubría el 100% del monto original, el saldo del anticipo de cliente aplicado quedaba
        // consumido para siempre (dinero atrapado) y la cotización de origen quedaba "Facturada"
        // fantasma sin poder refacturarse ni editarse.
        $estadoAceptada  = EstadoCotizacion::create(['nombre' => 'Aceptada']);
        $estadoFacturada = EstadoCotizacion::create(['nombre' => 'Facturada']);

        $cliente = Cliente::create([
            'empresa_id'   => $this->empresa->id,
            'rut'          => '5.5.5.5-5',
            'razon_social' => 'Cliente Anticipo NC',
            'estado'       => 'ACTIVO',
        ]);

        $cotizacion = Cotizacion::create([
            'empresa_id'         => $this->empresa->id,
            'cliente_id'         => $cliente->id,
            'nombre_cliente'     => $cliente->razon_social,
            'numero_cotizacion'  => 'COT-NC-01',
            'fecha_emision'      => now()->format('Y-m-d'),
            'fecha_validez'      => now()->addDays(30)->format('Y-m-d'),
            'validez'            => 30,
            'subtotal'           => 1000,
            'porcentaje_descuento' => 0,
            'monto_descuento'    => 0,
            'monto_neto'         => 1000,
            'porcentaje_iva'     => 19,
            'monto_iva'          => 190,
            'monto_total'        => 1190,
            'total'              => 1190,
            'estado_id'          => $estadoFacturada->id,
            'es_afecta'          => true,
        ]);

        $facturaVenta = Factura::create([
            'empresa_id'     => $this->empresa->id,
            'cliente_id'     => $cliente->id,
            'cotizacion_id'  => $cotizacion->id,
            'numero_factura' => 'FV-ANTICIPO-NC',
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'VENTA',
            'tipo_dte'       => 33,
            'monto_bruto'    => 1190,
            'monto_neto'     => 1000,
            'monto_iva'      => 190,
            'fecha_emision'  => now(),
            'estado'         => 'REGISTRADA',
            'codigo_unico'   => 30,
        ]);

        $anticipo = AnticipoCliente::create([
            'empresa_id'       => $this->empresa->id,
            'cliente_id'       => $cliente->id,
            'monto'            => 1190,
            'monto_original'   => 1190,
            'saldo_disponible' => 1190,
            'estado'           => 'DISPONIBLE',
        ]);

        app(AnticipoClienteService::class)->aplicarAFactura(
            $this->empresa->id,
            $anticipo->id,
            $facturaVenta->id,
            1190
        );

        // Confirma que el anticipo quedó consumido antes de emitir la NC.
        $this->assertSame('APLICADO', $anticipo->fresh()->estado);
        $this->assertEquals(0, (float) $anticipo->fresh()->saldo_disponible);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaVenta->id}/nota-credito", [
            'numero_nc'   => 'NC-VTA-ANTICIPO',
            'monto_neto'  => 1000,
            'monto_iva'   => 190,
            'monto_bruto' => 1190,
            'razon'       => 'Anulación total, cliente pagó con anticipo',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('facturas', [
            'id'     => $facturaVenta->id,
            'estado' => 'ANULADA',
        ]);

        // El anticipo queda liberado con su saldo completo disponible de nuevo.
        $anticipo->refresh();
        $this->assertSame('DISPONIBLE', $anticipo->estado);
        $this->assertEquals(1190.0, (float) $anticipo->saldo_disponible);

        // La cotización vuelve a 'Aceptada' en vez de quedar 'Facturada' fantasma.
        $this->assertDatabaseHas('cotizaciones', [
            'id'        => $cotizacion->id,
            'estado_id' => $estadoAceptada->id,
        ]);
    }
}
