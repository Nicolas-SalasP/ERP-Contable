<?php

namespace App\Domains\Sii\Services\Caf;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Support\RutHelper;
use Carbon\Carbon;
use SimpleXMLElement;

/**
 * Parser del XML del CAF emitido por el SII. Devuelve un array asociativo
 * con los campos canonicos del CAF. NO firma ni verifica criptograficamente
 * (validacion contra cert raiz SII queda diferida al backlog).
 */
class CafXmlParser
{
    /**
     * @return array{
     *     tipo_dte: int,
     *     folio_desde: int,
     *     folio_hasta: int,
     *     fecha_autorizacion: \Carbon\Carbon,
     *     fecha_vencimiento: \Carbon\Carbon|null,
     *     rut_empresa: string,
     *     razon_social: string,
     *     sii_idk: string,
     *     rsa_sk: string,
     *     rsa_pubk: string,
     *     firma_caf: string,
     *     xml_completo: string,
     * }
     *
     * @throws CafInvalidoException
     */
    public function parse(string $xmlString): array
    {
        $xml = $this->cargarXml($xmlString);

        // Nodos obligatorios bajo /AUTORIZACION/CAF/DA
        $da = $xml->CAF->DA ?? null;
        if ($da === null) {
            throw CafInvalidoException::estructuraInvalida('AUTORIZACION/CAF/DA');
        }

        $rutRaw       = $this->extraer($da, 'RE');
        $razonSocial  = $this->extraer($da, 'RS');
        $tipoDte      = $this->extraer($da, 'TD');
        $folioDesde   = $this->extraer($da->RNG ?? null, 'D', 'AUTORIZACION/CAF/DA/RNG/D');
        $folioHasta   = $this->extraer($da->RNG ?? null, 'H', 'AUTORIZACION/CAF/DA/RNG/H');
        $fechaAutoraw = $this->extraer($da, 'FA');
        $siiIdk       = $this->extraer($da, 'IDK');

        $firmaCaf = (string) ($xml->CAF->FRMA ?? '');
        if ($firmaCaf === '') {
            throw CafInvalidoException::estructuraInvalida('AUTORIZACION/CAF/FRMA');
        }

        $rsaSk = (string) ($xml->RSASK ?? '');
        if ($rsaSk === '') {
            throw CafInvalidoException::estructuraInvalida('AUTORIZACION/RSASK');
        }

        $rsaPubk = (string) ($xml->RSAPUBK ?? '');
        if ($rsaPubk === '') {
            throw CafInvalidoException::estructuraInvalida('AUTORIZACION/RSAPUBK');
        }

        try {
            $rutNormalizado = RutHelper::normalizar($rutRaw);
        } catch (\InvalidArgumentException $e) {
            throw CafInvalidoException::estructuraInvalida('AUTORIZACION/CAF/DA/RE (RUT invalido: ' . $e->getMessage() . ')');
        }

        $fechaAutorizacion = Carbon::parse($fechaAutoraw);

        // Calculo de vencimiento: Res. Ex. SII N°154/2025 fija vigencia de
        // 6 meses para CAFs nuevos. Para CAFs antiguos sin nodo explicito
        // tomamos esta aproximacion conservadora.
        $fechaVencimiento = $fechaAutorizacion->copy()->addMonths(6);

        return [
            'tipo_dte'           => (int) $tipoDte,
            'folio_desde'        => (int) $folioDesde,
            'folio_hasta'        => (int) $folioHasta,
            'fecha_autorizacion' => $fechaAutorizacion,
            'fecha_vencimiento'  => $fechaVencimiento,
            'rut_empresa'        => $rutNormalizado,
            'razon_social'       => $razonSocial,
            'sii_idk'            => $siiIdk,
            'rsa_sk'             => $rsaSk,
            'rsa_pubk'           => $rsaPubk,
            'firma_caf'          => $firmaCaf,
            'xml_completo'       => $xmlString,
        ];
    }

    /**
     * @throws CafInvalidoException
     */
    private function cargarXml(string $xmlString): SimpleXMLElement
    {
        $prev = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($xmlString);
            if ($xml === false) {
                $errores = array_map(fn ($e) => trim($e->message), libxml_get_errors());
                throw CafInvalidoException::xmlMalformado(implode(' | ', $errores) ?: 'parse error');
            }

            if ($xml->getName() !== 'AUTORIZACION') {
                throw CafInvalidoException::estructuraInvalida('Nodo raiz debe ser AUTORIZACION; encontrado: ' . $xml->getName());
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    /**
     * Extrae el valor de un nodo hijo obligatorio. Lanza si no existe.
     *
     * @param SimpleXMLElement|null $nodo
     */
    private function extraer($nodo, string $hijo, ?string $rutaCompleta = null): string
    {
        if ($nodo === null || ! isset($nodo->{$hijo})) {
            throw CafInvalidoException::estructuraInvalida($rutaCompleta ?? $hijo);
        }

        $valor = trim((string) $nodo->{$hijo});
        if ($valor === '') {
            throw CafInvalidoException::estructuraInvalida(($rutaCompleta ?? $hijo) . ' (vacio)');
        }

        return $valor;
    }
}
