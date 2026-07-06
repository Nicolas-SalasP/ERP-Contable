<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;

class DocumentoAdjuntoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $factura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Adjuntos SpA']);
        $this->usuario = User::create(['nombre' => 'Admin', 'email' => 'admin@adj.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Prov', 'codigo_interno' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $this->factura = Factura::create(['empresa_id' => $this->empresa->id, 'proveedor_id' => $proveedor->id, 'numero_factura' => 'F-1', 'codigo_unico' => 123456, 'fecha_emision' => now(), 'monto_bruto' => 100, 'monto_neto' => 100, 'monto_iva' => 0, 'tipo' => 'COMPRA']);

        Storage::fake('local');
    }

    public function test_sube_varios_documentos_en_una_sola_llamada()
    {
        $pdf = UploadedFile::fake()->create('guia.pdf', 200, 'application/pdf');
        $foto = UploadedFile::fake()->image('foto.jpg', 3000, 2000)->size(4000);

        $response = $this->actingAs($this->usuario)->post("/api/facturas/{$this->factura->id}/adjuntos", [
            'documentos' => [$pdf, $foto],
        ]);

        $response->assertStatus(201);
        $response->assertJsonCount(2, 'data');
        $this->assertDatabaseCount('documentos_adjuntos', 2);
    }

    public function test_lista_documentos_de_una_factura()
    {
        $pdf = UploadedFile::fake()->create('guia.pdf', 200, 'application/pdf');
        $this->actingAs($this->usuario)->post("/api/facturas/{$this->factura->id}/adjuntos", ['documentos' => [$pdf]]);

        $response = $this->actingAs($this->usuario)->getJson("/api/facturas/{$this->factura->id}/adjuntos");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.nombre_original', 'guia.pdf');
    }

    public function test_eliminar_adjunto_borra_la_fila_y_el_archivo_fisico()
    {
        $pdf = UploadedFile::fake()->create('guia.pdf', 200, 'application/pdf');
        $subida = $this->actingAs($this->usuario)->post("/api/facturas/{$this->factura->id}/adjuntos", ['documentos' => [$pdf]]);
        $adjuntoId = $subida->json('data.0.id');
        $ruta = $subida->json('data.0.ruta');

        Storage::disk('local')->assertExists($ruta);

        $response = $this->actingAs($this->usuario)->deleteJson("/api/facturas/{$this->factura->id}/adjuntos/{$adjuntoId}");

        $response->assertOk();
        $this->assertDatabaseMissing('documentos_adjuntos', ['id' => $adjuntoId]);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_rechaza_mas_de_10_documentos_por_subida()
    {
        $archivos = [];
        for ($i = 0; $i < 11; $i++) {
            $archivos[] = UploadedFile::fake()->create("doc{$i}.pdf", 10, 'application/pdf');
        }

        $response = $this->actingAs($this->usuario)->post("/api/facturas/{$this->factura->id}/adjuntos", [
            'documentos' => $archivos,
        ]);

        $response->assertStatus(422);
    }

    public function test_la_ficha_del_proveedor_incluye_el_conteo_de_documentos_adjuntos()
    {
        $pdf = UploadedFile::fake()->create('guia.pdf', 200, 'application/pdf');
        $this->actingAs($this->usuario)->post("/api/facturas/{$this->factura->id}/adjuntos", ['documentos' => [$pdf]]);

        $proveedorId = $this->factura->proveedor_id;
        $response = $this->actingAs($this->usuario)->getJson("/api/proveedores/ficha/{$proveedorId}");

        $response->assertOk();
        $response->assertJsonPath('data.facturas.0.documentos_adjuntos_count', 1);
    }

    public function test_no_puede_ver_ni_eliminar_adjunto_de_factura_de_otra_empresa()
    {
        $otraEmpresa = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Otra']);
        $otroProveedor = Proveedor::create(['empresa_id' => $otraEmpresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'ProvAjeno', 'codigo_interno' => 'P2', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $facturaAjena = Factura::create(['empresa_id' => $otraEmpresa->id, 'proveedor_id' => $otroProveedor->id, 'numero_factura' => 'F-AJENA', 'codigo_unico' => 999888, 'fecha_emision' => now(), 'monto_bruto' => 100, 'monto_neto' => 100, 'monto_iva' => 0, 'tipo' => 'COMPRA']);

        $response = $this->actingAs($this->usuario)->getJson("/api/facturas/{$facturaAjena->id}/adjuntos");
        $response->assertStatus(404);

        $pdf = UploadedFile::fake()->create('guia.pdf', 200, 'application/pdf');
        $response = $this->actingAs($this->usuario)->post("/api/facturas/{$facturaAjena->id}/adjuntos", ['documentos' => [$pdf]]);
        $response->assertStatus(404);
    }
}
