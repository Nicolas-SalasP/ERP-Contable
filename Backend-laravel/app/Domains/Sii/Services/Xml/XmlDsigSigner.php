<?php

namespace App\Domains\Sii\Services\Xml;

use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

/**
 * Servicio generico para firma XMLDSig SHA1+RSA+C14N1.0+enveloped.
 *
 * Usado por DteSigner (firma <Documento>) y SetDteSigner (firma <SetDTE>).
 * El contrato algoritmico es FIJO por requerimiento SII Chile:
 *   CanonicalizationMethod: C14N 1.0 inclusiva (NO exc-c14n).
 *   SignatureMethod:        rsa-sha1
 *   DigestMethod:           sha1
 *   Transforms (Reference): enveloped-signature + C14N 1.0
 *
 * Verifica criptograficamente la firma generada antes de retornar — si el
 * round-trip falla, lanza DteXmlInvalidException (defensa contra regresiones
 * de bit-exactness en la canonicalizacion).
 */
class XmlDsigSigner
{
    /** Transform enveloped-signature: xmlseclibs no expone una constante. */
    private const TRANSFORM_ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    /**
     * Firma un nodo identificado por su atributo ID y agrega <ds:Signature>
     * dentro de $signatureParent.
     *
     * @param DOMDocument $dom              XML que contiene el nodo a firmar.
     * @param string      $nodeIdAttribute  Valor del atributo ID (ej. "D123", "SetDocDTE").
     * @param string      $nodeTagName      Tag local del nodo a localizar (ej. "Documento", "SetDTE").
     * @param string      $certPem          Certificado X.509 en PEM.
     * @param string      $privKeyPem       Clave privada RSA en PEM.
     * @param DOMNode     $signatureParent  Padre donde se inserta <ds:Signature>.
     * @param DOMNode|null $signatureSibling Si se provee, la Signature se inserta ANTES de este nodo.
     *
     * @throws RuntimeException        si el nodo objetivo no se encuentra.
     * @throws DteXmlInvalidException  si la firma producida no se verifica.
     */
    public function firmarNodo(
        DOMDocument $dom,
        string $nodeIdAttribute,
        string $nodeTagName,
        string $certPem,
        string $privKeyPem,
        DOMNode $signatureParent,
        ?DOMNode $signatureSibling = null
    ): void {
        $nodoFirmar = $this->localizarNodoPorId($dom, $nodeTagName, $nodeIdAttribute);
        if ($nodoFirmar === null) {
            throw new RuntimeException(
                "No se encontro nodo <{$nodeTagName} ID=\"{$nodeIdAttribute}\"> para firmar."
            );
        }

        // setIdAttribute le dice al DOM que 'ID' es de tipo xs:ID. Sin esto,
        // xmlseclibs no resuelve la Reference URI="#D123" durante el verify.
        $nodoFirmar->setIdAttribute('ID', true);

        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::C14N);

