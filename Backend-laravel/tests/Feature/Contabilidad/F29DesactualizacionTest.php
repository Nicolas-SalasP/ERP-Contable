<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Services\ImpuestosService;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Services\FacturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/** Regresión: si se anula una factura de un mes cuyo F29 ya fue centralizado, el estado del F29 debe reportarlo como desactualizado. */
class F29DesactualizacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '11.222.333-4',
            'razon_social' => 'Prov F29 Drift',
            'codigo_interno' => 'PF29D',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        foreach ([
            ['152540', 'IVA Credito Fiscal', 'ACTIVO'],
            ['353360', 'IVA Debito Fiscal', 'PASIVO'],
            ['152542', 'Remanente IVA F29', 'ACTIVO'],
        ] as [$codigo, $nombre, $tipo]) {
            PlanCuenta::create([
                'empresa_id' => $this->empresa->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'imputable' => true,
                'activo' => true,
            ]);
        }
    }

    public function test_anular_factura_de_mes_ya_centralizado_marca_f29_desactualizado(): void
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'F29D-1',
            'tipo' => 'COMPRA',
            'codigo_unico' => 70000030,
            'fecha_emision' => '2026-05-10',
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $service = app(ImpuestosService::class);

        // Ejecuta el F29 de mayo/2026: queda MAYORIZADO.
        $service->ejecutarF29($this->empresa->id, $this->usuario->id, 5, 2026);

        $estadoAntes = $service->simularF29($this->empresa->id, 5, 2026);
        $this->assertTrue($estadoAntes['ya_cerrado']);
        $this->assertFalse($estadoAntes['desactualizado']);

        // Anula la factura de compra que ya formó parte del F29 centralizado.
        app(FacturaService::class)->anularFactura(
            $this->empresa->id,
            $this->usuario->id,
            $factura->id,
            'Error en monto facturado'
        );

        $estadoDespues = $service->simularF29($this->empresa->id, 5, 2026);
        $this->assertTrue($estadoDespues['desactualizado']);
        $this->assertStringContainsString('F29D-1', $estadoDespues['motivo_desactualizacion']);
    }

    public function test_anular_factura_de_mes_sin_f29_centralizado_no_marca_nada(): void
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'F29D-2',
            'tipo' => 'COMPRA',
            'codigo_unico' => 70000031,
            'fecha_emision' => '2026-06-10',
            'monto_neto' => 50000,
            'monto_iva' => 9500,
            'monto_bruto' => 59500,
            'estado' => 'REGISTRADA',
        ]);

        app(FacturaService::class)->anularFactura(
            $this->empresa->id,
            $this->usuario->id,
            $factura->id,
            'Duplicada'
        );

        $estado = app(ImpuestosService::class)->simularF29($this->empresa->id, 6, 2026);
        $this->assertFalse($estado['desactualizado']);
        $this->assertNull($estado['motivo_desactualizacion']);
    }
}
