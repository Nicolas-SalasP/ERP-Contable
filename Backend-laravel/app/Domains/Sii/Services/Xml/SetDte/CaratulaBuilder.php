<?php

namespace App\Domains\Sii\Services\Xml\SetDte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use DOMDocument;
use DOMElement;

/**
 * Construye el bloque <Caratula version="1.0"> del SetDTE.
 *
 * Orden xs:sequence del XSD EnvioDTE_v10.xsd:
 *   RutEmisor, RutEnvia, RutReceptor, FchResol, NroResol, TmstFirmaEnv, SubTotDTE+
 *
 * RutReceptor es siempre 60803000-K (SII Chile) — mismo valor para cert
 * y produccion segun documentacion oficial. La distincion entre ambientes
 * se hace en F5 (URL del WS), no en la Caratula.
 *
 * RutEnvia se obtiene del subject del certificado digital activo de la
 * empresa (CertificadoService::extraerRutDelSujeto). Suele coincidir con
 * el RUT empresa o con el RUT del representante legal.
 */
class CaratulaBuilder
{
    /** RUT receptor para envios al SII (cert y prod). */
    public const RUT_RECEPTOR_SII = '60803000-K';

    public function __construct(private readonly CertificadoService $certificadoService)
    {
    }

    /**
     * @param array<int, SiiDteEmitido> $dtes
     *
     * @throws DteXmlInvalidException si la empresa carece de datos de resolucion SII.
     */
    public function build(DOMDocument $dom, Empresa $empresa, array $dtes): DOMElement
    {
        if ($empresa->resolucion_sii_numero === null) {
            throw DteXmlInvalidException::estructuraIncoherente(
                'Empresa sin resolucion_sii_numero: requerido para Caratula.NroResol.'
            );
        }
        if ($empresa->resolucion_sii_fecha === null) {
            throw DteXmlInvalidException::estructuraIncoherente(
                'Empresa sin resolucion_sii_fecha: requerido para Caratula.FchResol.'
            );
        }

        $rutEnvia = $this->certificadoService->extraerRutDelSujeto($empresa);

        $caratula = $dom->createElement('Caratula');
        $caratula->setAttribute('version', '1.0');

        $caratula->appendChild($dom->createElement('RutEmisor',   $empresa->rut));
        $caratula->appendChild($dom->createElement('RutEnvia',    $rutEnvia));
        $caratula->appendChild($dom->createElement('RutReceptor', self::RUT_RECEPTOR_SII));
        $caratula->appendChild($dom->createElement(
            'FchResol',
            $empresa->resolucion_sii_fecha->format('Y-m-d')
        ));
        $caratula->appendChild($dom->createElement('NroResol', (string) $empresa->resolucion_sii_numero));
        $caratula->appendChild($dom->createElement('TmstFirmaEnv', now()->format('Y-m-d\TH:i:s')));

        foreach ($this->agruparPorTipo($dtes) as $tipoDte => $cantidad) {
            $sub = $dom->createElement('SubTotDTE');
            $sub->appendChild($dom->createElement('TpoDTE', (string) $tipoDte));
            $sub->appendChild($dom->createElement('NroDTE', (string) $cantidad));
            $caratula->appendChild($sub);
        }

        return $caratula;
    }

    /**
     * @param array<int, SiiDteEmitido> $dtes
     * @return array<int, int> mapa tipo_dte -> conteo (ordenado por tipo asc)
     */
    private function agruparPorTipo(array $dtes): array
    {
        $conteo = [];
        foreach ($dtes as $dte) {
            $tipo = (int) $dte->tipo_dte;
            $conteo[$tipo] = ($conteo[$tipo] ?? 0) + 1;
        }
        ksort($conteo);

        return $conteo;
    }
}
