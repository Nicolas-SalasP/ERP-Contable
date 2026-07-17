<?php

namespace Tests\Unit\Integraciones;

use App\Domains\Integraciones\Support\TokenApiKey;
use PHPUnit\Framework\TestCase;

class TokenApiKeyTest extends TestCase
{
    public function test_generar_produce_token_con_formato_esperado(): void
    {
        $generado = TokenApiKey::generar();

        $this->assertStringStartsWith('tnri_'.$generado['prefijo'].'_'.$generado['secreto'], $generado['token']);
        $this->assertSame(hash('sha256', $generado['secreto']), $generado['hash']);
    }

    public function test_parsear_extrae_prefijo_y_secreto(): void
    {
        $generado = TokenApiKey::generar();
        $partes = TokenApiKey::parsear($generado['token']);

        $this->assertSame($generado['prefijo'], $partes['prefijo']);
        $this->assertSame($generado['secreto'], $partes['secreto']);
    }

    public function test_parsear_rechaza_formato_invalido(): void
    {
        $this->assertNull(TokenApiKey::parsear('no-es-un-token-valido'));
        $this->assertNull(TokenApiKey::parsear('tnri_soloprefijo'));
        $this->assertNull(TokenApiKey::parsear('otroformato_prefijo_secreto'));
    }

    public function test_dos_generaciones_no_repiten_prefijo_ni_secreto(): void
    {
        $a = TokenApiKey::generar();
        $b = TokenApiKey::generar();

        $this->assertNotSame($a['prefijo'], $b['prefijo']);
        $this->assertNotSame($a['secreto'], $b['secreto']);
    }
}
