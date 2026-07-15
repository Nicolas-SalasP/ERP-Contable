<?php

namespace App\Domains\Sii\Services\Ws\Boleta;

use App\Domains\Sii\Exceptions\SiiAutenticacionException;
use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Obtiene una semilla para autenticar contra la API REST de Boleta Electronica (39/41) --
 * endpoint y servidores DISTINTOS a los de Factura/NC/ND (ver config('sii.urls_boleta')).
 * Confirmado contra el spec OpenAPI oficial del SII: GET /boleta.electronica.semilla, sin el
 * envoltorio SOAP del WS legacy de Factura (SiiSeedService). El body de respuesta es XML plano
 * con la misma estructura interna RESP_HDR/ESTADO + RESP_BODY/SEMILLA que el resto de los WS
 * del SII -- el parseo es tag-name-agnostic (busca <ESTADO>/<SEMILLA> en cualquier namespace)
 * para tolerar variaciones menores de envoltorio no confirmadas al 100% sin acceso al ambiente
 * real de certificacion.
 */
class SiiBoletaSeedService
{
    private const HTTP_TIMEOUT_SEGUNDOS = 30;

    private const HTTP_RETRIES = 3;

    private const HTTP_RETRY_DELAY_MS = 1000;

    private const ESTADO_OK = '00';

    public function obtener(string $ambiente): string
    {
        $url = $this->urlPara($ambiente);

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT_SEGUNDOS)
                ->retry(self::HTTP_RETRIES, self::HTTP_RETRY_DELAY_MS, throw: false)
                ->get($url);
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
        $url = config("sii.urls_boleta.{$ambiente}.semilla");
        if (! is_string($url) || $url === '') {
            throw SiiAutenticacionException::semillaInvalida(
                "config('sii.urls_boleta.{$ambiente}.semilla') no definido"
            );
        }

        return $url;
    }

    private function extraerSemillaDeRespuesta(string $xmlBody): string
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument;
            if (! @$dom->loadXML($xmlBody)) {
                throw SiiAutenticacionException::semillaInvalida('respuesta no es XML parseable');
            }

            $estadoNodos = $dom->getElementsByTagName('ESTADO');
            if ($estadoNodos->length === 0) {
                throw SiiAutenticacionException::semillaInvalida('falta <ESTADO> en respuesta SII (boleta)');
            }
            $estado = trim((string) $estadoNodos->item(0)->textContent);
            if ($estado !== self::ESTADO_OK) {
                $glosaNodos = $dom->getElementsByTagName('GLOSA');
                $glosa = $glosaNodos->length > 0 ? trim((string) $glosaNodos->item(0)->textContent) : '';
                throw SiiAutenticacionException::semillaInvalida(
                    "SII (boleta) respondio ESTADO={$estado}".($glosa !== '' ? " GLOSA={$glosa}" : '')
                );
            }

            $semillaNodos = $dom->getElementsByTagName('SEMILLA');
            if ($semillaNodos->length === 0) {
                throw SiiAutenticacionException::semillaInvalida('falta <SEMILLA> en respuesta SII (boleta)');
            }
            $semilla = trim((string) $semillaNodos->item(0)->textContent);
            if ($semilla === '') {
                throw SiiAutenticacionException::semillaInvalida('<SEMILLA> vacia (boleta)');
            }

            return $semilla;
        } catch (SiiAutenticacionException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::channel('sii')->warning('Fallo parseo respuesta semilla SII (boleta).', [
                'error' => $e->getMessage(),
                'response_head' => substr($xmlBody, 0, 200),
            ]);
            throw SiiAutenticacionException::semillaInvalida($e->getMessage());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }
}
