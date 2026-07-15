<?php

namespace App\Domains\Sii\Services\Xml\SetDte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Models\SiiDteEmitido;
use DOMDocument;
use LogicException;

/**
 * Construye <EnvioDTE><SetDTE><Caratula/><DTE>... (o <EnvioBOLETA>... para boletas 39/41 --
 * mismo Caratula, mismo SetDTE, solo cambia el elemento raiz y el schemaLocation, confirmado
 * contra EnvioBOLETA_v11.xsd: su Caratula tiene exactamente los mismos campos que EnvioDTE_v10.xsd)
 * agrupando uno o mas DTE ya firmados (la <ds:Signature> sobre SetDTE la inserta SetDteSigner
 * despues); todos los DTE deben tener el mismo emisor_rut, receptor variable permitido, y no se
 * pueden mezclar boletas con facturas en el mismo sobre (el SII usa envios/endpoints distintos).
 */
class SetDteBuilder
{
    private const NS_SII = 'http://www.sii.cl/SiiDte';

    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    private const SCHEMA_LOCATION_DTE = 'http://www.sii.cl/SiiDte EnvioDTE_v10.xsd';

    private const SCHEMA_LOCATION_BOLETA = 'http://www.sii.cl/SiiDte EnvioBOLETA_v11.xsd';

    /** Tipos DTE que son boleta (39 normal, 41 exenta) -- van en sobre <EnvioBOLETA>, no <EnvioDTE>. */
    private const TIPOS_BOLETA = [39, 41];

    public const SET_DTE_ID = 'SetDocDTE';

    public function __construct(private readonly CaratulaBuilder $caratulaBuilder) {}

    /**
     * @param  array<int, array{dte: SiiDteEmitido, xml: string}>  $dtesFirmados
     *
     * @throws LogicException si los emisores difieren entre DTE del set.
     */
    public function build(Empresa $empresa, array $dtesFirmados): string
    {
        if ($dtesFirmados === []) {
            throw new LogicException('SetDTE requiere al menos un DTE.');
        }

        $this->validarMismoEmisor($dtesFirmados);
        $esBoleta = $this->validarNoMezclaBoletaYFactura($dtesFirmados);

        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        // <EnvioDTE> (factura/NC/ND) o <EnvioBOLETA> (39/41) root con namespaces oficiales SII.
        $rootTag = $esBoleta ? 'EnvioBOLETA' : 'EnvioDTE';
        $schemaLocation = $esBoleta ? self::SCHEMA_LOCATION_BOLETA : self::SCHEMA_LOCATION_DTE;

        $envio = $dom->createElementNS(self::NS_SII, $rootTag);
        $envio->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::NS_DSIG);
        $envio->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);
        $envio->setAttributeNS(self::NS_XSI, 'xsi:schemaLocation', $schemaLocation);
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
     * @param  array<int, array{dte: SiiDteEmitido, xml: string}>  $dtesFirmados
     * @return bool true si el set es de boletas (39/41).
     *
     * @throws LogicException si el set mezcla boletas con otros tipos de DTE.
     */
    private function validarNoMezclaBoletaYFactura(array $dtesFirmados): bool
    {
        $tipos = array_unique(array_map(
            fn (array $i) => (int) $i['dte']->tipo_dte,
            $dtesFirmados
        ));

        $tiposBoleta = array_intersect($tipos, self::TIPOS_BOLETA);

        if (count($tiposBoleta) > 0 && count($tiposBoleta) < count($tipos)) {
            throw new LogicException(sprintf(
                'SetDTE no admite mezclar boletas (39/41) con otros tipos de DTE en el mismo sobre. Tipos encontrados: %s',
                implode(', ', $tipos)
            ));
        }

        return count($tiposBoleta) > 0;
    }

    /**
     * @param  array<int, array{dte: SiiDteEmitido, xml: string}>  $dtesFirmados
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
        $tmp = new DOMDocument;
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
