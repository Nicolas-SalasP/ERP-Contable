<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Fase 2c — CipherSweet: verifica que los campos con tipos Eloquent fuertemente
 * tipeados (fecha_nacimiento con cast 'date' y sueldo_base con cast 'decimal:2')
 * se almacenan cifrados en BD y que el round-trip preserva el tipo y el valor.
 *
 * Casos cubiertos:
 *  - fecha_nacimiento: el valor crudo en BD es ciphertext; $empleado->fecha_nacimiento
 *    devuelve una instancia Carbon igual a la fecha original; null sigue siendo null.
 *  - sueldo_base: el valor crudo en BD es ciphertext; (float) $contrato->sueldo_base
 *    es exactamente 1234567.89; funciona en operaciones aritméticas; null sigue null.
 */
class CifradoCamposTipadosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // ── fecha_nacimiento (Empleado, cast 'date') ──────────────────────────────

    public function test_fecha_nacimiento_se_almacena_cifrada_en_bd(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id'        => $empresa->id,
            'rut'               => '12.345.678-9',
            'nombres'           => 'María',
            'apellido_paterno'  => 'González',
            'fecha_nacimiento'  => '1990-05-15',
        ]);

        $crudo = DB::table('empleados')->where('id', $empleado->id)->value('fecha_nacimiento');

        $this->assertNotNull($crudo, 'El campo fecha_nacimiento no debe ser null en BD.');
        $this->assertNotEquals(
            '1990-05-15',
            $crudo,
            'fecha_nacimiento en BD debe ser ciphertext (CipherSweet), no fecha en claro.'
        );
        // El ciphertext de CipherSweet es considerablemente más largo que una fecha
        $this->assertGreaterThan(20, strlen($crudo),
            'El ciphertext debe ser más largo que una cadena de fecha normal.'
        );
    }

    public function test_fecha_nacimiento_round_trip_devuelve_instancia_carbon(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '11.111.111-1',
            'nombres'          => 'Juan',
            'apellido_paterno' => 'Pérez',
            'fecha_nacimiento' => '1985-12-31',
        ]);

        $desdeDb = $empleado->fresh();

        // El cast 'date' debe re-tipar el string descifrado como instancia Carbon
        $this->assertInstanceOf(
            Carbon::class,
            $desdeDb->fecha_nacimiento,
            '$empleado->fecha_nacimiento debe ser una instancia Carbon (cast date activo).'
        );

        // El valor debe coincidir con la fecha original
        $this->assertTrue(
            $desdeDb->fecha_nacimiento->isSameDay(Carbon::parse('1985-12-31')),
            'La fecha de nacimiento descifrada debe ser la misma que la original.'
        );
        $this->assertEquals(1985, $desdeDb->fecha_nacimiento->year);
        $this->assertEquals(12,   $desdeDb->fecha_nacimiento->month);
        $this->assertEquals(31,   $desdeDb->fecha_nacimiento->day);
    }

    public function test_fecha_nacimiento_null_permanece_null(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '22.222.222-2',
            'nombres'          => 'Ana',
            'apellido_paterno' => 'Ramírez',
            'fecha_nacimiento' => null,
        ]);

        // En BD debe ser null (CipherSweet addOptionalTextField deja null sin cifrar)
        $crudo = DB::table('empleados')->where('id', $empleado->id)->value('fecha_nacimiento');
        $this->assertNull($crudo, 'fecha_nacimiento null debe almacenarse como null en BD.');

        // Vía modelo también debe ser null
        $desdeDb = $empleado->fresh();
        $this->assertNull($desdeDb->fecha_nacimiento,
            'El modelo debe devolver null cuando fecha_nacimiento es null.'
        );
    }

    // ── sueldo_base (Contrato, cast 'decimal:2') ──────────────────────────────

    public function test_sueldo_base_se_almacena_cifrado_en_bd(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '33.333.333-3',
            'nombres'          => 'Pedro',
            'apellido_paterno' => 'Soto',
        ]);

        $contrato = Contrato::create([
            'empresa_id'        => $empresa->id,
            'empleado_id'       => $empleado->id,
            'tipo'              => 'INDEFINIDO',
            'fecha_inicio'      => '2024-01-01',
            'sueldo_base'       => 1234567.89,
            'horas_semana'      => 42,
            'estado'            => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $crudo = DB::table('contratos')->where('id', $contrato->id)->value('sueldo_base');

        $this->assertNotNull($crudo, 'sueldo_base no debe ser null en BD.');
        $this->assertNotEquals(
            '1234567.89',
            $crudo,
            'sueldo_base en BD debe ser ciphertext (CipherSweet), no número en claro.'
        );
        $this->assertNotEquals(
            '1234567',
            $crudo,
            'sueldo_base en BD no debe ser el número sin decimales.'
        );
        // El ciphertext es mucho más largo que el número
        $this->assertGreaterThan(20, strlen($crudo),
            'El ciphertext debe ser más largo que un número en claro.'
        );
    }

    public function test_sueldo_base_round_trip_preserva_valor_exacto(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '44.444.444-4',
            'nombres'          => 'Luis',
            'apellido_paterno' => 'Morales',
        ]);

        $contrato = Contrato::create([
            'empresa_id'        => $empresa->id,
            'empleado_id'       => $empleado->id,
            'tipo'              => 'INDEFINIDO',
            'fecha_inicio'      => '2024-01-01',
            'sueldo_base'       => 1234567.89,
            'horas_semana'      => 42,
            'estado'            => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $desdeDb = $contrato->fresh();

        // (float) $contrato->sueldo_base debe ser exactamente 1234567.89
        $this->assertEquals(
            1234567.89,
            (float) $desdeDb->sueldo_base,
            'sueldo_base descifrado debe redondear a exactamente 1234567.89.'
        );
    }

    public function test_sueldo_base_funciona_en_operaciones_aritmeticas(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '55.555.555-5',
            'nombres'          => 'Sofía',
            'apellido_paterno' => 'Vega',
        ]);

        $contrato = Contrato::create([
            'empresa_id'        => $empresa->id,
            'empleado_id'       => $empleado->id,
            'tipo'              => 'INDEFINIDO',
            'fecha_inicio'      => '2024-01-01',
            'sueldo_base'       => 900000,
            'horas_semana'      => 42,
            'estado'            => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $desdeDb = $contrato->fresh();

        // Operación aritmética típica de LiquidacionService / VacacionesService
        $diario = $desdeDb->sueldo_base / 30;
        $this->assertEqualsWithDelta(30000.0, (float) $diario, 0.01,
            'sueldo_base / 30 debe dar 30000 con delta 0.01.'
        );

        // Operación con cast (float) como en FiniquitoService
        $comoFloat = (float) $desdeDb->sueldo_base;
        $this->assertEquals(900000.0, $comoFloat,
            '(float) $contrato->sueldo_base debe devolver exactamente 900000.0.'
        );
    }

    public function test_sueldo_base_actualizar_contrato_no_corrompe_valor(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '66.666.666-6',
            'nombres'          => 'Carlos',
            'apellido_paterno' => 'Fuentes',
        ]);

        $contrato = Contrato::create([
            'empresa_id'        => $empresa->id,
            'empleado_id'       => $empleado->id,
            'tipo'              => 'INDEFINIDO',
            'fecha_inicio'      => '2024-01-01',
            'sueldo_base'       => 800000,
            'horas_semana'      => 42,
            'estado'            => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        // Simula la operación de firmar finiquito (update del estado del contrato)
        $contrato->update([
            'estado'            => 'TERMINADO',
            'es_contrato_activo' => false,
        ]);

        // Después del update, sueldo_base debe seguir intacto
        $desdeDb = Contrato::find($contrato->id);
        $this->assertEquals(800000.0, (float) $desdeDb->sueldo_base,
            'sueldo_base debe preservar su valor después de actualizar otros campos del contrato.'
        );
        $this->assertEquals('TERMINADO', $desdeDb->estado,
            'El campo estado debe haberse actualizado correctamente.'
        );
    }
}
