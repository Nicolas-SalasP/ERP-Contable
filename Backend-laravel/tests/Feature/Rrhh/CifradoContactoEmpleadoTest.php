<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Services\EmpleadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Fase 2a — CipherSweet: verifica el cifrado en reposo de los campos de
 * contacto del empleado (email, telefono, direccion) usando CipherSweet.
 *
 * Estrategia:
 *  - Se crea un empleado con valores conocidos para los tres campos.
 *  - Se verifica que el valor RAW almacenado en la BD es distinto al
 *    texto en claro (es un ciphertext de CipherSweet).
 *  - Se verifica que al acceder al campo a través del modelo (que
 *    desencripta automáticamente vía UsesCipherSweet) se obtiene el
 *    texto original.
 */
class CifradoContactoEmpleadoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private EmpleadoService $empleados;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empleados = app(EmpleadoService::class);
    }

    public function test_email_se_almacena_cifrado_y_se_descifra_via_modelo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = $this->empleados->crear($empresa->id, [
            'rut' => '12.345.678-9',
            'nombres' => 'Juan',
            'apellido_paterno' => 'Pérez',
            'email' => 'juan.perez@example.com',
        ]);

        // El valor crudo en BD no debe ser el texto en claro
        $crudo = DB::table('empleados')->where('id', $empleado->id)->value('email');
        $this->assertNotNull($crudo, 'El campo email no debe ser null en BD.');
        $this->assertNotEquals(
            'juan.perez@example.com',
            $crudo,
            'El email en BD debe estar cifrado (CipherSweet), no en texto plano.'
        );

        // El accessor del modelo debe retornar el texto original
        $desdeDb = Empleado::find($empleado->id);
        $this->assertEquals(
            'juan.perez@example.com',
            $desdeDb->email,
            'El accessor del modelo debe descifrar el email correctamente.'
        );
    }

    public function test_telefono_se_almacena_cifrado_y_se_descifra_via_modelo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = $this->empleados->crear($empresa->id, [
            'rut' => '11.111.111-1',
            'nombres' => 'Ana',
            'apellido_paterno' => 'González',
            'telefono' => '+56912345678',
        ]);

        $crudo = DB::table('empleados')->where('id', $empleado->id)->value('telefono');
        $this->assertNotNull($crudo, 'El campo telefono no debe ser null en BD.');
        $this->assertNotEquals(
            '+56912345678',
            $crudo,
            'El teléfono en BD debe estar cifrado (CipherSweet), no en texto plano.'
        );

        $desdeDb = Empleado::find($empleado->id);
        $this->assertEquals(
            '+56912345678',
            $desdeDb->telefono,
            'El accessor del modelo debe descifrar el teléfono correctamente.'
        );
    }

    public function test_direccion_se_almacena_cifrada_y_se_descifra_via_modelo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = $this->empleados->crear($empresa->id, [
            'rut' => '22.222.222-2',
            'nombres' => 'Pedro',
            'apellido_paterno' => 'Ramírez',
            'direccion' => 'Av. Providencia 1234, Santiago',
        ]);

        $crudo = DB::table('empleados')->where('id', $empleado->id)->value('direccion');
        $this->assertNotNull($crudo, 'El campo direccion no debe ser null en BD.');
        $this->assertNotEquals(
            'Av. Providencia 1234, Santiago',
            $crudo,
            'La dirección en BD debe estar cifrada (CipherSweet), no en texto plano.'
        );

        $desdeDb = Empleado::find($empleado->id);
        $this->assertEquals(
            'Av. Providencia 1234, Santiago',
            $desdeDb->direccion,
            'El accessor del modelo debe descifrar la dirección correctamente.'
        );
    }

    public function test_los_tres_campos_se_cifran_y_descifran_juntos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = $this->empleados->crear($empresa->id, [
            'rut' => '33.333.333-3',
            'nombres' => 'María',
            'apellido_paterno' => 'López',
            'email' => 'maria.lopez@empresa.cl',
            'telefono' => '+56922334455',
            'direccion' => 'Calle Falsa 123, Valparaíso',
        ]);

        // Verificar que los tres campos están cifrados en BD
        $fila = DB::table('empleados')->where('id', $empleado->id)->first();
        $this->assertNotEquals('maria.lopez@empresa.cl', $fila->email);
        $this->assertNotEquals('+56922334455', $fila->telefono);
        $this->assertNotEquals('Calle Falsa 123, Valparaíso', $fila->direccion);

        // Verificar descifrado vía modelo
        $desdeDb = Empleado::find($empleado->id);
        $this->assertEquals('maria.lopez@empresa.cl', $desdeDb->email);
        $this->assertEquals('+56922334455', $desdeDb->telefono);
        $this->assertEquals('Calle Falsa 123, Valparaíso', $desdeDb->direccion);
    }

    public function test_campos_nulos_siguen_siendo_nulos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = $this->empleados->crear($empresa->id, [
            'rut' => '44.444.444-4',
            'nombres' => 'Luis',
            'apellido_paterno' => 'Morales',
            // email, telefono y direccion se omiten — son nullable
        ]);

        $fila = DB::table('empleados')->where('id', $empleado->id)->first();
        $this->assertNull($fila->email);
        $this->assertNull($fila->telefono);
        $this->assertNull($fila->direccion);

        $desdeDb = Empleado::find($empleado->id);
        $this->assertNull($desdeDb->email);
        $this->assertNull($desdeDb->telefono);
        $this->assertNull($desdeDb->direccion);
    }

    public function test_ciphersweet_coexiste_con_cifrado_crypt_de_datos_bancarios(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = $this->empleados->crear($empresa->id, [
            'rut' => '55.555.555-5',
            'nombres' => 'Sofía',
            'apellido_paterno' => 'Vega',
            'email' => 'sofia.vega@empresa.cl',
            'banco_nombre' => 'Banco Estado',
            'banco_tipo_cuenta' => 'CUENTA_VISTA',
            'banco_numero_cuenta' => '00099988877',
        ]);

        // CipherSweet cifró el email
        $crudo = DB::table('empleados')->where('id', $empleado->id)->value('email');
        $this->assertNotEquals('sofia.vega@empresa.cl', $crudo);

        // Crypt sigue funcionando para datos bancarios
        $desdeDb = Empleado::find($empleado->id);
        $this->assertEquals('sofia.vega@empresa.cl', $desdeDb->email);
        $this->assertEquals('00099988877', $desdeDb->banco_numero_cuenta);
    }
}
