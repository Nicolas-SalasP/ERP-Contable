<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\AnticipoProveedor;
use App\Domains\Comercial\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * subirPdfAnticipo/descargarPdfAnticipo estaban implementados en ProveedorController
 * pero sin ruta registrada: el botón "Subir PDF" del frontend (VisorProveedor.jsx)
 * apuntaba a un endpoint inexistente y devolvía 404. Ver rutas agregadas en routes/api.php.
 */
class AnticipoProveedorPdfHttpTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected $empresa;

    protected $usuario;

    protected $anticipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Storage::fake('local');

        $this->empresa = $this->crearEmpresa([
            'rut' => '78.888.888-8',
            'razon_social' => 'Anticipos PDF SpA',
        ]);
        $this->usuario = $this->crearUsuario($this->empresa, $this->rolSuperAdmin, [
            'nombre' => 'Tesorero PDF',
            'email' => 'pdf@anti.cl',
        ]);

        $proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '2.2.2.2-2',
            'razon_social' => 'Prov PDF',
            'codigo_interno' => 'P2',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->anticipo = AnticipoProveedor::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedor->id,
            'monto' => 100000,
            'monto_original' => 100000,
            'saldo_disponible' => 100000,
            'fecha' => now()->format('Y-m-d'),
        ]);
    }

    public function test_subir_pdf_anticipo_via_http_real()
    {
        $response = $this->actingAs($this->usuario)->postJson(
            "/api/proveedores/anticipos/{$this->anticipo->id}/pdf",
            ['pdf' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf')]
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertNotNull($this->anticipo->fresh()->archivo_pdf);
    }

    public function test_descargar_pdf_anticipo_via_http_real()
    {
        $this->actingAs($this->usuario)->postJson(
            "/api/proveedores/anticipos/{$this->anticipo->id}/pdf",
            ['pdf' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf')]
        );

        $response = $this->actingAs($this->usuario)->get(
            "/api/proveedores/anticipos/{$this->anticipo->id}/pdf"
        );

        $response->assertStatus(200);
    }

    public function test_descargar_pdf_anticipo_sin_archivo_responde_404()
    {
        $response = $this->actingAs($this->usuario)->get(
            "/api/proveedores/anticipos/{$this->anticipo->id}/pdf"
        );

        $response->assertStatus(404);
    }
}