        // Reference con UN solo transform: enveloped-signature. El XSD oficial
        // del SII (xmldsignature_v10.xsd) limita el Transforms.Transform a 1
        // ocurrencia, por lo que NO agregamos C14N como transform adicional —
        // la canonicalizacion se aplica via CanonicalizationMethod del SignedInfo.
        //
        // overwrite=false + id_name=ID -> NO genera GUID, usa el atributo existente.
        $dsig->addReference(
            $nodoFirmar,
            XMLSecurityDSig::SHA1,
            [self::TRANSFORM_ENVELOPED],
            ['id_name' => 'ID', 'overwrite' => false, 'force_uri' => false]
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $key->loadKey($privKeyPem, false);

        // CRITICO: pasar $signatureParent a sign() para que xmlseclibs inserte
        // la sigNode en el DOM destino ANTES de canonilizar el SignedInfo. Sin
        // esto, el SignedInfo se canoliza en su DOM aislado (sin namespaces
        // heredados) y firma con bytes distintos a los que ve el verify
        // despues del importNode. Documentado en sign(): "If we have a parent
        // node append it now so C14N properly works".
        $dsig->sign($key, $signatureParent);

        // KeyInfo del SII requiere KeyValue (RSAKeyValue: Modulus + Exponent)
        // ANTES de X509Data, en ese orden. xmlseclibs::add509Cert solo agrega
        // X509Data, asi que construimos KeyValue manualmente. Sin esto, el
        // XSD oficial (xmldsignature_v10.xsd) rechaza el XML.
        $this->agregarKeyValueRsa($dsig, $key);
        $dsig->add509Cert($certPem, true);

        // Reposicionamiento opcional: sign() hizo appendChild al final del
        // padre. Si necesitabamos otro orden (signatureSibling), mover.
        $signatureNode = $dsig->sigNode;
        if ($signatureSibling !== null && $signatureNode->nextSibling !== $signatureSibling) {
            $signatureParent->insertBefore($signatureNode, $signatureSibling);
        }

        // Verificacion round-trip: re-parsear el XML emitido y re-validar la
        // firma contra la clave publica. Detecta regresiones de bit-exactness
        // en la canonicalizacion entre sign() y verify().
        $this->verificarRoundTrip($dom, $certPem, $signatureNode);
    }

    /**
     * @throws DteXmlInvalidException si la firma no verifica contra el cert publico.
     */
    private function verificarRoundTrip(DOMDocument $dom, string $certPem, DOMNode $signatureNode): void
    {
        // Re-parseamos el XML emitido para verificar en condiciones de "fresh load",
        // sin estado interno de xmlseclibs que pudiera enmascarar errores.
        $xmlEmitido = $dom->saveXML();
        if ($xmlEmitido === false) {
            throw DteXmlInvalidException::estructuraIncoherente(
                'DOMDocument::saveXML retorno false al verificar la firma round-trip.'
            );
        }

        $domVerificar = new DOMDocument();
        $domVerificar->preserveWhiteSpace = true;
        if (! @$domVerificar->loadXML($xmlEmitido)) {
            throw DteXmlInvalidException::estructuraIncoherente(
                'No se pudo re-parsear el XML firmado para verificar round-trip.'
            );
        }

        // Marcar todos los atributos ID como tipo ID en el DOM nuevo, para que
        // la Reference URI="#..." resuelva durante verify.
        $this->marcarAtributosIdComoId($domVerificar);

        // Localizar EXACTAMENTE la misma signature por su posicion ordinal en
        // el documento (cuando hay multiples, ej. Documento + ds:Signature de F4.1).
        $posicion = $this->posicionDeSignatureEnDocumento($dom, $signatureNode);

        $dsigVerify = new XMLSecurityDSig();
        // xmlseclibs busca @Id por default en processRefNode. Como nuestro
        // atributo es @ID (XSD del SII), tenemos que poblarlo aqui o
        // validateReference no encontrara el nodo via URI="#xxx".
        $dsigVerify->idKeys = ['ID'];
        $sigEncontrada = $dsigVerify->locateSignature($domVerificar, $posicion);
        if ($sigEncontrada === null) {
            throw DteXmlInvalidException::estructuraIncoherente(
                'Signature recien insertada no se pudo localizar en el XML serializado.'
            );
        }

        $dsigVerify->canonicalizeSignedInfo();

        $keyPub = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
        $keyPub->loadKey($certPem, false, true);

        if ($dsigVerify->verify($keyPub) !== 1) {
            throw DteXmlInvalidException::estructuraIncoherente(
                'La firma XMLDSig recien generada no se verifica contra el certificado publico (round-trip fallido).'
            );
        }

        if (! $dsigVerify->validateReference()) {
            throw DteXmlInvalidException::estructuraIncoherente(
                'La Reference del XMLDSig no valida contra el digest del nodo firmado.'
            );
        }
    }

