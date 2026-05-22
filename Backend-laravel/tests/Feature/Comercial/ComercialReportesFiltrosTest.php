<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use Carbon\Carbon;

class ComercialReportesFiltrosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $prov;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Reportes SpA']);
        $this->usuario = User::create(['nombre' => 'Gerente', 'email' => 'g@r.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
        $this->prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Prov X', 'codigo_interno' => 'PX', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
    }

    public function test_filtro_por_rango_de_fechas_trae_solo_facturas_del_periodo()
    {
        // Factura de Enero
        $f1 = new Factura();
        $f1->empresa_id = $this->empresa->id;
        $f1->proveedor_id = $this->prov->id;
        $f1->numero_factura = 'F-ENERO';
        $f1->monto_bruto = 100;
        $f1->monto_neto = 100;
        $f1->monto_iva = 0;
        $f1->tipo = 'COMPRA';
        $f1->codigo_unico = 1;
        $f1->fecha_emision = Carbon::create(2026, 1, 15);
        $f1->save();
        // Factura de Marzo
        $f2 = new Factura();
        $f2->empresa_id = $this->empresa->id;
        $f2->proveedor_id = $this->prov->id;
        $f2->numero_factura = 'F-MARZO';
        $f2->monto_bruto = 100;
        $f2->monto_neto = 100;
        $f2->monto_iva = 0;
        $f2->tipo = 'COMPRA';
        $f2->codigo_unico = 2;
        $f2->fecha_emision = Carbon::create(2026, 3, 10);
        $f2->save();

        // Simulamos filtro del frontend
        $response = $this->actingAs($this->usuario)->getJson('/api/facturas/historial?fecha_desde=2026-03-01&fecha_hasta=2026-03-31');

        $response->assertStatus(200);
        $response->assertJsonFragment(['numero_factura' => 'F-MARZO']);
        $response->assertJsonMissing(['numero_factura' => 'F-ENERO']);
    }

    public function test_dashboard_identifica_correctamente_facturas_vencidas_y_no_pagadas()
    {
        $fVencida = new Factura();
        $fVencida->empresa_id = $this->empresa->id;
        $fVencida->proveedor_id = $this->prov->id;
        $fVencida->numero_factura = 'F-VENCIDA';
        $fVencida->monto_bruto = 100;
        $fVencida->monto_neto = 100;
        $fVencida->monto_iva = 0;
        $fVencida->tipo = 'COMPRA';
        $fVencida->codigo_unico = 3;
        $fVencida->fecha_emision = now()->subDays(40);
        $fVencida->fecha_vencimiento = now()->subDays(10);
        $fVencida->estado = 'REGISTRADA';
        $fVencida->save();

        $fPagada = new Factura();
        $fPagada->empresa_id = $this->empresa->id;
        $fPagada->proveedor_id = $this->prov->id;
        $fPagada->numero_factura = 'F-PAGADA';
        $fPagada->monto_bruto = 100;
        $fPagada->monto_neto = 100;
        $fPagada->monto_iva = 0;
        $fPagada->tipo = 'COMPRA';
        $fPagada->codigo_unico = 4;
        $fPagada->fecha_emision = now()->subDays(40);
        $fPagada->fecha_vencimiento = now()->subDays(10);
        $fPagada->estado = 'PAGADA';
        $fPagada->save();

        $fAlDia = new Factura();
        $fAlDia->empresa_id = $this->empresa->id;
        $fAlDia->proveedor_id = $this->prov->id;
        $fAlDia->numero_factura = 'F-ALDIA';
        $fAlDia->monto_bruto = 100;
        $fAlDia->monto_neto = 100;
        $fAlDia->monto_iva = 0;
        $fAlDia->tipo = 'COMPRA';
        $fAlDia->codigo_unico = 5;
        $fAlDia->fecha_emision = now()->subDays(5);
        $fAlDia->fecha_vencimiento = now()->addDays(10);
        $fAlDia->estado = 'REGISTRADA';
        $fAlDia->save();

        $response = $this->actingAs($this->usuario)->getJson('/api/facturas/vencidas');

        $response->assertStatus(200);
        $response->assertJsonFragment(['numero_factura' => 'F-VENCIDA']);
        $response->assertJsonMissing(['numero_factura' => 'F-PAGADA']);
        $response->assertJsonMissing(['numero_factura' => 'F-ALDIA']);
    }

    public function test_exportar_listado_de_facturas_a_excel_retorna_archivo_valido_csv()
    {
        $f = new Factura();
        $f->empresa_id = $this->empresa->id;
        $f->proveedor_id = $this->prov->id;
        $f->numero_factura = 'F-EXPORT';
        $f->monto_bruto = 100;
        $f->monto_neto = 100;
        $f->monto_iva = 0;
        $f->tipo = 'COMPRA';
        $f->codigo_unico = 999;
        $f->fecha_emision = now();
        $f->estado = 'REGISTRADA';
        $f->save();

        $response = $this->actingAs($this->usuario)->get('/api/facturas/exportar/excel');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('F-EXPORT', $response->getContent());
        $this->assertStringContainsString('Numero Factura', $response->getContent());
    }
}