<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\OrdenCompra;
use App\Domains\Comercial\Models\DetalleOrdenCompra;

class OrdenCompraTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected Empresa $empresa;
    protected User $usuario;
    protected Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'razon_social' => 'Proveedor Test SpA',
            'rut' => '76.543.210-5',
            'codigo_interno' => 'P-TEST-01',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    private function payloadBase(): array
    {
        return [
            'proveedor_id' => $this->proveedor->id,
            'fecha_emision' => now()->format('Y-m-d'),
            'detalles' => [
                [
                    'producto_descripcion' => 'Resma papel A4',
                    'cantidad' => 10,
                    'precio_unitario' => 3500,
                    'subtotal' => 35000,
                ],
                [
                    'producto_descripcion' => 'Lapiceros azul x12',
                    'cantidad' => 5,
                    'precio_unitario' => 1200,
                    'subtotal' => 6000,
                ],
            ],
        ];
    }

    public function test_crear_oc_con_detalles_retorna_201_y_estructura_correcta(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payloadBase());

        $response->assertStatus(201)
            ->assertJsonPath('data.estado', 'BORRADOR')
            ->assertJsonPath('data.proveedor_id', $this->proveedor->id);

        $this->assertDatabaseHas('ordenes_compra', [
            'empresa_id' => $this->empresa->id,
            'estado' => 'BORRADOR',
        ]);
        $this->assertDatabaseCount('detalle_ordenes_compra', 2);
    }

    public function test_numero_oc_se_auto_genera_con_formato_correcto(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payloadBase());

        $response->assertStatus(201);
        $numeroOc = $response->json('data.numero_oc');
        $this->assertMatchesRegularExpression('/^OC-\d{4}-\d{4}$/', $numeroOc);
    }

    public function test_listar_ocs_solo_muestra_las_de_su_empresa(): void
    {
        // Crear OC para empresa propia
        $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payloadBase());

        // Crear OC para otra empresa
        [$otraEmpresa, $otroUsuario] = $this->crearEmpresaConAdmin();
        $otroProveedor = Proveedor::create([
            'empresa_id' => $otraEmpresa->id,
            'razon_social' => 'Otro Proveedor',
            'rut' => '65.432.109-4',
            'codigo_interno' => 'P-OTRO',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
        $this->actingAs($otroUsuario)->postJson('/api/comercial/ordenes-compra', [
            'proveedor_id' => $otroProveedor->id,
            'fecha_emision' => now()->format('Y-m-d'),
            'detalles' => [['producto_descripcion' => 'Otro producto', 'cantidad' => 1, 'precio_unitario' => 1000, 'subtotal' => 1000]],
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson('/api/comercial/ordenes-compra');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_anular_oc_cambia_estado_a_anulada(): void
    {
        $crearResp = $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payloadBase());

        $id = $crearResp->json('data.id');

        $this->actingAs($this->usuario)
            ->deleteJson("/api/comercial/ordenes-compra/{$id}")
            ->assertOk()
            ->assertJsonPath('mensaje', 'Orden de compra anulada.');

        $this->assertDatabaseHas('ordenes_compra', ['id' => $id, 'estado' => 'ANULADA']);
    }

    public function test_recibir_mercaderia_parcialmente_cambia_estado(): void
    {
        $crearResp = $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payloadBase());

        $id = $crearResp->json('data.id');
        $detalles = $crearResp->json('data.detalles');
        $primerDetalleId = $detalles[0]['id'];

        $response = $this->actingAs($this->usuario)
            ->postJson("/api/comercial/ordenes-compra/{$id}/recibir", [
                'recepciones' => [
                    ['detalle_id' => $primerDetalleId, 'cantidad_recibida' => 5],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.estado', 'RECIBIDA_PARCIAL');
    }

    public function test_recibir_todos_los_detalles_cambia_estado_a_recibida_total(): void
    {
        $crearResp = $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payloadBase());

        $id = $crearResp->json('data.id');
        $detalles = $crearResp->json('data.detalles');

        $recepciones = array_map(
            fn ($d) => ['detalle_id' => $d['id'], 'cantidad_recibida' => $d['cantidad']],
            $detalles
        );

        $response = $this->actingAs($this->usuario)
            ->postJson("/api/comercial/ordenes-compra/{$id}/recibir", [
                'recepciones' => $recepciones,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.estado', 'RECIBIDA_TOTAL');
    }

    public function test_usuario_sin_permiso_recibe_403(): void
    {
        $usuarioSinPermiso = $this->crearUsuario($this->empresa, $this->rolUsuarioBasico);

        $this->actingAs($usuarioSinPermiso)
            ->getJson('/api/comercial/ordenes-compra')
            ->assertStatus(403);
    }

    public function test_no_se_puede_anular_una_oc_ya_recibida_totalmente(): void
    {
        $crearResp = $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payloadBase());
        $id = $crearResp->json('data.id');
        $detalles = $crearResp->json('data.detalles');

        $this->actingAs($this->usuario)->postJson("/api/comercial/ordenes-compra/{$id}/recibir", [
            'recepciones' => array_map(fn ($d) => ['detalle_id' => $d['id'], 'cantidad_recibida' => $d['cantidad']], $detalles),
        ])->assertJsonPath('data.estado', 'RECIBIDA_TOTAL');

        $this->actingAs($this->usuario)
            ->deleteJson("/api/comercial/ordenes-compra/{$id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('ordenes_compra', ['id' => $id, 'estado' => 'RECIBIDA_TOTAL']);
    }

    public function test_no_se_puede_recibir_mercaderia_de_una_oc_anulada(): void
    {
        $crearResp = $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payloadBase());
        $id = $crearResp->json('data.id');
        $detalles = $crearResp->json('data.detalles');

        $this->actingAs($this->usuario)->deleteJson("/api/comercial/ordenes-compra/{$id}")->assertOk();

        $this->actingAs($this->usuario)
            ->postJson("/api/comercial/ordenes-compra/{$id}/recibir", [
                'recepciones' => [['detalle_id' => $detalles[0]['id'], 'cantidad_recibida' => 1]],
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('detalle_ordenes_compra', ['id' => $detalles[0]['id'], 'cantidad_recibida' => 0]);
    }

    public function test_update_ignora_estado_enviado_por_el_cliente(): void
    {
        $crearResp = $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payloadBase());
        $id = $crearResp->json('data.id');

        $this->actingAs($this->usuario)
            ->putJson("/api/comercial/ordenes-compra/{$id}", ['estado' => 'RECIBIDA_TOTAL'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'BORRADOR');

        $this->assertDatabaseHas('ordenes_compra', ['id' => $id, 'estado' => 'BORRADOR']);
    }

    public function test_oc_de_otra_empresa_retorna_404(): void
    {
        [$otraEmpresa, $otroUsuario] = $this->crearEmpresaConAdmin();
        $otroProveedor = Proveedor::create([
            'empresa_id' => $otraEmpresa->id,
            'razon_social' => 'Proveedor Ajeno',
            'rut' => '55.555.555-5',
            'codigo_interno' => 'P-AJENO',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $crearResp = $this->actingAs($otroUsuario)
            ->postJson('/api/comercial/ordenes-compra', [
                'proveedor_id' => $otroProveedor->id,
                'fecha_emision' => now()->format('Y-m-d'),
                'detalles' => [['producto_descripcion' => 'Prod', 'cantidad' => 1, 'precio_unitario' => 100, 'subtotal' => 100]],
            ]);

        $idAjena = $crearResp->json('data.id');

        $this->actingAs($this->usuario)
            ->getJson("/api/comercial/ordenes-compra/{$idAjena}")
            ->assertStatus(404);
    }
}
