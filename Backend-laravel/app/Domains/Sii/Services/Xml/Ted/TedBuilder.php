<?php

namespace App\Domains\Sii\Services\Xml\Ted;

use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Caf\CafSerializerService;
use App\Domains\Sii\Support\Iso88591Helper;
use LogicException;

/**
 * Construye el TED firmado completo:
 *
 *   <TED version="1.0">
 *     <DD>RE,TD,F,FE,RR,RSR,MNT,IT1,CAF,TSTED</DD>
 *     <FRMT algoritmo="SHA1withRSA">firma_base64</FRMT>
 *   </TED>
 *
 * CRITICO: el byte-exact del DD que se firma debe ser identico al byte-exact
 * del DD que aparece en el XML final del DTE. Por eso construimos el TED como
 * string puro (sin DOMDocument intermedio) y lo inyectamos en el XML final
 * via reemplazo de placeholder (preservando bytes).
 */
class TedBuilder
{
    public function __construct(
        private readonly CafSerializerService $cafSerializer,
        private readonly TedSignerService $tedSigner
    ) {
    }

    /**
     * @return string XML del TED en bytes ISO-8859-1, sin declaracion <?xml ?>.
     *
     * @throws LogicException si el folio del DTE no esta en el rango del CAF
     *                        o si el tipo_dte del CAF no coincide.
     * @throws \App\Domains\Sii\Exceptions\CafInvalidoException si falla firma
     */
    public function buildFirmado(SiiDteEmitido $dte, SiiCaf $caf): string
    {
        $this->validarConsistencia($dte, $caf);

        // 1. Extraer bloque CAF interno (DA + FRMA del SII) — UTF-8.
        $cafBlockUtf8 = $this->cafSerializer->extraerBloqueCaf($caf);

        // 2. Construir DD en UTF-8 con valores escapados, en orden EXACTO del XSD.
        $primerItem = $dte->detalles->first();
        $nombreIt1  = $primerItem instanceof SiiDteEmitidoDetalle
            ? $primerItem->nombre_item
            : 'ITEM';

        $ddUtf8 = '<DD>'
            . $this->elemento('RE',   $dte->emisor_rut)
            . $this->elemento('TD',   (string) $dte->tipo_dte)
            . $this->elemento('F',    (string) $dte->folio)
            . $this->elemento('FE',   $dte->fecha_emision->format('Y-m-d'))
            . $this->elemento('RR',   $dte->receptor_rut)
            . $this->elemento('RSR',  Iso88591Helper::sanitize($dte->receptor_razon_social, 40))
            . $this->elemento('MNT',  (string) (int) round((float) $dte->monto_total))
            . $this->elemento('IT1',  Iso88591Helper::sanitize($nombreIt1, 40))
            . $cafBlockUtf8
            . $this->elemento('TSTED', now()->format('Y-m-d\TH:i:s'))
            . '</DD>';

        // 3. Convertir el DD a bytes ISO-8859-1 — esto es lo que el SII espera
        //    y lo que firmamos para que verifique con RSAPUBK.
        $ddIso = Iso88591Helper::convertToIso($ddUtf8);

        // 4. Firmar los bytes EXACTOS del DD en ISO-8859-1.
        $firmaBase64 = $this->tedSigner->firmarDd($ddIso, $caf);

        // 5. Componer TED final como concatenacion pura (sin DOM intermedio
        //    que pueda reformatear bytes). FRMT contiene firma en ASCII puro.
        return '<TED version="1.0">'
            . $ddIso
            . '<FRMT algoritmo="SHA1withRSA">' . $firmaBase64 . '</FRMT>'
            . '</TED>';
    }

    /**
     * Helper interno: arma un elemento XML simple con valor escapado.
     * Trabaja en UTF-8; la conversion a ISO ocurre al final del build.
     */
    private function elemento(string $tag, string $valor): string
    {
        // Los valores van como texto de elemento, no como atributos.
        // Escapar comillas/apostrofes con &quot;/&apos; hace que DOMDocument pueda
        // reserializarlas luego como caracteres literales, cambiando los bytes
        // del <DD> y rompiendo la verificacion RSA-SHA1 del FRMT en el TED.
        // Por eso solo escapamos caracteres obligatorios en texto XML: &, < y >.
        $escaped = htmlspecialchars($valor, ENT_XML1 | ENT_NOQUOTES, 'UTF-8');

        return "<{$tag}>{$escaped}</{$tag}>";
    }

    /**
     * @throws LogicException
     */
    private function validarConsistencia(SiiDteEmitido $dte, SiiCaf $caf): void
    {
        if ($caf->tipo_dte !== $dte->tipo_dte) {
            throw new LogicException(sprintf(
                'tipo_dte del CAF (%d) no coincide con tipo_dte del DTE (%d).',
                $caf->tipo_dte,
                $dte->tipo_dte
            ));
        }

        if ($dte->folio < $caf->folio_desde || $dte->folio > $caf->folio_hasta) {
            throw new LogicException(sprintf(
                'Folio del DTE (%d) esta fuera del rango del CAF (%d-%d).',
                $dte->folio,
                $caf->folio_desde,
                $caf->folio_hasta
            ));
        }
    }
}
