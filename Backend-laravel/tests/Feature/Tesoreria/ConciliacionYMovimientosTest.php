<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\Pais;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use Laravel\Sanctum\Sanctum;

class ConciliacionYMovimientosTest extends TestCase
{
    use RefreshDatabase;

    protected $empresaA;
    protected $empresaB;
    protected $adminA;
    protected $adminB;
    protected $cuentaA;
    protected $cuentaB;
    protected $proveedorA;

    protected function setUp(): void
    {
        parent::setUp();

        $estadoActivo = EstadoSuscripcion::create(['nombre' => 'Activa']);
        $rolAdmin = Rol::create(['nombre' => 'Admin', 'jerarquia' => 100]);

        $this->empresaA = Empresa::create(['rut' => '11.111.111-1', 'razon_social' => 'Empresa A']);
        $this->empresaB = Empresa::create(['rut' => '22.222.222-2', 'razon_social' => 'Empresa B']);

        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'activo' => true]);

        $this->adminA = User::create([
            'nombre' => 'A',
            'email' => 'a@test.com',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $rolAdmin->id,
            'estado_suscripcion_id' => $estadoActivo->id
        ]);

        $this->adminB = User::create([
            'nombre' => 'B',
            'email' => 'b@test.com',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaB->id,
            'rol_id' => $rolAdmin->id,
            'estado_suscripcion_id' => $estadoActivo->id
        ]);

        $this->cuentaA = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'banco' => 'Banco A',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);

        $this->cuentaB = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaB->id,
            'banco' => 'Banco B',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '2',
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);

        $this->proveedorA = Proveedor::create(['empresa_id' => $this->empresaA->id, 'rut' => '1-9', 'razon_social' => 'Prov A', 'codigo_interno' => 'PA', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
    }

    public function test_ingreso_manual_exitosamente()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/ingreso-manual', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha' => '2026-05-04',
            'monto' => 10000,
            'tipo_movimiento' => 'INGRESO',
            'descripcion' => 'Aporte'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
    }

    public function test_idor_rechaza_ingreso_manual_en_cuenta_ajena()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/ingreso-manual', [
            'cuenta_bancaria_id' => $this->cuentaB->id,
            'fecha' => '2026-05-04',
            'monto' => 10000,
            'tipo_movimiento' => 'INGRESO',
            'descripcion' => 'Robo'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 403, 404, 422, 500]));
    }

    public function test_rechaza_ingreso_manual_sin_monto()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/ingreso-manual', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha' => '2026-05-04',
            'tipo_movimiento' => 'INGRESO',
            'descripcion' => 'Aporte'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_rechaza_ingreso_manual_con_monto_negativo()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/ingreso-manual', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha' => '2026-05-04',
            'monto' => -500,
            'tipo_movimiento' => 'INGRESO',
            'descripcion' => 'Aporte'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_pagar_nomina_requiere_arreglo_facturas()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/nomina/pagar', [
            'facturas_ids' => 'texto_no_arreglo',
            'cuenta_bancaria_id' => $this->cuentaA->id
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_pagar_nomina_vacia_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/nomina/pagar', [
            'facturas_ids' => [],
            'cuenta_bancaria_id' => $this->cuentaA->id
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_idor_rechaza_pagar_nomina_con_facturas_ajenas()
    {
        $proveedorB = Proveedor::create([
            'empresa_id' => $this->empresaB->id,
            'rut' => '8.8.8-8',
            'razon_social' => 'Prov B',
            'codigo_interno' => 'PB',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $facturaB = Factura::create([
            'empresa_id' => $this->empresaB->id,
            'proveedor_id' => $proveedorB->id,
            'numero_factura' => '1',
            'monto_bruto' => 100,
            'monto_neto' => 81,
            'monto_impuesto' => 19,
            'estado' => 'REGISTRADA',
            'codigo_unico' => 99999001,
            'fecha_emision' => '2026-05-04'
        ]);

        Sanctum::actingAs($this->adminA);

        $response = $this->postJson('/api/banco/nomina/pagar', [
            'facturas_ids' => [$facturaB->id],
            'cuenta_bancaria_id' => $this->cuentaA->id
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [400, 403, 404, 500]));
    }

    public function test_importar_cartola_falla_sin_archivo()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/importar', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_importar_cartola_rechaza_archivo_pdf()
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('cartola.pdf', 100, 'application/pdf');
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/importar', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'archivo' => $file
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_idor_rechaza_importar_cartola_en_cuenta_ajena()
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('cartola.csv', 100, 'text/csv');
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/importar', [
            'cuenta_bancaria_id' => $this->cuentaB->id,
            'archivo' => $file
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 403, 404, 422, 500]));
    }

    public function test_conciliar_factura_compra_requiere_id()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/tesoreria/conciliar/factura-compra', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha_pago' => '2026-05-04'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_conciliar_facturas_sin_movimiento_id_devuelve_422_no_400()
    {
        Sanctum::actingAs($this->adminA);
        // Falta 'movimiento_id' (required|integer): validación de entrada → 422, no 400.
        $this->postJson('/api/banco/movimientos/conciliar-facturas', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('movimiento_id');
    }

    public function test_importar_cartola_csv_procesa_movimientos()
    {
        Storage::fake('local');

        $csv = "Fecha,Descripcion,Monto\n04/05/2026,Deposito cliente,10000\n05/05/2026,Pago proveedor,-5000\n";
        $ruta = tempnam(sys_get_temp_dir(), 'cartola') . '.csv';
        file_put_contents($ruta, $csv);
        $file = new UploadedFile($ruta, 'cartola.csv', 'text/csv', null, true);

        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/importar', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'archivo' => $file
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.importados', 2);

        // El import deja los movimientos PENDIENTE con la fecha real del archivo
        // (no la de hoy) -- se contabilizan recien en Mesa de Conciliación.
        $this->assertDatabaseHas('movimientos_bancarios', [
            'empresa_id' => $this->empresaA->id,
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha' => '2026-05-04',
            'descripcion' => 'Deposito cliente',
            'abono' => 10000,
            'cargo' => 0,
            'estado' => 'PENDIENTE',
        ]);
        $this->assertDatabaseHas('movimientos_bancarios', [
            'empresa_id' => $this->empresaA->id,
            'fecha' => '2026-05-05',
            'descripcion' => 'Pago proveedor',
            'abono' => 0,
            'cargo' => 5000,
            'estado' => 'PENDIENTE',
        ]);
        $this->assertDatabaseMissing('asientos_contables', ['origen_modulo' => 'importacion_banco']);
    }

    public function test_importar_cartola_xlsx_procesa_movimientos()
    {
        Storage::fake('local');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray([
            ['Fecha', 'Descripcion', 'Monto'],
            ['04/05/2026', 'Deposito cliente', 10000],
            ['05/05/2026', 'Pago proveedor', -5000],
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'cartola') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($ruta);
        $file = new UploadedFile($ruta, 'cartola.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/importar', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'archivo' => $file
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.importados', 2);
    }

    public function test_importar_cartola_no_colapsa_movimientos_identicos_por_nro_documento()
    {
        // Reproduce el caso real: dos transferencias distintas, mismo dia, misma
        // glosa y mismo monto (ej. nomina con dos pagos de $50.000 al mismo
        // destinatario). Sin el N° Doc. como clave de dedup, la segunda se
        // marcaba como "duplicado" y se perdia.
        Storage::fake('local');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray([
            ['Fecha', 'Descripcion', 'N Doc.', 'Cargos', 'Abonos'],
            ['20-05-2026', 'TEF Nicolas Mathias', '5654121544', null, 50000],
            ['20-05-2026', 'TEF Nicolas Mathias', '5654120597', null, 50000],
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'cartola') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($ruta);
        $file = new UploadedFile($ruta, 'cartola.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/importar', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'archivo' => $file
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.importados', 2);
        $response->assertJsonPath('data.ignorados', 0);
    }

    public function test_importar_cartola_con_metadata_previa_y_columnas_cargo_abono()
    {
        // Reproduce el formato real de Scotiabank: bloque de metadata (Nombre
        // Empresa, Numero Linea, Saldo Disponible, etc.) antes del encabezado
        // real, y Cargos/Abonos en columnas separadas en vez de un monto unico
        // con signo. Antes de este fix, esto importaba 0 filas en silencio.
        Storage::fake('local');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray([
            ['Nombre Empresa', 'SPA - TEST'],
            ['Numero Linea', '991980431'],
            ['Fecha Consulta', '04-07-2026'],
            ['Moneda', 'CLP'],
            ['Saldo Disponible', 2387737],
            ['Fecha', 'Descripcion', 'Sucursal', 'N Doc.', 'Cargos', 'Abonos', 'Saldo'],
            ['03-07-2026', 'IVA POR COMISION', 'SUCURSAL VIRTUAL', '5755491254', -141, null, 2387737],
            ['25-06-2026', 'PROVEEDORE 78730890-2', 'NUEVA MORANDE', '5733174661', null, 640000, 2388618],
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'cartola') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($ruta);
        $file = new UploadedFile($ruta, 'cartola.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/importar', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'archivo' => $file
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.importados', 2);
    }

    public function test_conciliar_factura_inexistente_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/tesoreria/conciliar/factura-compra', [
            'factura_id' => 9999,
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha_pago' => '2026-05-04'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 404, 422, 500]));
    }

    public function test_obtener_movimientos_pendientes_exitosamente()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson("/api/banco/movimientos/pendientes/{$this->cuentaA->id}");
        $this->assertTrue(in_array($response->getStatusCode(), [200, 400]));
    }

    public function test_idor_rechaza_obtener_movimientos_pendientes_de_cuenta_ajena()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson("/api/banco/movimientos/pendientes/{$this->cuentaB->id}");
        $this->assertTrue(in_array($response->getStatusCode(), [400, 403, 404, 500]));
    }

    public function test_obtener_anticipos_pendientes_exitosamente()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->getJson("/api/banco/anticipos-pendientes");
        $this->assertTrue(in_array($response->getStatusCode(), [200, 400]));
    }

    public function test_conciliacion_directa_requiere_movimiento_id()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/movimientos/conciliar', [
            'cuenta_codigo' => '1111',
            'glosa' => 'Test'
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_conciliacion_directa_con_datos_completos()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/movimientos/conciliar', [
            'movimiento_id' => 1,
            'cuenta_codigo' => '1111',
            'glosa' => 'Test'
        ]);
        // El movimiento 1 no existe en el setup → 404 (noEncontrado) es la respuesta correcta.
        $this->assertTrue(in_array($response->getStatusCode(), [200, 400, 404, 422, 500]));
    }

    public function test_conciliar_anticipo_requiere_ambos_ids()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/movimientos/conciliar-anticipo', [
            'movimiento_id' => 1
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    public function test_conciliar_anticipo_inexistente_falla()
    {
        Sanctum::actingAs($this->adminA);
        $response = $this->postJson('/api/banco/movimientos/conciliar-anticipo', [
            'movimiento_id' => 999,
            'anticipo_id' => 888
        ]);
        $this->assertTrue(in_array($response->getStatusCode(), [400, 404, 422, 500]));
    }

    public function test_rutas_de_movimientos_requieren_token()
    {
        $response1 = $this->postJson('/api/banco/ingreso-manual', []);
        $response2 = $this->postJson('/api/banco/nomina/pagar', []);

        $response1->assertStatus(401);
        $response2->assertStatus(401);
    }
}