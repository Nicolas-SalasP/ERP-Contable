<?php

namespace App\Domains\Sii\Services\Ws;

use App\Domains\Sii\Exceptions\SiiAutenticacionException;
use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F5.1 — Obtiene una semilla del WS de autenticacion del SII.
 *
 * Envia un SOAP envelope con el metodo getSeed al endpoint del ambiente
 * (cert o prod). Parsea respuesta SOAP+CDATA y extrae <SEMILLA>.
 *
 * Estrategia de parseo SOAP+CDATA: DOMDocument anidado. El SII envuelve
 * su XML de respuesta en CDATA dentro del nodo SOAP <getSeedReturn>; primero
 * parseamos el SOAP, extraemos el textContent (que es el XML SII en claro),
 * y lo re-parseamos como segundo DOMDocument para navegar a <SEMILLA>.
 * SimpleXML no maneja bien los namespaces multi-prefijo de la respuesta.
 */
class SiiSeedService
{
    private const HTTP_TIMEOUT_SEGUNDOS = 30;
    private const HTTP_RETRIES          = 3;
    private const HTTP_RETRY_DELAY_MS   = 1000;

    /** Codigo "OK" devuelto por el SII en <SII:RESP_HDR><ESTADO>. */
    private const ESTADO_OK = '00';

    public function obtener(string $ambiente): string
    {
        $url = $this->urlPara($ambiente);

        $soapBody = $this->construirSoapGetSeed();

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT_SEGUNDOS)
                ->retry(self::HTTP_RETRIES, self::HTTP_RETRY_DELAY_MS, throw: false)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction'   => '""',
                ])
                ->withBody($soapBody, 'text/xml')
                ->post($url);
        } catch (ConnectionException $e) {
            throw SiiAutenticacionException::timeoutRed(self::HTTP_RETRIES);
        }

        if ($response->failed()) {
            throw SiiAutenticacionException::semillaNoObtenida(
                $response->status(),
                $response->body()
            );
        }

        return $this->extraerSemillaDeRespuesta($response->body());
    }

    private function urlPara(string $ambiente): string
    {
        $url = config("sii.urls.{$ambiente}.semilla");
        if (! is_string($url) || $url === '') {
            throw SiiAutenticacionException::semillaInvalida(
                "config('sii.urls.{$ambiente}.semilla') no definido"
            );
        }

        return $url;
    }

    /**
     * SOAP envelope para getSeed. SOAPAction='""' (segun WSDL del SII Chile).
     */
    private function construirSoapGetSeed(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:def="http://DefaultNamespace">
  <soapenv:Header/>
  <soapenv:Body>
    <def:getSeed/>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * Parsea SOAP envelope → extrae CDATA con el XML SII → valida ESTADO=00
     * → retorna el contenido de <SEMILLA>.
     *
     * @throws SiiAutenticacionException si la estructura no es la esperada.
     */
    private function extraerSemillaDeRespuesta(string $soapBody): string
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            if (! @$dom->loadXML($soapBody)) {
                throw SiiAutenticacionException::semillaInvalida('respuesta no es XML parseable');
            }

            // Navegar a getSeedReturn (en cualquier namespace) y extraer su
            // textContent, que es el XML del SII en claro (CDATA-unwrapped por DOMDocument).
            $returns = $dom->getElementsByTagName('getSeedReturn');
            if ($returns->length === 0) {
                throw SiiAutenticacionException::semillaInvalida(
                    'no se encontro <getSeedReturn> en la respuesta SOAP'
                );
            }
            $xmlSii = trim((string) $returns->item(0)->textContent);
            if ($xmlSii === '') {
                throw SiiAutenticacionException::semillaInvalida('<getSeedReturn> vacio');
            }

            return $this->parsearXmlSiiYExtraerSemilla($xmlSii);
        } catch (SiiAutenticacionException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::channel('sii')->warning('Fallo parseo respuesta semilla SII.', [
                'error'         => $e->getMessage(),
                'response_head' => substr($soapBody, 0, 200),
            ]);
            throw SiiAutenticacionException::semillaInvalida($e->getMessage());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    private function parsearXmlSiiYExtraerSemilla(string $xmlSii): string
    {
        $dom = new DOMDocument();
        if (! @$dom->loadXML($xmlSii)) {
            throw SiiAutenticacionException::semillaInvalida('XML SII interno no es parseable');
        }

        $estadoNodos = $dom->getElementsByTagName('ESTADO');
        if ($estadoNodos->length === 0) {
            throw SiiAutenticacionException::semillaInvalida('falta <ESTADO> en respuesta SII');
        }
        $estado = trim((string) $estadoNodos->item(0)->textContent);
        if ($estado !== self::ESTADO_OK) {
            $glosaNodos = $dom->getElementsByTagName('GLOSA');
            $glosa      = $glosaNodos->length > 0 ? trim((string) $glosaNodos->item(0)->textContent) : '';
            throw SiiAutenticacionException::semillaInvalida(
                "SII respondio ESTADO={$estado}" . ($glosa !== '' ? " GLOSA={$glosa}" : '')
            );
        }

        $semillaNodos = $dom->getElementsByTagName('SEMILLA');
        if ($semillaNodos->length === 0) {
            throw SiiAutenticacionException::semillaInvalida('falta <SEMILLA> en RESP_BODY');
        }
        $semilla = trim((string) $semillaNodos->item(0)->textContent);
        if ($semilla === '') {
            throw SiiAutenticacionException::semillaInvalida('<SEMILLA> vacia');
        }

        return $semilla;
    }
}
