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
use App\Domains\Contabilidad\Models\PlanCuenta;

class ComercialNotasDebitoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected Empresa $empresa;
    protected User $usuario;
    protected Proveedor $prov;
    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Notas Débito SpA']);
        $this->usuario = User::create([
            'nombre'                  => 'Admin ND',
            'email'                   => 'nd@nd.cl',
            'password'                => bcrypt('123'),
            'empresa_id'              => $this->empresa->id,
            'rol_id'                  => $this->rolSuperAdmin->id,
            'estado_suscripcion_id'   => $this->estadoSuscripcionActiva->id,
        ]);
        $this->prov = Proveedor::create([
            'empresa_id'      => $this->empresa->id,
            'rut'             => '2.2.2.2-2',
            'razon_social'    => 'Prov ND',
            'codigo_interno'  => 'P2',
            'pais_iso'        => 'CL',
            'moneda_defecto'  => 'CLP',
        ]);
        $this->cliente = Cliente::create([
            'empresa_id'   => $this->empresa->id,
            'rut'          => '6.6.6.6-6',
            'razon_social' => 'Cliente ND',
            'estado'       => 'ACTIVO',
        ]);

        foreach ([
            ['410101', 'Gasto Genérico',       'GASTO'],
            ['353350', 'IVA Crédito Fiscal',    'ACTIVO'],
            ['353360', 'IVA Débito Fiscal',     'PASIVO'],
            ['352105', 'Proveedores',            'PASIVO'],
            ['152005', 'Clientes CxC',           'ACTIVO'],
            ['501105', 'Ventas',                 'INGRESO'],
        ] as [$cod, $nom, $tipo]) {
            PlanCuenta::create([
                'empresa_id' => $this->empresa->id,
                'codigo'     => $cod,
                'nombre'     => $nom,
                'tipo'       => $tipo,
                'imputable'  => true,
                'activo'     => true,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function crearFacturaVenta(string $numero, float $neto, float $iva, float $bruto, string $estado = 'REGISTRADA', int $codigoUnico = 100): Factura
    {
        return Factura::create([
            'empresa_id'     => $this->empresa->id,
            'cliente_id'     => $this->cliente->id,
            'numero_factura' => $numero,
            'tipo_documento' => 'FACTURA',
            'tipo'           => 'VENTA',
            'tipo_dte'       => 33,
            'monto_bruto'    => $bruto,
            'monto_neto'     => $neto,
            'monto_iva'      => $iva,
            'fecha_emision'  => now(),
            'estado'         => $estado,
            'codigo_unico'   => $codigoUnico,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_nd_venta_crea_documento_tipo_nota_debito(): void
    {
        $facturaVenta = $this->crearFacturaVenta('FV-ND-001', 1000, 190, 1190, 'REGISTRADA', 200);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaVenta->id}/nota-debito", [
            'numero_nd'   => 'ND-VTA-001',
            'monto_neto'  => 500,
            'monto_iva'   => 95,
            'monto_bruto' => 595,
            'razon'       => 'Cargo adicional por flete no incluido',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('facturas', [
            'empresa_id'            => $this->empresa->id,
            'numero_factura'        => 'ND-VTA-001',
            'tipo_documento'        => 'NOTA_DEBITO',
            'tipo_dte'              => 56,
            'tipo'                  => 'VENTA',
            'factura_referencia_id' => $facturaVenta->id,
        ]);
    }

    public function test_nd_venta_crea_asiento_contable(): void
    {
        $facturaVenta = $this->crearFacturaVenta('FV-ND-002', 2000, 380, 2380, 'REGISTRADA', 201);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaVenta->id}/nota-debito", [
            'numero_nd'   => 'ND-VTA-002',
            'monto_neto'  => 1000,
            'monto_iva'   => 190,
            'monto_bruto' => 1190,
            'razon'       => 'Diferencia de precio pactado en contrato',
        ]);

        $response->assertStatus(201);

        $nd = Factura::where('empresa_id', $this->empresa->id)
            ->where('numero_factura', 'ND-VTA-002')
            ->first();

        $this->assertNotNull($nd, 'La ND no fue creada en la base de datos.');
        $this->assertNotNull($nd->comprobante_contable, 'La ND no tiene asiento contable vinculado.');
    }

    public function test_nd_venta_no_permite_sobre_factura_anulada(): void
    {
        $facturaAnulada = $this->crearFacturaVenta('FV-ND-ANULADA', 1000, 0, 1000, 'ANULADA', 202);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaAnulada->id}/nota-debito", [
            'numero_nd'   => 'ND-ERR-001',
            'monto_neto'  => 500,
            'monto_iva'   => 0,
            'monto_bruto' => 500,
            'razon'       => 'Intento sobre factura anulada',
        ]);

        $response->assertStatus(422);
    }

    public function test_nd_venta_requiere_permisos(): void
    {
        $facturaVenta = $this->crearFacturaVenta('FV-ND-AUTH', 1000, 190, 1190, 'REGISTRADA', 203);

        // Sin sesión activa la API responde 401 (no autenticado)
        $response = $this->postJson("/api/facturas/{$facturaVenta->id}/nota-debito", [
            'numero_nd'   => 'ND-SIN-AUTH',
            'monto_neto'  => 500,
            'monto_iva'   => 95,
            'monto_bruto' => 595,
            'razon'       => 'Sin autenticación intento ND',
        ]);

        $response->assertStatus(401);
    }

    public function test_nd_no_afecta_estado_factura_origen(): void
    {
        $facturaVenta = $this->crearFacturaVenta('FV-ND-003', 3000, 570, 3570, 'REGISTRADA', 204);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaVenta->id}/nota-debito", [
            'numero_nd'   => 'ND-VTA-003',
            'monto_neto'  => 1000,
            'monto_iva'   => 190,
            'monto_bruto' => 1190,
            'razon'       => 'Cargo adicional por servicio postventa',
        ]);

        $response->assertStatus(201);

        // La ND no debe cambiar el estado de la factura de origen
        $this->assertDatabaseHas('facturas', [
            'id'     => $facturaVenta->id,
            'estado' => 'REGISTRADA',
        ]);
    }

    public function test_nd_venta_rechaza_monto_neto_cero(): void
    {
        $facturaVenta = $this->crearFacturaVenta('FV-ND-004', 1000, 190, 1190, 'REGISTRADA', 205);

        // Antes del fix: monto_neto=0 pasaba la validacion HTTP (min:0, no gt:0)
        // y no habia ningun guard en el servicio.
        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaVenta->id}/nota-debito", [
            'numero_nd'   => 'ND-CERO',
            'monto_neto'  => 0,
            'monto_iva'   => 0,
            'monto_bruto' => 1,
            'razon'       => 'Intento de ND con monto neto cero',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('facturas', ['numero_factura' => 'ND-CERO']);
    }
}
