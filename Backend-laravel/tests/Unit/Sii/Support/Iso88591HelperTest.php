<?php

namespace Tests\Unit\Sii\Support;

use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Support\Iso88591Helper;
use PHPUnit\Framework\TestCase;

class Iso88591HelperTest extends TestCase
{
    public function test_convierte_string_simple(): void
    {
        $this->assertSame('Hola mundo', Iso88591Helper::convertToIso('Hola mundo'));
    }

    public function test_convierte_caracteres_acentuados(): void
    {
        $original = 'Cañón áéíóú ÁÉÍÓÚ ñÑ üÜ ¿¡';
        $iso = Iso88591Helper::convertToIso($original);
        $roundTrip = Iso88591Helper::convertToUtf8($iso);

        $this->assertSame($original, $roundTrip);
    }

    public function test_convierte_eñe_minuscula_y_mayuscula(): void
    {
        // ñ en ISO-8859-1 = byte 0xF1 (241), Ñ = byte 0xD1 (209).
        $this->assertSame('Ba' . chr(241) . 'o', Iso88591Helper::convertToIso('Baño'));
        $this->assertSame(chr(209) . 'UBLE', Iso88591Helper::convertToIso('ÑUBLE'));
    }

    public function test_round_trip_idempotente_para_chars_validos(): void
    {
        $cases = ['ABC', 'á', 'ñ', '¿qué pasa?', 'Calle José Joaquín Pérez 1234'];
        foreach ($cases as $caso) {
            $iso = Iso88591Helper::convertToIso($caso);
            $this->assertSame($caso, Iso88591Helper::convertToUtf8($iso), "Fallo en: $caso");
        }
    }

    public function test_falla_con_emoji_lanza_excepcion(): void
    {
        $this->expectException(DteIncompletoException::class);
        Iso88591Helper::convertToIso('Hola 🎉 mundo');
    }

    public function test_falla_con_kanji(): void
    {
        $this->expectException(DteIncompletoException::class);
        Iso88591Helper::convertToIso('日本語');
    }

    public function test_sanitize_aplica_trim_y_collapse(): void
    {
        $this->assertSame('a b c', Iso88591Helper::sanitize("  a   b\t\tc  "));
    }

    public function test_sanitize_respeta_max_length(): void
    {
        $largo = str_repeat('ab', 100);
        $cortado = Iso88591Helper::sanitize($largo, 10);
        $this->assertSame(10, strlen($cortado));
    }

    public function test_sanitize_no_corta_en_medio_de_caracter_multibyte(): void
    {
        // sanitize retorna UTF-8 (validado). Cortamos a 5 caracteres UTF-8.
        $resultado = Iso88591Helper::sanitize('mañana suave', 5);
        $this->assertSame('mañan', $resultado);
    }

    public function test_sanitize_retorna_utf8_no_iso(): void
    {
        // sanitize valida convertibilidad a ISO-8859-1 pero retorna UTF-8
        // (para que DOMDocument lo maneje correctamente).
        $resultado = Iso88591Helper::sanitize('Ñandú café');
        $this->assertSame('Ñandú café', $resultado);
    }

}
