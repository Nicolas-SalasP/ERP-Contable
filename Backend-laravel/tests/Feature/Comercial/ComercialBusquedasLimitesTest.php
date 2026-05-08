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

class ComercialBusquedasLimitesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;
    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Limites SpA']);
        $this->usuario = User::create(['nombre' => 'User', 'email' => 'u@lim.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_api_proveedores_ignora_limites_negativos_en_paginacion()
    {
        $response = $this->actingAs($this->usuario)->getJson('/api/proveedores?limit=-50');
        
        // Laravel o lo trata como error 422, o lo convierte a limite por defecto (200), pero NUNCA un 500 DB Exception
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_api_proveedores_ignora_limites_excesivos_para_prevenir_dos()
    {
        $response = $this->actingAs($this->usuario)->getJson('/api/proveedores?limit=999999999');
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_busqueda_historial_facturas_escapa_caracteres_sql_injection()
    {
        $payloadMalicioso = "Apple'; DROP TABLE facturas; --";
        $response = $this->actingAs($this->usuario)->getJson("/api/facturas/historial?search={$payloadMalicioso}");

        // El QueryBuilder de Laravel debe sanitizar esto y simplemente devolver un array vacío (200 OK)
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    public function test_api_clientes_retorna_arreglo_vacio_si_empresa_no_tiene_registros()
    {
        // Empresa nueva sin clientes
        $response = $this->actingAs($this->usuario)->getJson('/api/clientes');
        
        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
        $this->assertEmpty($response->json('data'));
    }

    public function test_check_duplicados_responde_false_si_faltan_parametros()
    {
        // Petición incompleta al check asíncrono
        $response = $this->actingAs($this->usuario)->getJson("/api/facturas/check?numeroFactura=F-123");
        
        // Al no tener proveedor, el servicio devuelve exists => false (O lanza 422)
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}