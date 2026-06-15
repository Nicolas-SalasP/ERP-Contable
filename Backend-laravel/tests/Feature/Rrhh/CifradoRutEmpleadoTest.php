<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\CargaFamiliar;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Services\EmpleadoService;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Fase 2b — CipherSweet: verifica el cifrado en reposo del RUT del empleado
 * con blind index para búsqueda exacta y control de unicidad, y el cifrado del
 * RUT de cargas familiares sin blind index.
 *
 * Casos cubiertos:
 *  - RUT almacenado cifrado (el valor bruto de BD es ciphertext, no texto plano).
 *  - El modelo desencripta el RUT automáticamente.
 *  - Búsqueda por RUT completo normalizado devuelve el empleado.
 *  - Búsqueda por fragmento parcial de RUT NO devuelve resultados (tradeoff aceptado).
 *  - Unicidad: mismo RUT en misma empresa lanza excepción.
 *  - Mismo RUT en empresa diferente es permitido.
 *  - RUT de cargas familiares almacenado cifrado y legible vía modelo.
 */
class CifradoRutEmpleadoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private EmpleadoService $empleados;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empleados = app(EmpleadoService::class);
    }

    // ── Cifrado en reposo del RUT ────────────────────────────────────────────

    public function test_rut_se_almacena_cifrado_y_el_modelo_lo_descifra(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $rutOriginal = '12.345.678-9';
        $rutNormal   = RutHelper::normalizar($rutOriginal);

        $empleado = $this->empleados->crear($empresa->id, [
            'rut'               => $rutOriginal,
            'nombres'           => 'Juan',
            'apellido_paterno'  => 'Pérez',
        ]);

        // El valor bruto en BD debe ser ciphertext (no texto plano)
        $crudo = DB::table('empleados')->where('id', $empleado->id)->value('rut');
        $this->assertNotNull($crudo, 'El campo rut no debe ser null en BD.');
        $this->assertNotEquals(
            $rutNormal,
            $crudo,
            'El RUT en BD debe estar cifrado (CipherSweet), no en texto plano.'
        );

        // El modelo lo desencripta devolviendo la forma normalizada
        $desdeDb = Empleado::find($empleado->id);
        $this->assertEquals(
            $rutNormal,
            $desdeDb->rut,
            'El accessor del modelo debe descifrar el RUT al valor normalizado.'
        );
    }

    // ── Búsqueda exacta por RUT ──────────────────────────────────────────────

    public function test_busqueda_por_rut_completo_normalizado_encuentra_empleado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->empleados->crear($empresa->id, [
            'rut'               => '12.345.678-9',
            'nombres'           => 'Juan',
            'apellido_paterno'  => 'Pérez',
        ]);

        // Buscar con RUT en formato con puntos → normalizado internamente → blind index
        $resultado = $this->empleados->listar($empresa->id, ['buscar' => '12.345.678-9']);
        $this->assertGreaterThanOrEqual(1, $resultado->total(),
            'La búsqueda por RUT completo debe devolver al menos un resultado.');

        // También funciona con el RUT ya normalizado
        $resultado2 = $this->empleados->listar($empresa->id, ['buscar' => '12345678-9']);
        $this->assertGreaterThanOrEqual(1, $resultado2->total(),
            'La búsqueda con RUT normalizado también debe funcionar.');
    }

    public function test_busqueda_por_fragmento_parcial_de_rut_no_devuelve_resultados(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->empleados->crear($empresa->id, [
            'rut'               => '12.345.678-9',
            'nombres'           => 'ZZZ-NombreÚnico',   // nombre que no coincide con fragmento
            'apellido_paterno'  => 'ZZZ-Único',
        ]);

        // Una búsqueda por fragmento numérico del RUT no debe encontrar el empleado
        // (el término "12345" no es un RUT parseable, sólo se busca por nombre)
        $resultado = $this->empleados->listar($empresa->id, ['buscar' => '12345']);
        $this->assertEquals(0, $resultado->total(),
            'La búsqueda por fragmento parcial de RUT no debe devolver resultados (tradeoff de cifrado).');
    }

    // ── Unicidad vía blind index ────────────────────────────────────────────

    public function test_crear_segundo_empleado_con_mismo_rut_en_misma_empresa_falla(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->empleados->crear($empresa->id, [
            'rut'              => '11.111.111-1',
            'nombres'          => 'Ana',
            'apellido_paterno' => 'González',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ya existe un empleado con el RUT');

        $this->empleados->crear($empresa->id, [
            'rut'              => '11.111.111-1',   // mismo RUT, diferente formato: sin puntos
            'nombres'          => 'Otra',
            'apellido_paterno' => 'Persona',
        ]);
    }

    public function test_mismo_rut_en_empresa_diferente_es_permitido(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin([], ['email' => 'a@test.cl']);
        [$empresaB] = $this->crearEmpresaConAdmin([], ['email' => 'b@test.cl']);

        $this->empleados->crear($empresaA->id, [
            'rut'              => '11.111.111-1',
            'nombres'          => 'Ana',
            'apellido_paterno' => 'González',
        ]);

        // Mismo RUT en empresa diferente no debe lanzar excepción
        $empleadoB = $this->empleados->crear($empresaB->id, [
            'rut'              => '11.111.111-1',
            'nombres'          => 'Ana',
            'apellido_paterno' => 'López',
        ]);

        $this->assertNotNull($empleadoB->id,
            'El mismo RUT puede existir en otra empresa (aislamiento multitenant).');
    }

    // ── Cifrado de RUT en cargas familiares ──────────────────────────────────

    public function test_rut_carga_familiar_se_almacena_cifrado_y_es_legible_via_modelo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = $this->empleados->crear($empresa->id, [
            'rut'              => '22.222.222-2',
            'nombres'          => 'María',
            'apellido_paterno' => 'Soto',
        ]);

        $rutCarga = '5.555.555-5';

        $carga = CargaFamiliar::create([
            'empresa_id'    => $empresa->id,
            'empleado_id'   => $empleado->id,
            'rut'           => $rutCarga,
            'nombre'        => 'Hijo Test',
            'tipo'          => 'HIJO',
            'fecha_nacimiento' => '2015-06-01',
            'vigente_desde' => '2020-01-01',
            'activa'        => true,
        ]);

        // El valor bruto en BD debe ser ciphertext
        $crudo = DB::table('cargas_familiares')->where('id', $carga->id)->value('rut');
        $this->assertNotNull($crudo, 'El RUT de carga familiar no debe ser null en BD.');
        $this->assertNotEquals(
            $rutCarga,
            $crudo,
            'El RUT de carga familiar debe estar cifrado en BD.'
        );

        // El modelo lo desencripta correctamente
        $desdeDb = CargaFamiliar::find($carga->id);
        $this->assertEquals(
            $rutCarga,
            $desdeDb->rut,
            'El modelo CargaFamiliar debe descifrar el RUT automáticamente.'
        );
    }

    public function test_rut_carga_familiar_nullable_permanece_null(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = $this->empleados->crear($empresa->id, [
            'rut'              => '33.333.333-3',
            'nombres'          => 'Pedro',
            'apellido_paterno' => 'Ramírez',
        ]);

        $carga = CargaFamiliar::create([
            'empresa_id'    => $empresa->id,
            'empleado_id'   => $empleado->id,
            'rut'           => null,          // null es válido
            'nombre'        => 'Cónyuge Sin RUT',
            'tipo'          => 'CONYUGE',
            'vigente_desde' => '2023-01-01',
            'activa'        => true,
        ]);

        $crudo = DB::table('cargas_familiares')->where('id', $carga->id)->value('rut');
        $this->assertNull($crudo, 'Un RUT null debe permanecer null en BD (no cifrado).');

        $desdeDb = CargaFamiliar::find($carga->id);
        $this->assertNull($desdeDb->rut, 'El modelo debe devolver null cuando el RUT es null.');
    }
}
