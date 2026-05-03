<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;

class ContabilidadTest extends TestCase
{
    use RefreshDatabase;

    protected $empresaA;
    protected $usuarioContador;
    protected $rolContador;
    protected $rutaCuentas = '/api/contabilidad/plan-cuentas'; 
    protected $rutaAsientos = '/api/contabilidad/asientos';

    protected function setUp(): void
    {
        parent::setUp();

        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $this->rolContador = Rol::create(['nombre' => 'Contador', 'jerarquia' => 50, 'permisos' => []]);

        $this->empresaA = Empresa::create([
            'rut' => '77.777.777-7',
            'razon_social' => 'Finanzas Claras SpA'
        ]);

        $this->usuarioContador = User::create([
            'nombre' => 'Contador Jefe',
            'email' => 'contador@claras.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $this->rolContador->id,
            'estado_suscripcion_id' => 1
        ]);
    }

    // PRUEBA: Aislamiento Multitenant en Plan de Cuentas
    // Un contador jamas debe poder ver las cuentas contables de otra empresa.
    public function test_aislamiento_multitenant_en_plan_de_cuentas()
    {
        $empresaB = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Empresa B']);
        $hacker = User::create(['nombre' => 'Hacker', 'email' => 'h@b.cl', 'password' => bcrypt('123'), 'empresa_id' => $empresaB->id, 'rol_id' => $this->rolContador->id, 'estado_suscripcion_id' => 1]);

        PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '111', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        
        $response = $this->actingAs($hacker)->getJson($this->rutaCuentas);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // PRUEBA: Partida Doble (Debe == Haber)
    // El sistema debe bloquear cualquier asiento descuadrado.
    public function test_rechaza_asientos_contables_descuadrados()
    {
        $cuentaCaja = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1001', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        $cuentaVentas = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '4001', 'nombre' => 'Ventas', 'tipo' => 'INGRESO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->postJson($this->rutaAsientos, [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'Venta del día descuadrada',
            'origen_modulo' => 'manual',
            'detalles' => [
                ['cuenta_contable' => $cuentaCaja->codigo, 'debe' => 1000, 'haber' => 0],
                ['cuenta_contable' => $cuentaVentas->codigo, 'debe' => 0, 'haber' => 900]
            ]
        ]);

        $response->assertStatus(422)
                 ->assertSee('Partida Doble'); 
    }

    // PRUEBA: Estructura Mínima
    // Un asiento contable no existe si tiene menos de 2 lineas.
    public function test_rechaza_asiento_con_menos_de_dos_lineas()
    {
        $cuenta = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '111', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->postJson($this->rutaAsientos, [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'Asiento de una sola pierna',
            'detalles' => [
                ['cuenta_contable' => $cuenta->codigo, 'debe' => 1000, 'haber' => 1000]
            ]
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['detalles']); 
    }

    // PRUEBA: Imputabilidad (Cuentas Padre)
    // No se pueden hacer registros a Cuentas Agrupadoras (imputable = false).
    public function test_rechaza_movimientos_en_cuentas_no_imputables()
    {
        $cuentaPadre = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1000', 'nombre' => 'ACTIVOS', 'tipo' => 'ACTIVO', 'imputable' => false, 'activo' => true]);
        
        $response = $this->actingAs($this->usuarioContador)->postJson($this->rutaAsientos, [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'Intento de usar cuenta padre',
            'origen_modulo' => 'manual',
            'detalles' => [
                ['cuenta_contable' => $cuentaPadre->codigo, 'debe' => 1000, 'haber' => 0], 
                ['cuenta_contable' => $cuentaPadre->codigo, 'debe' => 0, 'haber' => 1000]
            ]
        ]);

        $response->assertStatus(422); 
    }

    // PRUEBA: Seguridad IDOR
    // Previene que el contador de la Empresa A descuadre a la Empresa B usando el codigo de una de sus cuentas.
    public function test_idor_rechaza_usar_cuentas_de_otra_empresa_en_los_detalles()
    {
        $empresaB = Empresa::create(['rut' => '99.999.999-9', 'razon_social' => 'Empresa B']);
        $cuentaAjena = PlanCuenta::create(['empresa_id' => $empresaB->id, 'codigo' => '500', 'nombre' => 'Gasto B', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
        
        $cuentaPropia = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '100', 'nombre' => 'Caja A', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->postJson($this->rutaAsientos, [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'Fraude de cuentas cruzadas',
            'detalles' => [
                ['cuenta_contable' => $cuentaPropia->codigo, 'debe' => 0, 'haber' => 1000],
                ['cuenta_contable' => $cuentaAjena->codigo, 'debe' => 1000, 'haber' => 0]
            ]
        ]);

        $response->assertStatus(422);
    }

    // PRUEBA: Duplicidad de Cuentas Contables (Creación)
    // Impide que se creen dos cuentas con el mismo código en la misma empresa.
    public function test_rechaza_creacion_de_cuenta_contable_con_codigo_duplicado()
    {
        PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1111', 'nombre' => 'Caja Real', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->postJson($this->rutaCuentas, [
            'codigo' => '1111',
            'nombre' => 'Caja Falsa',
            'tipo' => 'ACTIVO',
            'nivel' => 4,
            'imputable' => true,
            'activo' => true
        ]);

        $response->assertStatus(422)
                 ->assertSee('ya existe');
    }

    // PRUEBA: Modificación de Cuentas (Integridad de Datos)
    // Evita que al editar una cuenta le pongas el código de otra que ya existe.
    public function test_rechaza_actualizar_cuenta_con_codigo_ya_en_uso()
    {
        PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1000', 'nombre' => 'Activos', 'tipo' => 'ACTIVO', 'imputable' => false, 'activo' => true]);
        $cuenta2 = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '2000', 'nombre' => 'Pasivos', 'tipo' => 'PASIVO', 'imputable' => false, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->putJson($this->rutaCuentas . '/' . $cuenta2->id, [
            'codigo' => '1000',
            'nombre' => 'Pasivos Cambiados',
            'tipo' => 'PASIVO'
        ]);

        $response->assertStatus(422)
                 ->assertSee('uso');
    }

    // PRUEBA: Happy Path de Asiento Contable (Comprobación de guardado)
    // Verifica que un asiento válido se guarde correctamente y genere un comprobante automático.
    public function test_guarda_asiento_contable_valido_exitosamente()
    {
        $cuentaCaja = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1001', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        $cuentaCapital = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '3001', 'nombre' => 'Capital', 'tipo' => 'PATRIMONIO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->postJson($this->rutaAsientos, [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'Aporte inicial de capital',
            'origen_modulo' => 'manual',
            'detalles' => [
                ['cuenta_contable' => $cuentaCaja->codigo, 'debe' => 5000000, 'haber' => 0, 'glosa_detalle' => 'Ingreso a caja'],
                ['cuenta_contable' => $cuentaCapital->codigo, 'debe' => 0, 'haber' => 5000000, 'glosa_detalle' => 'Aporte socios']
            ]
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('data.numero_comprobante'));
        
        $this->assertDatabaseHas('asientos_contables', [
            'empresa_id' => $this->empresaA->id,
            'glosa' => 'Aporte inicial de capital'
        ]);
    }

    // PRUEBA: Cantidades Negativas (Capa 8)
    // Los montos contables siempre son absolutos. No existen los "Debe -500".
    public function test_capa8_rechaza_montos_negativos_en_los_detalles_del_asiento()
    {
        $cuentaCaja = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1001', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        $cuentaVentas = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '4001', 'nombre' => 'Ventas', 'tipo' => 'INGRESO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->postJson($this->rutaAsientos, [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'Venta hackeada',
            'detalles' => [
                ['cuenta_contable' => $cuentaCaja->codigo, 'debe' => -1000, 'haber' => 0], // ESTUPIDEZ NEGATIVA
                ['cuenta_contable' => $cuentaVentas->codigo, 'debe' => 0, 'haber' => -1000] // ESTUPIDEZ NEGATIVA
            ]
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['detalles.0.debe', 'detalles.1.haber']); 
    }

    // PRUEBA: Reporte de Libro Mayor
    // Comprueba que el sistema maneja correctamente la consulta a una cuenta que no existe.
    public function test_rechaza_generar_libro_mayor_de_cuenta_inexistente()
    {
        $response = $this->actingAs($this->usuarioContador)->getJson('/api/contabilidad/libro-diario?cuenta=999999&desde=2026-01-01&hasta=2026-12-31');

        $response->assertStatus(400)
                 ->assertJsonPath('success', false)
                 ->assertSee('no existe');
    }

    // PRUEBA: Lógica Tributaria (Mes Vacío)
    // El sistema debe impedir que se genere un asiento de cierre de impuestos (F29) si el mes no tiene ventas ni compras.
    public function test_impuestos_rechaza_cierre_f29_sin_movimientos()
    {
        $response = $this->actingAs($this->usuarioContador)->postJson('/api/impuestos/cierre-f29/ejecutar', [
            'mes' => 2,
            'anio' => 2026
        ]);

        $response->assertStatus(422)
                 ->assertSee('No hay movimientos');
    }

    // PRUEBA: Lógica Tributaria (Doble Cierre F29)
    // Protege contra la duplicación del asiento contable de impuestos. Si un mes ya se cerró, se bloquea.
    public function test_impuestos_bloquea_doble_cierre_de_f29()
    {
        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'activo' => true]);

        PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '110001', 'nombre' => 'IVA Crédito', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '110402', 'nombre' => 'Remanente IVA F29', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);

        $prov = Proveedor::create([
            'empresa_id' => $this->empresaA->id, 'codigo_interno' => 'PR-F29', 'rut' => '1.1.1.1-1', 'razon_social' => 'Prov F29', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP'
        ]);

        Factura::create([
            'empresa_id' => $this->empresaA->id, 'proveedor_id' => $prov->id,
            'numero_factura' => 'F-123', 'tipo' => 'COMPRA', 'codigo_unico' => 112233,
            'fecha_emision' => '2026-03-15', 'monto_neto' => 100000, 'monto_iva' => 19000, 'monto_bruto' => 119000, 'estado' => 'REGISTRADA'
        ]);

        $this->actingAs($this->usuarioContador)->postJson('/api/impuestos/cierre-f29/ejecutar', [
            'mes' => 3, 'anio' => 2026
        ])->assertStatus(200);

        $response = $this->actingAs($this->usuarioContador)->postJson('/api/impuestos/cierre-f29/ejecutar', [
            'mes' => 3, 'anio' => 2026
        ]);

        $response->assertStatus(422)
                 ->assertSee('ya ha sido centralizado'); 
    }

    // PRUEBA: Seguridad (IDOR) en Lectura de Asientos
    // Un contador astuto cambia el ID en la URL para intentar descargar el comprobante contable de la competencia.
    public function test_idor_lectura_asientos_contables()
    {
        $empresaB = Empresa::create(['rut' => '44.444.444-4', 'razon_social' => 'Empresa Hacker']);
        $asientoB = AsientoContable::create([
            'empresa_id' => $empresaB->id, 'numero_comprobante' => '999', 'fecha' => now(), 'glosa' => 'Secreto Industrial', 'estado' => 'MAYORIZADO'
        ]);

        $response = $this->actingAs($this->usuarioContador)->getJson($this->rutaAsientos . '/' . $asientoB->id);
        
        $response->assertStatus(404);
    }

    // PRUEBA: Reportes (Filtro Libro Diario)
    // Garantiza que el Libro Diario procese bien las fechas y no traiga información de otros meses.
    public function test_reporte_libro_diario_filtra_correctamente_por_fechas()
    {
        AsientoContable::create([
            'empresa_id' => $this->empresaA->id, 'numero_comprobante' => 'C-001', 'fecha' => '2026-01-10', 'glosa' => 'Asiento Enero', 'estado' => 'MAYORIZADO'
        ]);
        AsientoContable::create([
            'empresa_id' => $this->empresaA->id, 'numero_comprobante' => 'C-002', 'fecha' => '2026-02-15', 'glosa' => 'Asiento Febrero', 'estado' => 'MAYORIZADO'
        ]);

        $response = $this->actingAs($this->usuarioContador)->getJson('/api/contabilidad/libro-diario?desde=2026-02-01&hasta=2026-02-28');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Asiento Febrero', $data[0]['glosa']);
    }

    // PRUEBA: Capa 8 (Asiento Manual Avanzado)
    // Impide que se ingresen glosas sin contexto real.
    public function test_capa8_asiento_avanzado_exige_glosa_descriptiva()
    {
        $cuentaCaja = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1001', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        
        $response = $this->actingAs($this->usuarioContador)->postJson('/api/contabilidad/asiento-manual/avanzado', [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'A',
            'detalles' => [
                ['cuenta_contable' => $cuentaCaja->codigo, 'debe' => 10, 'haber' => 0],
                ['cuenta_contable' => $cuentaCaja->codigo, 'debe' => 0, 'haber' => 10]
            ]
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['glosa']);
    }

    // PRUEBA: Seguridad IDOR (Edición de Cuentas)
    // Evita que manipulando la URL alguien le cambie el nombre a la cuenta de otra empresa.
    public function test_idor_rechaza_editar_cuenta_de_otra_empresa()
    {
        $empresaB = Empresa::create(['rut' => '33.333.333-3', 'razon_social' => 'Empresa B']);
        $cuentaAjena = PlanCuenta::create(['empresa_id' => $empresaB->id, 'codigo' => '9999', 'nombre' => 'Caja Secreta', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->putJson($this->rutaCuentas . '/' . $cuentaAjena->id, [
            'nombre' => 'Caja Hackeada'
        ]);

        $response->assertStatus(422)
                 ->assertSee('no existe');
    }

    // PRUEBA: Reporte Libro Mayor (Cálculo de Saldos)
    // Verifica que la matemática del Debe/Haber arroje el saldo correcto.
    public function test_reporte_libro_mayor_calcula_saldos_correctamente()
    {
        $cuenta = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1111', 'nombre' => 'Banco', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        
        $asiento = AsientoContable::create([
            'empresa_id' => $this->empresaA->id, 'numero_comprobante' => 'C-MAYOR', 'fecha' => now()->format('Y-m-d'), 'glosa' => 'Depósito', 'estado' => 'MAYORIZADO'
        ]);
        
        DetalleAsiento::create([
            'asiento_id' => $asiento->id, 'cuenta_contable' => $cuenta->codigo, 'debe' => 5000, 'haber' => 0, 'fecha' => now()->format('Y-m-d'), 'tipo_operacion' => 'DEBE'
        ]);

        $response = $this->actingAs($this->usuarioContador)->getJson('/api/contabilidad/reportes/libro-mayor?cuenta_contable=' . $cuenta->codigo . '&fecha_inicio=' . now()->subDay()->format('Y-m-d') . '&fecha_fin=' . now()->addDay()->format('Y-m-d'));

        $response->assertStatus(200)
                 ->assertJsonPath('data.saldo_final', 5000);
    }

    // PRUEBA: Tributario - Guardar Mapeo SII
    // Evalúa si las cuentas se vinculan correctamente para la Operación Renta
    public function test_tributario_guarda_mapeo_cuenta_sii_correctamente()
    {
        $cuenta = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '4001', 'nombre' => 'Ventas', 'tipo' => 'INGRESO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->postJson('/api/renta/mapeo', [
            'codigo_cuenta' => $cuenta->codigo,
            'concepto_sii' => 'INGRESOS_GIRO'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mapeo_cuentas_sii', [
            'empresa_id' => $this->empresaA->id,
            'codigo_cuenta' => '4001',
            'concepto_sii' => 'INGRESOS_GIRO'
        ]);
    }

    // PRUEBA: Tributario - Pre-cálculo Renta
    // Verifica que el endpoint retorne la estructura JSON pesada que necesita el Frontend
    public function test_tributario_genera_pre_calculo_renta_con_estructura_valida()
    {
        $response = $this->actingAs($this->usuarioContador)->getJson('/api/renta/pre-calculo/2026');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'anio_comercial', 'anio_tributario', 'regimen_tributario', 'ingresos', 'gastos', 'resultado', 'creditos', 'liquidacion'
                     ]
                 ]);
    }

    // PRUEBA: Capa 8 - Mes y Año absurdos en Impuestos F29
    // Impide que un usuario procese el cierre de impuestos enviando fechas incoherentes.
    public function test_capa8_f29_rechaza_meses_invalidos()
    {
        $response = $this->actingAs($this->usuarioContador)->postJson('/api/impuestos/cierre-f29/ejecutar', [
            'mes' => 15,
            'anio' => 99999
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['mes', 'anio']); 
    }

    // PRUEBA: Integridad Histórica (Cambio de Naturaleza)
    // Impide que un usuario edite el "tipo" de una cuenta (Ej: De ACTIVO a INGRESO) si esta ya tiene asientos vinculados, ya que eso rompería los balances del pasado.
    public function test_rechaza_cambiar_tipo_de_cuenta_si_tiene_movimientos()
    {
        $cuenta = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1111', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        
        $asiento =AsientoContable::create(['empresa_id' => $this->empresaA->id, 'numero_comprobante' => 'C-01', 'fecha' => now(), 'glosa' => 'Prueba', 'estado' => 'MAYORIZADO']);
       DetalleAsiento::create(['asiento_id' => $asiento->id, 'cuenta_contable' => $cuenta->codigo, 'debe' => 1000, 'haber' => 0, 'fecha' => now(), 'tipo_operacion' => 'DEBE']);

        $response = $this->actingAs($this->usuarioContador)->putJson($this->rutaCuentas . '/' . $cuenta->id, [
            'codigo' => '1111',
            'nombre' => 'Caja',
            'tipo' => 'GASTO'
        ]);

        $response->assertStatus(422)
                 ->assertSee('movimientos');
    }

    // PRUEBA: Prevención de Cuentas Zombie
    // No puedes "inactivar" una cuenta si fue usada, porque los reportes antiguos necesitan que siga existiendo lógicamente.
    public function test_rechaza_inactivar_cuenta_con_movimientos()
    {
        $cuenta = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '2222', 'nombre' => 'Banco', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        
        $asiento = AsientoContable::create(['empresa_id' => $this->empresaA->id, 'numero_comprobante' => 'C-02', 'fecha' => now(), 'glosa' => 'Prueba', 'estado' => 'MAYORIZADO']);
        DetalleAsiento::create(['asiento_id' => $asiento->id, 'cuenta_contable' => $cuenta->codigo, 'debe' => 1000, 'haber' => 0, 'fecha' => now(), 'tipo_operacion' => 'DEBE']);

        $response = $this->actingAs($this->usuarioContador)->putJson($this->rutaCuentas . '/' . $cuenta->id, [
            'codigo' => '2222',
            'nombre' => 'Banco',
            'tipo' => 'ACTIVO',
            'activo' => false
        ]);

        $response->assertStatus(422)
                 ->assertSee('movimientos');
    }

    // PRUEBA: Micro-Descuadres (El Hacker de los Céntimos)
    // En finanzas, un descuadre de 0.01 es igual de grave que uno de 1.000.000. El sistema no debe redondear a favor del usuario.
    public function test_rechaza_descuadres_microscopicos_por_decimales()
    {
        $cuentaCaja = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '1001', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        $cuentaVentas = PlanCuenta::create(['empresa_id' => $this->empresaA->id, 'codigo' => '4001', 'nombre' => 'Ventas', 'tipo' => 'INGRESO', 'imputable' => true, 'activo' => true]);

        $response = $this->actingAs($this->usuarioContador)->postJson($this->rutaAsientos, [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'Estafa del centavo',
            'detalles' => [
                ['cuenta_contable' => $cuentaCaja->codigo, 'debe' => 1000.50, 'haber' => 0],
                ['cuenta_contable' => $cuentaVentas->codigo, 'debe' => 0, 'haber' => 1000.49]
            ]
        ]);

        $response->assertStatus(422)
                 ->assertSee('Partida Doble');
    }

    // PRUEBA: Capa 8 de Rendimiento (Consultas Infinitas)
    // Si un usuario pide el Libro Diario sin enviar fechas, el backend colapsará intentando traer 10 años de datos.
    public function test_reporte_libro_diario_exige_rango_de_fechas_obligatorio()
    {
        $response = $this->actingAs($this->usuarioContador)->getJson('/api/contabilidad/libro-diario');

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['desde', 'hasta']);
    }

    // PRUEBA: Seguridad IDOR (Anulaciones)
    // Verifica que el endpoint de Anulaciones bloquee intentos de anular asientos contables de otras empresas.
    public function test_idor_anulacion_de_asiento_contable()
    {
        $empresaB = Empresa::create(['rut' => '66.666.666-6', 'razon_social' => 'Empresa Víctima']);
        $asientoB = AsientoContable::create([
            'empresa_id' => $empresaB->id, 'numero_comprobante' => 'V-999', 'fecha' => now(), 'glosa' => 'Asiento Millonario', 'estado' => 'MAYORIZADO'
        ]);

        $response = $this->actingAs($this->usuarioContador)->postJson('/api/anulacion/anular', [
            'tipo_entidad' => 'asiento_contable',
            'entidad_id' => $asientoB->id,
            'motivo' => 'Hackeo malicioso'
        ]);

        $this->assertNotEquals(200, $response->getStatusCode(), '¡PELIGRO! Se logró anular un asiento de otra empresa.');
        $response->assertStatus(422);
    }
}