    /**
     * Agrega <ds:KeyInfo><ds:KeyValue><ds:RSAKeyValue><ds:Modulus/><ds:Exponent/>
     * a la sigNode, ANTES de cualquier <ds:X509Data> que add509Cert anada
     * despues. El XSD del SII exige este orden.
     *
     * Modulus y Exponent se extraen de la clave (privada o publica) via
     * openssl_pkey_get_details. Aunque el contrato del SII es algorimicamente
     * redundante (X509 ya contiene el publico), el XSD lo requiere.
     */
    private function agregarKeyValueRsa(XMLSecurityDSig $dsig, XMLSecurityKey $key): void
    {
        // XMLSecurityKey expone la clave como recurso/string. Re-leerla con
        // openssl_pkey_get_private/public para acceder a modulus/exponent.
        $pem = $key->key;
        $res = @openssl_pkey_get_private($pem) ?: @openssl_pkey_get_public($pem);
        if ($res === false) {
            // Sin acceso a modulus/exponent no podemos cumplir el XSD; salir
            // silenciosamente para no romper casos donde el XSD no se valida.
            return;
        }
        $detalles = openssl_pkey_get_details($res);
        if (! is_array($detalles) || ! isset($detalles['rsa']['n'], $detalles['rsa']['e'])) {
            return;
        }

        $modulusB64  = base64_encode($detalles['rsa']['n']);
        $exponentB64 = base64_encode($detalles['rsa']['e']);

        $sigDoc = $dsig->sigNode->ownerDocument;
        // Localizar KeyInfo o crearlo si no existe.
        $xp = new \DOMXPath($sigDoc);
        $xp->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
        $keyInfo = $xp->query('./ds:KeyInfo', $dsig->sigNode)->item(0);
        if ($keyInfo === null) {
            $keyInfo = $sigDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:KeyInfo');
            $dsig->sigNode->appendChild($keyInfo);
        }

        $keyValue = $sigDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:KeyValue');
        $rsaKey   = $sigDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:RSAKeyValue');
        $rsaKey->appendChild($sigDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:Modulus', $modulusB64));
        $rsaKey->appendChild($sigDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:Exponent', $exponentB64));
        $keyValue->appendChild($rsaKey);

        // Insertar como PRIMER hijo de KeyInfo (antes de X509Data que vendra despues).
        if ($keyInfo->firstChild !== null) {
            $keyInfo->insertBefore($keyValue, $keyInfo->firstChild);
        } else {
            $keyInfo->appendChild($keyValue);
        }
    }

    private function localizarNodoPorId(DOMDocument $dom, string $tagName, string $idValue): ?DOMElement
    {
        $nodos = $dom->getElementsByTagName($tagName);
        foreach ($nodos as $nodo) {
            if ($nodo instanceof DOMElement && $nodo->getAttribute('ID') === $idValue) {
                return $nodo;
            }
        }

        return null;
    }

    /**
     * Recorre todo el DOM marcando atributos ID como tipo ID. Necesario para
     * que xmlseclibs::validateReference resuelva URI="#xxx" correctamente.
     */
    private function marcarAtributosIdComoId(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $nodos = $xpath->query('//*[@ID]');
        if ($nodos === false) {
            return;
        }
        foreach ($nodos as $nodo) {
            if ($nodo instanceof DOMElement) {
                $nodo->setIdAttribute('ID', true);
            }
        }
    }

    /**
     * Cuenta cuantas signatures ds:Signature hay antes (en orden documental)
     * de la signature recien insertada. Usado para que locateSignature
     * encuentre exactamente la misma en el re-parse.
     */
    private function posicionDeSignatureEnDocumento(DOMDocument $dom, DOMNode $signatureNode): int
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);

        $todas = $xpath->query('//ds:Signature');
        if ($todas === false) {
            return 0;
        }

        foreach ($todas as $i => $nodo) {
            if ($nodo->isSameNode($signatureNode)) {
                return $i;
            }
        }

        return 0;
    }
}
