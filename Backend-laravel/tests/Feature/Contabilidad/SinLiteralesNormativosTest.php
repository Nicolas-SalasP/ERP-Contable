<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guardian anti-regresion de literales normativos (auditoria tasa IVA hardcodeada).
 *
 * Historial: `FacturaAComercialDteMapper.php` tenia `private const TASA_IVA = 19.00;`
 * en vez de leer `config('fiscal.tasa_iva')` (unico punto de cambio de la tasa,
 * ver config/fiscal.php). `CotizacionService.php` tenia el mismo problema con
 * `?? 19` en dos lugares. Este test escanea los Services de dominios que hacen
 * calculo tributario (Sii, Comercial, Contabilidad) y falla nombrando
 * archivo:linea si aparece un literal de IVA (19 / 0.19 / 0,19) en codigo,
 * cerca de la palabra "iva" (misma linea o +-2 lineas). Solo mira codigo real:
 * lineas de comentario (linea completa o bloques tipo PHPDoc) se ignoran,
 * porque documentar la tasa en un comentario no es el bug que se busca atajar.
 */
class SinLiteralesNormativosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Exclusiones documentadas (archivo => razon):
     *  - config/fiscal.php: es EL punto unico de la tasa, por definicion
     *    contiene el literal 0.19 (default de env('FISCAL_TASA_IVA', 0.19)).
     */
    private const ARCHIVOS_EXCLUIDOS = [
        'config/fiscal.php',
    ];

    /** Dominios con calculo tributario, dentro de sus Services. */
    private const DOMINIOS = ['Sii', 'Comercial', 'Contabilidad'];

    public function test_no_hay_literales_de_tasa_iva_hardcodeados_en_services(): void
    {
        $archivos = [];
        foreach (self::DOMINIOS as $dominio) {
            $archivos = array_merge(
                $archivos,
                glob(app_path("Domains/{$dominio}/Services/*.php")) ?: [],
                glob(app_path("Domains/{$dominio}/Services/**/*.php")) ?: [],
            );
        }
        $archivos = array_unique($archivos);

        $this->assertNotEmpty($archivos, 'No se encontraron archivos de Services; revisa el glob.');

        $hallazgos = [];

        foreach ($archivos as $archivo) {
            $rutaRelativa = str_replace('\\', '/', str_replace(app_path().DIRECTORY_SEPARATOR, '', $archivo));
            $rutaRelativa = 'app/'.ltrim($rutaRelativa, '/');

            if ($this->rutaExcluida($archivo)) {
                continue;
            }

            $lineasCodigo = $this->lineasDeCodigo($archivo);

            foreach ($lineasCodigo as $numeroLinea => $linea) {
                if (! $this->esLiteralIvaSospechoso($linea)) {
                    continue;
                }

                // Contexto: la propia linea o +-2 lineas deben mencionar iva/Iva/IVA
                // (en la version de codigo, sin comentarios) para reducir falsos positivos
                // (ej. "19" como codigo de tipo de documento SII, un anio, etc.)
                if (! $this->contextoTieneIva($lineasCodigo, $numeroLinea)) {
                    continue;
                }

                $hallazgos[] = sprintf('%s:%d: %s', $rutaRelativa, $numeroLinea + 1, trim($linea));
            }
        }

        $this->assertSame(
            [],
            $hallazgos,
            "Literales de tasa IVA hardcodeados detectados (deben leer config('fiscal.tasa_iva')): \n"
                .implode("\n", $hallazgos)
        );
    }

    private function rutaExcluida(string $archivoAbsoluto): bool
    {
        $normalizado = str_replace('\\', '/', $archivoAbsoluto);
        foreach (self::ARCHIVOS_EXCLUIDOS as $excluida) {
            if (str_ends_with($normalizado, $excluida)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Devuelve las lineas del archivo con el codigo de comentarios ya removido
     * (comentarios de linea completa y contenido dentro de bloques de tipo
     * PHPDoc). El indice del array corresponde a numero_linea - 1.
     *
     * @return array<int, string>
     */
    private function lineasDeCodigo(string $archivo): array
    {
        $contenido = file_get_contents($archivo) ?: '';
        $lineasCrudas = explode("\n", $contenido);

        $resultado = [];
        $dentroDeBloque = false;

        foreach ($lineasCrudas as $i => $linea) {
            $codigo = '';
            $len = strlen($linea);
            $j = 0;

            while ($j < $len) {
                if ($dentroDeBloque) {
                    $cierre = strpos($linea, '*/', $j);
                    if ($cierre === false) {
                        $j = $len;
                        break;
                    }
                    $dentroDeBloque = false;
                    $j = $cierre + 2;

                    continue;
                }

                // Comentario de linea completa: descartamos el resto de la linea.
                if (substr($linea, $j, 2) === '//') {
                    break;
                }

                // Apertura de bloque /* o /**.
                if (substr($linea, $j, 2) === '/*') {
                    $dentroDeBloque = true;
                    $j += 2;

                    continue;
                }

                $codigo .= $linea[$j];
                $j++;
            }

            $resultado[$i] = $codigo;
        }

        return $resultado;
    }

    private function esLiteralIvaSospechoso(string $lineaCodigo): bool
    {
        // 19 / 19.00 / 19.0 como numero aislado (no parte de 190, 1900, 2019, etc.)
        // o 0.19 / 0,19 como fraccion.
        return (bool) preg_match('/(?<![\d.])19(?:\.0{1,2})?(?![\d.])/', $lineaCodigo)
            || (bool) preg_match('/0[.,]19\b/', $lineaCodigo);
    }

    /**
     * @param  array<int, string>  $lineasCodigo
     */
    private function contextoTieneIva(array $lineasCodigo, int $numeroLinea): bool
    {
        for ($offset = -2; $offset <= 2; $offset++) {
            $idx = $numeroLinea + $offset;
            if (! array_key_exists($idx, $lineasCodigo)) {
                continue;
            }
            if (preg_match('/iva/i', $lineasCodigo[$idx])) {
                return true;
            }
        }

        return false;
    }
}
