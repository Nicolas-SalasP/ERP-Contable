<?php

namespace App\Domains\Sii\Services\Caf;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Models\SiiCaf;
use DOMDocument;
use Illuminate\Support\Facades\Crypt;

/**
 * Extrae el bloque <CAF version="1.0">...</CAF> del XML original del CAF
 * (que F3.1 persistio cifrado en sii_caf.xml_completo_cifrado). Este bloque
 * (DA + FRMA del SII) se embebe dentro del DD del TED para acreditar
 * legalmente el rango de folios autorizados.
 *
 * NOTA: el envoltorio <AUTORIZACION> y los bloques <RSASK>/<RSAPUBK> NO se
 * incluyen en el TED (son material privado al emisor; el SII ya los conoce
 * porque fue quien firmo el CAF).
 */
class CafSerializerService
{
    /**
     * @return string XML del bloque CAF, sin declaracion <?xml ?>, sin
     *                envoltorio AUTORIZACION, sin RSASK/RSAPUBK. Encoding
     *                de bytes: ISO-8859-1 si el XML original lo declaraba.
     *
     * @throws CafInvalidoException si el XML no contiene <CAF>.
     */
    public function extraerBloqueCaf(SiiCaf $caf): string
    {
        $xmlCompleto = Crypt::decryptString($caf->xml_completo_cifrado);

        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            // loadXML preserva encoding declarado en la cabecera del XML.
            if (! @$dom->loadXML($xmlCompleto)) {
                throw CafInvalidoException::xmlMalformado('XML cifrado del CAF no es parseable');
            }

            $cafNodes = $dom->getElementsByTagName('CAF');
            if ($cafNodes->length === 0) {
                throw CafInvalidoException::bloqueCafAusente($caf->id);
            }

            // saveXML(node) serializa el nodo aislado, sin declaracion al frente
            // y preservando atributos en su orden original.
            return $dom->saveXML($cafNodes->item(0));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }
}
