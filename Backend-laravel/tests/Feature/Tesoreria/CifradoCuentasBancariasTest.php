<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Pais;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;

/**
 * Prueba que numero_cuenta se almacena cifrado en ambas tablas (Ley 21.719).
 * Sigue el patrón de EmpleadoContratoTest: aserta texto cifrado en la base de
 * datos vía DB::table() y descifrado correcto vía el accessor del modelo.
 */
class CifradoCuentasBancariasTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        EstadoSuscripcion::create(['nombre' => 'Activa']);
        Rol::create(['nombre' => 'Admin', 'jerarquia' => 100]);

        $this->empresa = Empresa::create([
            'rut'          => '11.111.111-1',
            'razon_social' => 'Empresa Test',
        ]);

        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'activo' => true]);

        $this->proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'rut'            => '1-9',
            'razon_social'   => 'Proveedor Test',
            'codigo_interno' => 'PT',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    public function test_numero_cuenta_empresa_se_almacena_cifrado(): void
    {
        $cuenta = CuentaBancariaEmpresa::create([
            'empresa_id'    => $this->empresa->id,
            'banco'         => 'Banco Estado',
            'tipo_cuenta'   => 'Corriente',
            'numero_cuenta' => '12345678',
            'titular'       => 'Empresa Test',
            'rut_titular'   => '11.111.111-1',
        ]);

        // El valor crudo en la base de datos NO debe ser el texto en claro.
        $crudo = DB::table('cuentas_bancarias_empresa')
            ->where('id', $cuenta->id)
            ->value('numero_cuenta');

        $this->assertNotEquals('12345678', $crudo, 'El número de cuenta NO debe quedar en texto claro en la BD.');
        $this->assertNotEmpty($crudo, 'El campo cifrado no debe estar vacío.');

        // El accessor del modelo debe descifrar correctamente.
        $cuenta->refresh();
        $this->assertSame('12345678', $cuenta->numero_cuenta, 'El accessor debe devolver el valor descifrado.');
    }

    public function test_numero_cuenta_proveedor_se_almacena_cifrado(): void
    {
        $cuenta = CuentaBancariaProveedor::create([
            'proveedor_id'  => $this->proveedor->id,
            'banco'         => 'Santander',
            'numero_cuenta' => '12345678',
            'tipo_cuenta'   => 'Vista',
            'pais_iso'      => 'CL',
        ]);

        // El valor crudo en la base de datos NO debe ser el texto en claro.
        $crudo = DB::table('cuentas_bancarias_proveedores')
            ->where('id', $cuenta->id)
            ->value('numero_cuenta');

        $this->assertNotEquals('12345678', $crudo, 'El número de cuenta de proveedor NO debe quedar en texto claro.');
        $this->assertNotEmpty($crudo, 'El campo cifrado no debe estar vacío.');

        // El accessor del modelo debe descifrar correctamente.
        $cuenta->refresh();
        $this->assertSame('12345678', $cuenta->numero_cuenta, 'El accessor debe devolver el valor descifrado.');
    }

    public function test_dos_registros_con_mismo_numero_producen_ciphertexts_distintos(): void
    {
        // El cifrado usa IV aleatorio; cada registro debe tener un ciphertext diferente.
        CuentaBancariaEmpresa::create([
            'empresa_id'    => $this->empresa->id,
            'banco'         => 'Banco A',
            'tipo_cuenta'   => 'Corriente',
            'numero_cuenta' => '99999999',
            'titular'       => 'T',
            'rut_titular'   => 'R',
        ]);

        CuentaBancariaEmpresa::create([
            'empresa_id'    => $this->empresa->id,
            'banco'         => 'Banco B',
            'tipo_cuenta'   => 'Vista',
            'numero_cuenta' => '99999999',
            'titular'       => 'T',
            'rut_titular'   => 'R',
        ]);

        $valores = DB::table('cuentas_bancarias_empresa')
            ->pluck('numero_cuenta')
            ->toArray();

        $this->assertCount(2, $valores);
        $this->assertNotEquals($valores[0], $valores[1], 'Ciphertexts del mismo plaintext deben diferir (IV aleatorio).');
    }
}
