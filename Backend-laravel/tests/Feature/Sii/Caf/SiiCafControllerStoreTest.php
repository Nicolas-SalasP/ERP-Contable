<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiCafControllerStoreTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearEmpresaConRut(string $num = '76123456'): array
    {
        [, $usuario] = $this->crearEmpresaConAdmin();

        $rut = $num . '-' . RutHelper::calcularDv((int) $num);
        $empresa = Empresa::find($usuario->empresa_id);
        $empresa->update(['rut' => $rut]);

        return [$empresa->fresh(), $usuario, $rut];
    }

    private function xmlCaf(string $rut, string $idk = '300', int $tipo = 33, int $desde = 1, int $hasta = 50): string
    {
        return <<<XML
<?xml version="1.0"?>
<AUTORIZACION>
  <CAF version="1.0">
    <DA>
      <RE>{$rut}</RE><RS>EMPRESA STORE TEST</RS><TD>{$tipo}</TD>
      <RNG><D>{$desde}</D><H>{$hasta}</H></RNG>
      <FA>2026-01-15</FA><RSAPK><M>M</M><E>E</E></RSAPK><IDK>{$idk}</IDK>
    </DA>
    <FRMA algoritmo="SHA1withRSA">FIRMA_DUMMY</FRMA>
  </CAF>
  <RSASK>-----BEGIN RSA PRIVATE KEY-----
DUMMY
-----END RSA PRIVATE KEY-----</RSASK>
  <RSAPUBK>-----BEGIN PUBLIC KEY-----
DUMMY
-----END PUBLIC KEY-----</RSAPUBK>
</AUTORIZACION>
XML;
    }

    public function test_store_con_xml_valido_retorna_201(): void
    {
        [, $usuario, $rut] = $this->crearEmpresaConRut('76123456');
        Sanctum::actingAs($usuario);

        $archivo = UploadedFile::fake()->createWithContent('caf.xml', $this->xmlCaf($rut, '300'));

        $this->post('/api/sii/caf', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonStructure(['id', 'tipo_dte', 'folio_desde', 'folio_hasta', 'sii_idk', 'estado']);
    }

    public function test_store_persiste_caf_con_metadatos_correctos(): void
    {
        [$empresa, $usuario, $rut] = $this->crearEmpresaConRut('76123456');
        Sanctum::actingAs($usuario);

        $archivo = UploadedFile::fake()->createWithContent('caf.xml', $this->xmlCaf($rut, '400', 39, 1, 100));

        $this->post('/api/sii/caf', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $caf = SiiCaf::where('empresa_id', $empresa->id)->first();
        $this->assertNotNull($caf);
        $this->assertSame(39, $caf->tipo_dte);
        $this->assertSame(1, $caf->folio_desde);
        $this->assertSame(100, $caf->folio_hasta);
        $this->assertSame('400', $caf->sii_idk);
    }

    public function test_store_rechaza_archivo_no_xml_con_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $archivo = UploadedFile::fake()->createWithContent('caf.txt', 'contenido cualquiera');

        $this->post('/api/sii/caf', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    public function test_store_rechaza_archivo_demasiado_grande_con_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        // 150 KB > 100 KB
        $archivo = UploadedFile::fake()->createWithContent('caf.xml', str_repeat('A', 150 * 1024));

        $this->post('/api/sii/caf', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    public function test_store_rechaza_xml_malformado_con_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $archivo = UploadedFile::fake()->createWithContent('caf.xml', '<AUTORIZACION><CAF>NO_CIERRA');

        $this->post('/api/sii/caf', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonStructure(['mensaje', 'error_code'])
            ->assertJson(['error_code' => 'xml_malformado']);
    }

    public function test_store_rechaza_xml_sin_estructura_CAF_con_422(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $archivo = UploadedFile::fake()->createWithContent('caf.xml', '<?xml version="1.0"?><otra><x>1</x></otra>');

        $this->post('/api/sii/caf', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJson(['error_code' => 'estructura_invalida']);
    }

    public function test_store_rechaza_caf_con_rut_distinto_a_empresa_con_422(): void
    {
        [, $usuario] = $this->crearEmpresaConRut('76123456');
        Sanctum::actingAs($usuario);

        $otroRut = '99999999-' . RutHelper::calcularDv(99_999_999);
        $archivo = UploadedFile::fake()->createWithContent('caf.xml', $this->xmlCaf($otroRut));

        $this->post('/api/sii/caf', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJson(['error_code' => 'rut_no_coincide']);
    }

    public function test_store_rechaza_caf_duplicado_con_422(): void
    {
        [, $usuario, $rut] = $this->crearEmpresaConRut('76123456');
        Sanctum::actingAs($usuario);

        $archivo1 = UploadedFile::fake()->createWithContent('caf.xml', $this->xmlCaf($rut, '500'));
        $archivo2 = UploadedFile::fake()->createWithContent('caf.xml', $this->xmlCaf($rut, '500', 33, 100, 200));

        $this->post('/api/sii/caf', ['archivo' => $archivo1], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $this->post('/api/sii/caf', ['archivo' => $archivo2], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJson(['error_code' => 'ya_existe']);
    }

    public function test_store_oculta_rsa_y_xml_en_response(): void
    {
        [, $usuario, $rut] = $this->crearEmpresaConRut('76123456');
        Sanctum::actingAs($usuario);

        $archivo = UploadedFile::fake()->createWithContent('caf.xml', $this->xmlCaf($rut));

        $this->post('/api/sii/caf', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonMissing(['rsa_sk_cifrada'])
            ->assertJsonMissing(['xml_completo_cifrado']);
    }

    public function test_store_requiere_autenticacion_401(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('caf.xml', '<x/>');

        $this->post('/api/sii/caf', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(401);
    }
}
