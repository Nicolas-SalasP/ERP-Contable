<?php

namespace App\Domains\Sii\Services\Ws;

use App\Domains\Sii\Exceptions\PollingSiiException;
use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F5.3 — Cliente HTTP del WS QueryEstUp.jws (SOAP) del SII.
 *
 * Mismo patron SOAP+CDATA que SiiSeedService (F5.1). El servicio retorna
 * en RESP_HDR el ESTADO de control (00=OK, 99=token expirado) y en
 * RESP_BODY el ESTADO real del envio (EPR, EOK, RPR, LOC, etc.).
 *
 * NO orquesta — solo POST + parseo. El reintento por token expirado vive
 * en PollearEstadoSiiService.
 */
class SiiEstadoUpService
{
    private const HTTP_TIMEOUT_SEGUNDOS_DEFAULT = 30;
    private const HTTP_RETRIES_DEFAULT          = 3;
    private const HTTP_RETRY_DELAY_MS_DEFAULT   = 60000;

    private const USER_AGENT_DEFAULT = 'Mozilla/4.0 (compatible; PROG 1.0; Windows NT 5.0; YComp 5.0.2.4)';

    /**
     * @return array{
     *   estado_hdr: string,     // codigo SII del RESP_HDR (00/99/etc)
     *   estado_sii: string|null,// codigo SII del RESP_BODY (EPR/EOK/etc)
     *   glosa: string|null,
     *   http_status: int,
     *   response_body: string,
     *   transport_failed: bool
     * }
     */
    public function consultar(
        string $rutCompany,
        string $dvCompany,
        string $trackId,
        string $token,
        string $ambiente
    ): array {
        $url = (string) config("sii.urls.{$ambiente}.estado_envio");
        if ($url === '') {
            throw PollingSiiException::respuestaSinEstado(
                "config('sii.urls.{$ambiente}.estado_envio') no definido"
            );
        }

        $timeout    = (int) config('sii.upload.timeout_seconds', self::HTTP_TIMEOUT_SEGUNDOS_DEFAULT);
        $retries    = (int) config('sii.upload.retries', self::HTTP_RETRIES_DEFAULT);
        $retryDelay = (int) config('sii.upload.retry_delay_ms', self::HTTP_RETRY_DELAY_MS_DEFAULT);
        $userAgent  = (string) config('sii.upload.user_agent', self::USER_AGENT_DEFAULT);

        $soapBody = $this->construirSoapGetEstUp($rutCompany, $dvCompany, $trackId, $token);

        try {
            $response = Http::timeout($timeout)
                ->retry($retries, $retryDelay, throw: false)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction'   => '""',
                    'User-Agent'   => $userAgent,
                    'Cookie'       => "TOKEN={$token}",
                ])
                ->withBody($soapBody, 'text/xml')
                ->post($url);
        } catch (ConnectionException $e) {
            return [
                'estado_hdr'       => '',
                'estado_sii'       => null,
                'glosa'            => 'ConnectionException: ' . $e->getMessage(),
                'http_status'      => 0,
                'response_body'    => '',
                'transport_failed' => true,
            ];
        }

        $body = (string) $response->body();

        if ($response->failed()) {
            return [
                'estado_hdr'       => '',
                'estado_sii'       => null,
                'glosa'            => "HTTP {$response->status()}",
                'http_status'      => (int) $response->status(),
                'response_body'    => $body,
                'transport_failed' => true,
            ];
        }

        try {
            $parsed = $this->parsearRespuesta($body);
        } catch (Throwable $e) {
            Log::channel('sii')->warning('Fallo parseo respuesta QueryEstUp.', [
                'track_id' => $trackId,
                'error'    => $e->getMessage(),
                'head'     => substr($body, 0, 200),
            ]);
            throw PollingSiiException::respuestaSinEstado($body);
        }

        return array_merge($parsed, [
            'http_status'      => (int) $response->status(),
            'response_body'    => $body,
            'transport_failed' => false,
        ]);
    }

    private function construirSoapGetEstUp(string $rutCompany, string $dvCompany, string $trackId, string $token): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:def="http://DefaultNamespace">
  <soapenv:Header/>
  <soapenv:Body>
    <def:getEstUp>
      <RutCompania>{$rutCompany}</RutCompania>
      <DvCompania>{$dvCompany}</DvCompania>
      <TrackId>{$trackId}</TrackId>
      <Token>{$token}</Token>
    </def:getEstUp>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * Parsea SOAP envelope con CDATA. Extrae:
     *  - RESP_HDR.ESTADO (control: 00=OK, 99=token expirado).
     *  - RESP_BODY.ESTADO (estado real: EPR/EOK/RPR/etc).
     *  - GLOSA (mensaje del SII).
     *
     * @return array{estado_hdr: string, estado_sii: string|null, glosa: string|null}
     */
    public function parsearRespuesta(string $soapBody): array
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            if (! @$dom->loadXML($soapBody)) {
                throw PollingSiiException::respuestaSinEstado('respuesta no es XML parseable');
            }

            $returns = $dom->getElementsByTagName('getEstUpReturn');
            if ($returns->length === 0) {
                throw PollingSiiException::respuestaSinEstado(
                    'no se encontro <getEstUpReturn> en respuesta SOAP'
                );
            }
            $xmlSii = trim((string) $returns->item(0)->textContent);
            if ($xmlSii === '') {
                throw PollingSiiException::respuestaSinEstado('<getEstUpReturn> vacio');
            }

            $domSii = new DOMDocument();
            if (! @$domSii->loadXML($xmlSii)) {
                throw PollingSiiException::respuestaSinEstado('XML SII interno no parseable');
            }

            // El SII responde con un envelope SII:RESPUESTA con RESP_HDR + RESP_BODY.
            // RESP_HDR.ESTADO es el codigo de control: 00 = OK, 99 = sesion expirada.
            // RESP_BODY tiene el ESTADO real del envio (EPR/EOK/RPR/LOC/etc).
            $estadoHdr  = $this->extraerTextoDe($domSii, 'RESP_HDR', 'ESTADO') ?? '';
            $estadoBody = $this->extraerTextoDe($domSii, 'RESP_BODY', 'ESTADO');
            $glosa      = $this->extraerTextoDe($domSii, 'RESP_HDR', 'GLOSA')
                       ?? $this->extraerTextoDe($domSii, 'RESP_BODY', 'GLOSA');

            return [
                'estado_hdr' => $estadoHdr,
                'estado_sii' => $estadoBody,
                'glosa'      => $glosa,
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    /**
     * Busca <$childTag> dentro del primer <$parentTag> del DOM.
     */
    private function extraerTextoDe(DOMDocument $dom, string $parentTag, string $childTag): ?string
    {
        $parents = $dom->getElementsByTagName($parentTag);
        if ($parents->length === 0) {
            return null;
        }
        foreach ($parents->item(0)->getElementsByTagName($childTag) as $nodo) {
            return trim((string) $nodo->textContent);
        }
        return null;
    }
}
