<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Pais;

class ActivoFijoTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);

        Pais::create([
            'iso' => 'CL',
            'nombre' => 'Chile',
            'moneda_defecto' => 'CLP',
            'etiqueta_id' => 'RUT',
            'activo' => true
        ]);

        $this->empresa = Empresa::create([
            'rut' => '77.777.777-7',
            'razon_social' => 'Empresa Test QA',
            'regimen_tributario' => '14_D3',
            'tasa_impuesto' => 25.00
        ]);

        $this->usuario = User::create([
            'nombre' => 'QA Tester',
            'email' => 'qa@erp.cl',
            'password' => bcrypt('password123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $rol->id,
            'estado_suscripcion_id' => 1
        ]);
    }

    //  PRUEBA DE PENETRACIÓN: Aislamiento Tenant.
    public function test_aislamiento_de_datos_multitenant_evita_fuga_de_informacion()
    {
        $empresaHacker = Empresa::create(['rut' => '66.666.666-6', 'razon_social' => 'Hacker SpA']);
        $hacker = User::create(['nombre' => 'Hacker', 'email' => 'hacker@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $empresaHacker->id, 'rol_id' => 1, 'estado_suscripcion_id' => 1]);

        ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-00001',
            'nombre' => 'Servidor Confidencial',
            'valor_adquisicion' => 1000000,
            'fecha_adquisicion' => now(),
            'vida_util_meses' => 60,
        ]);

        $response = $this->actingAs($hacker)->getJson('/api/activos');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'), "FALLO DE SEGURIDAD: Un usuario vio activos de otra empresa.");
    }

    // PRUEBA DE VALIDACIÓN: Input malicioso.
    public function test_rechaza_creacion_con_datos_negativos_incompletos_o_maliciosos()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => '<script>alert("xss")</script>',
            'valor_adquisicion' => -50000,
            'fecha_adquisicion' => 'fecha-inventada',
            'vida_util_meses' => 0
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['valor_adquisicion', 'fecha_adquisicion', 'vida_util_meses']);
    }

    // PRUEBA DE LÓGICA DE NEGOCIO: Límite de Depreciación.
    public function test_depreciacion_nunca_supera_valor_residual()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-00002',
            'nombre' => 'Notebook CEO',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => now()->subMonths(10),
            'vida_util_meses' => 10,
            'valor_residual' => 1,
            'depreciacion_acumulada' => 95000,
            'estado' => 'ACTIVO'
        ]);

        $response = $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', [
            'mes_anio' => now()->format('Y-m')
        ]);

        $response->assertStatus(200);

        $activo->refresh();

        $this->assertEquals(99999, $activo->depreciacion_acumulada, "Error crítico contable: La depreciación cruzó el límite del valor residual.");
    }


    // PRUEBA DE LÓGICA DE NEGOCIO: Evitar doble capitalización.

    public function test_activar_proyecto_dos_veces_lanza_excepcion()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Proyecto Doble',
            'estado' => 'ACTIVO_OPERATIVO', // Ya activado!
            'valor_total_original' => 500000,
            'vida_util_meses' => 60
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}/activar");

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertSee('ya ha sido activado');
    }

    // PRUEBA END-TO-END: Flujo completo de Proyecto en Construcción.
    public function test_flujo_completo_imputar_costos_y_capitalizar_proyecto()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Edificio Nueva Sucursal',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 120
        ]);

        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'Constructor', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => 123456,
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-111',
            'fecha_emision' => now(),
            'monto_neto' => 500000,
            'monto_iva' => 0,
            'monto_bruto' => 500000,
            'tipo' => 'COMPRA'
        ]);

        $responseImputar = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", [
            'factura_id' => $factura->id,
            'monto' => 500000
        ]);

        $responseImputar->assertStatus(200);

        $this->assertEquals(500000, $proyecto->fresh()->valor_total_original);
        $this->assertEquals($proyecto->id_proyecto, $factura->fresh()->proyecto_activo_id);

        $responseActivar = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}/activar");

        $responseActivar->assertStatus(200);
        $this->assertEquals('ACTIVO_OPERATIVO', $proyecto->fresh()->estado);

        $this->assertDatabaseHas('activos_fijos', [
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Edificio Nueva Sucursal',
            'valor_adquisicion' => 500000,
            'estado' => 'ACTIVO'
        ]);
    }

    // PRUEBA DE ESTABILIDAD: Intentar depreciar sin activos.
    public function test_depreciacion_sin_activos_operativos_falla_con_gracia()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', [
            'mes_anio' => now()->format('Y-m')
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertSee('No hay activos fijos operativos');
    }

    // PRUEBA DE SEGURIDAD (IDOR): Analizar proyecto de otra empresa.

    public function test_analisis_proyecto_ajeno_retorna_404_por_seguridad()
    {
        $empresaAjena = Empresa::create(['rut' => '99.999.999-9', 'razon_social' => 'Empresa Enemiga']);

        $proyectoAjeno = ProyectoActivo::create([
            'empresa_id' => $empresaAjena->id,
            'nombre' => 'Secreto Industrial',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 1000000,
            'vida_util_meses' => 60
        ]);

        $response = $this->actingAs($this->usuario)->getJson("/api/activos/proyectos/{$proyectoAjeno->id_proyecto}/analisis");

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'No se pudo cargar el análisis del proyecto. Es posible que no exista.']);
    }

    // PRUEBA DE LÓGICA CONTABLE: Imputar a un proyecto más dinero del que tiene la factura.

    public function test_imputar_monto_superior_al_neto_de_factura_falla()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Maquinaria',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 120
        ]);

        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P2', 'razon_social' => 'Prov Test', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'codigo_unico' => 999888,
            'proveedor_id' => $prov->id,
            'numero_factura' => 'F-100',
            'fecha_emision' => now(),
            'monto_neto' => 50000,
            'monto_iva' => 9500,
            'monto_bruto' => 59500,
            'tipo' => 'COMPRA',
            'estado' => 'REGISTRADA'
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", [
            'factura_id' => $factura->id,
            'monto' => 500000
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEquals(0, $proyecto->fresh()->valor_total_original);
    }

    // PRUEBA DE LÓGICA DE NEGOCIO: Intentar capitalizar un proyecto sin valor.

    public function test_activar_proyecto_con_costo_cero_lanza_error()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Proyecto Vacío',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 60
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}/activar");

        $response->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('activos_fijos', [
            'nombre' => 'Proyecto Vacío'
        ]);
    }

    // PRUEBA DE VALIDACIÓN: Inyección de fechas inválidas para engañar la depreciación.
    public function test_depreciacion_rechaza_formatos_de_fecha_maliciosos()
    {
        $payloadsErroneos = [
            '2026-13',
            '2026/05',
            'drop table activos;',
            '26-05'
        ];

        foreach ($payloadsErroneos as $fechaMala) {
            $response = $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', [
                'mes_anio' => $fechaMala
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['mes_anio']);
        }
    }

    // PRUEBA DE ESTABILIDAD VISUAL: El endpoint de parámetros devuelve datos limpios.

    public function test_endpoint_parametros_clasifica_bien_las_cuentas_contables()
    {
        \App\Domains\Contabilidad\Models\PlanCuenta::insert([
            ['empresa_id' => $this->empresa->id, 'codigo' => '100', 'nombre' => 'Vehículos', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true],
            ['empresa_id' => $this->empresa->id, 'codigo' => '101', 'nombre' => 'Depreciación Acumulada', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true],
            ['empresa_id' => $this->empresa->id, 'codigo' => '500', 'nombre' => 'Gasto por Depreciación', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true],
        ]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/parametros');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['cuentas_activo']);
        $this->assertEquals('Vehículos', $data['cuentas_activo'][0]['nombre']);
        $this->assertCount(1, $data['cuentas_depreciacion']);
        $this->assertEquals('Depreciación Acumulada', $data['cuentas_depreciacion'][0]['nombre']);
        $this->assertCount(1, $data['cuentas_gasto']);
    }

    // PRUEBA DE ESTABILIDAD: Si el usuario no envía un código, el sistema debe asegurar un formato AF-0000X sin fallar.

    public function test_autogeneracion_de_codigo_correlativo_al_crear_activo()
    {
        $response1 = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => 'Mesa de Reuniones',
            'valor_adquisicion' => 150000,
            'fecha_adquisicion' => now()->format('Y-m-d'),
            'vida_util_meses' => 60
        ]);

        $response1->assertStatus(201);
        $this->assertStringStartsWith('AF-', $response1->json('data.codigo'));

        $response2 = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => 'Silla Ergonómica',
            'valor_adquisicion' => 80000,
            'fecha_adquisicion' => now()->format('Y-m-d'),
            'vida_util_meses' => 36
        ]);

        $response2->assertStatus(201);
        $this->assertNotEquals($response1->json('data.codigo'), $response2->json('data.codigo'));
    }

    // PRUEBA FISCAL:  Evita cometer fraude declarando gastos de maquinaria que ya no existe o se vendió.

    public function test_depreciacion_ignora_activos_dados_de_baja_o_pendientes()
    {
        $activoDadoDeBaja = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-0099',
            'nombre' => 'Vehículo Antiguo',
            'valor_adquisicion' => 5000000,
            'fecha_adquisicion' => now()->subMonths(5),
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'depreciacion_acumulada' => 100000,
            'estado' => 'DADO_DE_BAJA'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', [
            'mes_anio' => now()->format('Y-m')
        ]);

        $this->assertEquals(100000, $activoDadoDeBaja->fresh()->depreciacion_acumulada);
    }

    // PRUEBA DE SEGURIDAD INTER-DOMINIOS (IDOR): Intento de robar el costo de una factura de otra empresa hacia mi proyecto.

    public function test_imputar_factura_de_otra_empresa_lanza_excepcion_y_falla()
    {
        $miProyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Mi Construcción',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 120
        ]);

        $empresaAjena = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Empresa B']);
        $provAjeno = Proveedor::create(['empresa_id' => $empresaAjena->id, 'codigo_interno' => 'P9', 'razon_social' => 'Prov', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $facturaAjena = Factura::create([
            'empresa_id' => $empresaAjena->id,
            'codigo_unico' => 555444,
            'proveedor_id' => $provAjeno->id,
            'numero_factura' => 'F-SECRETA',
            'monto_neto' => 10000000,
            'monto_iva' => 1900000,
            'monto_bruto' => 11900000,
            'tipo' => 'COMPRA',
            'fecha_emision' => now()
        ]);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$miProyecto->id_proyecto}/facturas", [
            'factura_id' => $facturaAjena->id,
            'monto' => 10000000
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertSee('no existe o no pertenece a su empresa');

        $this->assertEquals(0, $miProyecto->fresh()->valor_total_original);
    }

    // PRUEBA MATEMÁTICA E2E: Capitalizar un proyecto con múltiples facturas debe sumar el monto matemáticamente exacto.

    public function test_capitalizacion_consolida_multiples_facturas_exactamente()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Ensamblaje Servidor',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 48
        ]);

        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P-SRV', 'razon_social' => 'Tech', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $f1 = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 111, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-1', 'monto_neto' => 100000, 'monto_iva' => 0, 'monto_bruto' => 100000, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);
        $f2 = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 222, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-2', 'monto_neto' => 250000, 'monto_iva' => 0, 'monto_bruto' => 250000, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);

        $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", ['factura_id' => $f1->id, 'monto' => 100000]);
        $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", ['factura_id' => $f2->id, 'monto' => 250000]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}/activar");

        $response->assertStatus(200);

        $this->assertDatabaseHas('activos_fijos', [
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Ensamblaje Servidor',
            'valor_adquisicion' => 350000,
            'vida_util_meses' => 48,
            'estado' => 'ACTIVO'
        ]);
    }

    // PRUEBA DE SEGURIDAD DE LISTADOS:

    public function test_listados_generales_estan_aislados_por_empresa()
    {
        $empresaAjena = Empresa::create(['rut' => '33.333.333-3', 'razon_social' => 'Empresa Fantasma']);

        ActivoFijo::create([
            'empresa_id' => $empresaAjena->id,
            'codigo' => 'AF-AJENO',
            'nombre' => 'Secreto',
            'valor_adquisicion' => 100,
            'fecha_adquisicion' => now(),
            'vida_util_meses' => 10,
            'estado' => 'ACTIVO'
        ]);

        ProyectoActivo::create([
            'empresa_id' => $empresaAjena->id,
            'nombre' => 'Proyecto Fantasma',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 100,
            'vida_util_meses' => 10
        ]);

        $resActivos = $this->actingAs($this->usuario)->getJson('/api/activos');
        $resPendientes = $this->actingAs($this->usuario)->getJson('/api/activos/pendientes');
        $resProyectos = $this->actingAs($this->usuario)->getJson('/api/activos/proyectos');

        $this->assertCount(0, $resActivos->json('data'));
        $this->assertCount(0, $resPendientes->json('data'));
        $this->assertCount(0, $resProyectos->json('data'));
    }

    // PRUEBA DE ERROR DE USUARIO: Evita depreciación inversa (ganancias fantasmas).

    public function test_rechaza_activo_con_valor_residual_mayor_o_igual_a_adquisicion()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => 'Escritorio Mágico',
            'valor_adquisicion' => 50000,
            'valor_residual' => 100000,
            'fecha_adquisicion' => now()->format('Y-m-d'),
            'vida_util_meses' => 12
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertSee('El valor residual no puede ser mayor o igual');
    }

    // PRUEBA DE ERROR DE USUARIO: Evita agregar costos a un proyecto que ya fue cerrado y convertido en Activo.

    public function test_no_se_pueden_imputar_facturas_a_proyectos_ya_activados()
    {
        $proyectoCerrado = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Edificio Terminado',
            'estado' => 'ACTIVO_OPERATIVO',
            'valor_total_original' => 1000000,
            'vida_util_meses' => 120
        ]);

        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P-ZMB', 'razon_social' => 'Prov', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $facturaAtrasada = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 333, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-ATRASADA', 'monto_neto' => 50000, 'monto_iva' => 0, 'monto_bruto' => 50000, 'tipo' => 'COMPRA', 'estado' => 'REGISTRADA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyectoCerrado->id_proyecto}/facturas", [
            'factura_id' => $facturaAtrasada->id,
            'monto' => 50000
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertSee('cerrado');
    }

    // PRUEBA DE ERROR DE USUARIO: Doble asignación de la misma factura.

    public function test_evita_imputar_la_misma_factura_dos_veces_al_mismo_proyecto()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Software a Medida',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 36
        ]);

        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P-DEV', 'razon_social' => 'Devs', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 444, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-DOBLE', 'monto_neto' => 800000, 'monto_iva' => 0, 'monto_bruto' => 800000, 'tipo' => 'COMPRA', 'estado' => 'REGISTRADA', 'fecha_emision' => now()]);

        $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", [
            'factura_id' => $factura->id,
            'monto' => 800000
        ])->assertStatus(200);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", [
            'factura_id' => $factura->id,
            'monto' => 800000
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertSee('ya ha sido asignada');

        $this->assertEquals(800000, $proyecto->fresh()->valor_total_original);
    }

    public function test_vendedor_no_puede_ejecutar_operaciones_contables_criticas()
    {
        $rolVendedor = Rol::create(['id' => 2, 'nombre' => 'Vendedor', 'jerarquia' => 10]);
        $vendedor = User::create([
            'nombre' => 'Empleado Ventas',
            'email' => 'ventas@erp.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $rolVendedor->id,
            'estado_suscripcion_id' => 1
        ]);

        $response = $this->actingAs($vendedor)->postJson('/api/activos/depreciar-mes', [
            'mes_anio' => now()->format('Y-m')
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertSee('Acceso denegado');
    }
}