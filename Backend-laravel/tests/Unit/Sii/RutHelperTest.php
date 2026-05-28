<?php

namespace Tests\Unit\Sii;

use App\Domains\Sii\Support\RutHelper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RutHelperTest extends TestCase
{
    public function test_normalizar_acepta_rut_con_puntos_y_guion(): void
    {
        $this->assertSame('12345678-5', RutHelper::normalizar('12.345.678-5'));
    }

    public function test_normalizar_acepta_rut_sin_puntos_con_guion(): void
    {
        $this->assertSame('12345678-5', RutHelper::normalizar('12345678-5'));
    }

    public function test_normalizar_pone_dv_en_mayuscula(): void
    {
        $this->assertSame('12345678-K', RutHelper::normalizar('12345678-k'));
    }

    public function test_normalizar_acepta_rut_corto(): void
    {
        $this->assertSame('1-9', RutHelper::normalizar('1-9'));
    }

    public function test_normalizar_lanza_excepcion_con_string_no_numerico(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RutHelper::normalizar('abc');
    }

    public function test_normalizar_lanza_excepcion_con_string_vacio(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RutHelper::normalizar('');
    }

    public function test_validar_acepta_rut_de_prueba_oficial_sii(): void
    {
        $this->assertTrue(RutHelper::validar('76086428-5'));
    }

    public function test_validar_acepta_rut_repetido_con_dv_correcto(): void
    {
        $this->assertTrue(RutHelper::validar('11111111-1'));
    }

    public function test_validar_rechaza_rut_con_dv_incorrecto(): void
    {
        $this->assertFalse(RutHelper::validar('11111111-9'));
    }

    public function test_validar_rechaza_rut_cero(): void
    {
        $this->assertFalse(RutHelper::validar('0-0'));
    }

    public function test_calcular_dv_para_rut_de_prueba_sii(): void
    {
        $this->assertSame('5', RutHelper::calcularDv(76086428));
    }

    public function test_calcular_dv_para_rut_de_un_digito(): void
    {
        $this->assertSame('9', RutHelper::calcularDv(1));
    }

    public function test_formatear_con_puntos(): void
    {
        $this->assertSame('76.086.428-5', RutHelper::formatear('76086428-5', true));
    }

    public function test_formatear_sin_puntos(): void
    {
        $this->assertSame('76086428-5', RutHelper::formatear('76086428-5', false));
    }
}
