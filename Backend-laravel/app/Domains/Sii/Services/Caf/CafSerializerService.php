<?php

namespace App\Domains\Sii\Services\Caf;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Models\SiiCaf;
use DOMDocument;
use Illuminate\Support\Facades\Crypt;

/** Extrae el bloque <CAF version="1.0">...</CAF> (DA + FRMA del SII) del XML original persistido cifrado en sii_caf.xml_completo_cifrado, para embeberlo en el DD del TED y acreditar legalmente el rango de folios; NOTA: <AUTORIZACION> y <RSASK>/<RSAPUBK> no se incluyen en el TED (material privado del emisor, el SII ya los conoce por haber firmado el CAF). */
class CafSerializerService
{
    /**
     * @return string XML del bloque CAF, sin declaracion <?xml ?>, sin envoltorio AUTORIZACION, sin RSASK/RSAPUBK; encoding de bytes ISO-8859-1 si el XML original lo declaraba.
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

            // saveXML(node) serializa el nodo aislado, sin declaracion al frente y preservando atributos en su orden original.
            return $dom->saveXML($cafNodes->item(0));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }
}
