<?php

namespace Tests\Feature\Inventario;

use App\Domains\Inventario\Models\InventarioAuditoriaEvento;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use App\Domains\Inventario\Services\InventarioAuditoriaService;
use App\Domains\Inventario\Services\InventarioEventoIntegracionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioFase19HardeningFinalTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararUsuariosInventarioDemo();
    }

    public function test_auditoria_sanea_claves_sensibles_y_tributarias_en_payloads_json(): void
    {
        [$empresa, $usuario] = $this->usuarioAuditorConPermisos($this->permisosInventarioAuditor());

        app(InventarioAuditoriaService::class)->registrarEvento($usuario, [
            'empresa_id' => $empresa->id,
            'accion' => InventarioAuditoriaEvento::ACCION_OPERACION_BLOQUEADA,
            'entidad_tipo' => 'inventario_hardening_f19',
            'entidad_id' => 19,
            'severidad' => InventarioAuditoriaEvento::SEVERIDAD_WARNING,
            'descripcion' => 'Hardening F19 de auditoría.',
            'metadata_json' => [
                'Authorization' => 'Bearer secreto',
                'codigo_dte' => 'no-guardar',
                'payload' => [
                    'access_token' => 'token-interno',
                    'xml_dte' => '<xml/>',
                    'campo_seguro' => 'visible',
                ],
            ],
            'antes_json' => [
                'folio_dte' => '123',
                'campo_anterior' => 'ok',
            ],
            'despues_json' => [
                'track_id_sii' => 'abc',
                'campo_nuevo' => 'ok',
            ],
        ]);

        $evento = InventarioAuditoriaEvento::where('entidad_tipo', 'inventario_hardening_f19')->firstOrFail();

        $this->assertArrayNotHasKey('Authorization', $evento->metadata_json);
        $this->assertArrayNotHasKey('codigo_dte', $evento->metadata_json);
        $this->assertArrayNotHasKey('access_token', $evento->metadata_json['payload']);
        $this->assertArrayNotHasKey('xml_dte', $evento->metadata_json['payload']);
        $this->assertEquals('visible', $evento->metadata_json['payload']['campo_seguro']);

        $this->assertArrayNotHasKey('folio_dte', $evento->antes_json);
        $this->assertEquals('ok', $evento->antes_json['campo_anterior']);
        $this->assertArrayNotHasKey('track_id_sii', $evento->despues_json);
        $this->assertEquals('ok', $evento->despues_json['campo_nuevo']);
    }

    public function test_gestion_de_eventos_integracion_queda_auditada(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());

        $evento = InventarioEventoIntegracion::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'evento' => InventarioEventoIntegracion::EVENTO_DESPACHO_CONFIRMADO,
            'entidad_tipo' => 'despacho_f19',
            'entidad_id' => 1901,
            'estado' => InventarioEventoIntegracion::ESTADO_PENDIENTE,
            'prioridad' => InventarioEventoIntegracion::PRIORIDAD_ALTA,
            'correlacion_id' => 'corr-f19-auditada',
        ]);

        app(InventarioEventoIntegracionService::class)->marcarProcesado($usuario, $evento->id);

        $this->assertDatabaseHas('inventario_auditoria_eventos', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'accion' => InventarioAuditoriaEvento::ACCION_EVENTO_INTEGRACION_PROCESADO,
            'entidad_tipo' => InventarioEventoIntegracion::class,
            'entidad_id' => $evento->id,
            'origen_modulo' => 'inventario.eventos_integracion',
            'origen_id' => $evento->id,
        ]);
    }

    public function test_dashboard_admite_permisos_de_lectura_avanzados_f17_f18(): void
    {
        [, $usuario] = $this->usuarioAuditorConPermisos([
            'inventario.eventos_integracion.ver',
        ]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_tablas_inventario_no_agregan_columnas_dte_sii_en_cierre_f19(): void
    {
        $tablasInventario = [
            'inventario_auditoria_eventos',
            'inventario_eventos_integracion',
            'inventario_despacho_ordenes',
            'inventario_devolucion_ordenes',
            'inventario_movimientos',
        ];

        $columnasBloqueadas = [
            'codigo_dte',
            'codigo_sii',
            'folio_dte',
            'xml_dte',
            'track_id_sii',
            'guia_despacho_electronica',
            'factura_electronica',
            'boleta_electronica',
            'emitir_dte',
            'estado_sii',
        ];

        foreach ($tablasInventario as $tabla) {
            foreach ($columnasBloqueadas as $columna) {
                $this->assertFalse(Schema::hasColumn($tabla, $columna), "La tabla {$tabla} no debe incluir {$columna}.");
            }
        }
    }
}
