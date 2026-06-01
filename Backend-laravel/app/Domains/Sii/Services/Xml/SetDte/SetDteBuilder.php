<?php

namespace App\Domains\Sii\Services\Xml\SetDte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Models\SiiDteEmitido;
use DOMDocument;
use LogicException;

/**
 * Construye <EnvioDTE version="1.0"><SetDTE ID="SetDocDTE"><Caratula/><DTE>...
 * agrupando uno o mas DTE ya firmados (XMLs producidos por DteSigner).
 *
 * El SetDTE generado NO incluye aun la <ds:Signature> sobre SetDTE — esa la
 * inserta SetDteSigner como paso posterior.
 *
 * Validacion: todos los DTE deben tener el mismo emisor_rut. Receptor variable
 * permitido (en cert se aceptan sets multireceptor; en prod la regla la valida
 * el SII, no nosotros).
 */
class SetDteBuilder
{
    private const NS_SII   = 'http://www.sii.cl/SiiDte';
    private const NS_DSIG  = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XSI   = 'http://www.w3.org/2001/XMLSchema-instance';
    private const SCHEMA_LOCATION = 'http://www.sii.cl/SiiDte EnvioDTE_v10.xsd';

    public const SET_DTE_ID = 'SetDocDTE';

    public function __construct(private readonly CaratulaBuilder $caratulaBuilder)
    {
    }

    /**
     * @param array<int, array{dte: SiiDteEmitido, xml: string}> $dtesFirmados
     *
     * @throws LogicException si los emisores difieren entre DTE del set.
     */
    public function build(Empresa $empresa, array $dtesFirmados): string
    {
        if ($dtesFirmados === []) {
            throw new LogicException('SetDTE requiere al menos un DTE.');
        }

        $this->validarMismoEmisor($dtesFirmados);

        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput       = false;

        // <EnvioDTE> root con namespaces oficiales SII.
        $envio = $dom->createElementNS(self::NS_SII, 'EnvioDTE');
        $envio->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds',  self::NS_DSIG);
        $envio->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);
        $envio->setAttributeNS(self::NS_XSI, 'xsi:schemaLocation', self::SCHEMA_LOCATION);
        $envio->setAttribute('version', '1.0');
        $dom->appendChild($envio);

        // <SetDTE ID="SetDocDTE">
        $setDte = $dom->createElement('SetDTE');
        $setDte->setAttribute('ID', self::SET_DTE_ID);
        $envio->appendChild($setDte);

        // <Caratula>
        $dtes = array_map(fn (array $i) => $i['dte'], $dtesFirmados);
        $setDte->appendChild($this->caratulaBuilder->build($dom, $empresa, $dtes));

        // <DTE> ... importados desde cada XML firmado preservando bytes
        foreach ($dtesFirmados as $i => $item) {
            $setDte->appendChild($this->importarDteFirmado($dom, $item['xml'], $i));
        }

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw DteXmlInvalidException::estructuraIncoherente('saveXML retorno false al construir SetDTE.');
        }

        return $xml;
    }

    /**
     * @param array<int, array{dte: SiiDteEmitido, xml: string}> $dtesFirmados
     */
    private function validarMismoEmisor(array $dtesFirmados): void
    {
        $emisores = array_unique(array_map(
            fn (array $i) => $i['dte']->emisor_rut,
            $dtesFirmados
        ));

        if (count($emisores) > 1) {
            throw new LogicException(sprintf(
                'SetDTE no admite emisores distintos. Encontrados: %s',
                implode(', ', $emisores)
            ));
        }
    }

    private function importarDteFirmado(DOMDocument $destino, string $xmlDte, int $indice): \DOMNode
    {
        $tmp = new DOMDocument();
        $tmp->preserveWhiteSpace = true;
        if (! @$tmp->loadXML($xmlDte)) {
            throw DteXmlInvalidException::estructuraIncoherente(
                "El DTE indice {$indice} no es XML parseable."
            );
        }

        $root = $tmp->documentElement;
        if ($root === null || $root->localName !== 'DTE') {
            throw DteXmlInvalidException::estructuraIncoherente(
                "El DTE indice {$indice} no tiene <DTE> como root."
            );
        }

        return $destino->importNode($root, true);
    }
}
