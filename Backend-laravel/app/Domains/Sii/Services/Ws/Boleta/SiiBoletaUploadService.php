<?php

namespace App\Domains\Sii\Services\Ws\Boleta;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP del endpoint REST boleta.electronica.envio del SII Chile -- a diferencia del WS
 * legacy DTEUpload (SiiUploadService, texto/HTML parseado por regex), este es un endpoint
 * "recursos/v1" moderno: mismo POST multipart/form-data (rutSender/dvSender/rutCompany/
 * dvCompany/archivo) y mismo mecanismo de token en Cookie, pero la RESPUESTA es JSON. Confirmado
 * contra el spec OpenAPI oficial del SII (openapi.yaml de bolcoreinternetui), schema
 * ResultadoEnvioPost: {rut_emisor, rut_envia, trackid, fecha_recepcion, estado, file}. El envio
 * no valida sincronicamente -- "estado":"REC" solo significa recibido, el resultado real
 * (aceptado/rechazado) se consulta despues via GET (no implementado aun, ver README F6-bis).
 */
class SiiBoletaUploadService
{
    private const USER_AGENT_DEFAULT = 'Mozilla/4.0 (compatible; PROG 1.0; Windows NT 5.0; YComp 5.0.2.4)';

    private const HTTP_TIMEOUT_SEGUNDOS_DEFAULT = 30;

    private const HTTP_RETRIES_DEFAULT = 3;

    private const HTTP_RETRY_DELAY_MS_DEFAULT = 60000;

    /** "estado" que el SII devuelve cuando el envio fue recibido correctamente (no es un veredicto final, ver docblock de clase). */
    private const ESTADO_RECIBIDO = 'REC';

    /**
     * @return array{
     *   track_id: string|null,
     *   error_code: int,
     *   glosa: string|null,
     *   request_body: string,
     *   response_body: string,
     *   http_status: int,
     *   transport_failed: bool
     * }
     */
    public function subir(
        string $xmlEnvioBoleta,
        string $rutSender,
        string $dvSender,
        string $rutCompany,
        string $dvCompany,
        string $token,
        string $ambiente
    ): array {
        $url = (string) config("sii.urls_boleta.{$ambiente}.envio");
        $userAgent = (string) config('sii.upload.user_agent', self::USER_AGENT_DEFAULT);

        $requestBodyParaAuditoria = $this->reconstruirRequestParaAuditoria(
            $url,
            $userAgent,
            $rutSender,
            $dvSender,
            $rutCompany,
            $dvCompany
        );

        $timeout = (int) config('sii.upload.timeout_seconds', self::HTTP_TIMEOUT_SEGUNDOS_DEFAULT);
        $retries = (int) config('sii.upload.retries', self::HTTP_RETRIES_DEFAULT);
        $retryDelay = (int) config('sii.upload.retry_delay_ms', self::HTTP_RETRY_DELAY_MS_DEFAULT);

        try {
            $response = Http::timeout($timeout)
                ->retry($retries, $retryDelay, throw: false)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Cookie' => "TOKEN={$token}",
                ])
                ->attach('archivo', $xmlEnvioBoleta, 'envio.xml', ['Content-Type' => 'text/xml'])
                ->post($url, [
                    'rutSender' => $rutSender,
                    'dvSender' => $dvSender,
                    'rutCompany' => $rutCompany,
                    'dvCompany' => $dvCompany,
                ]);
        } catch (ConnectionException $e) {
            return [
                'track_id' => null,
                'error_code' => -1,
                'glosa' => 'ConnectionException: '.$e->getMessage(),
                'request_body' => $requestBodyParaAuditoria,
                'response_body' => '',
                'http_status' => 0,
                'transport_failed' => true,
            ];
        }

        $body = (string) $response->body();
        $parsed = $this->parsearRespuesta($body, $response->status());

        return [
            'track_id' => $parsed['track_id'],
            'error_code' => $parsed['error_code'],
            'glosa' => $parsed['glosa'],
            'request_body' => $requestBodyParaAuditoria,
            'response_body' => $body,
            'http_status' => (int) $response->status(),
            'transport_failed' => $response->failed() && $response->status() !== 400 && $response->status() !== 401,
        ];
    }

    /**
     * Traduce la respuesta JSON de boleta.electronica.envio al mismo vocabulario
     * (track_id/error_code/glosa) que usa el flujo de Factura, para que EnvioBoletaService
     * pueda reusar el mismo estado terminal que EnvioSiiService: error_code=0 significa "ok".
     *
     * @return array{track_id: string|null, error_code: int, glosa: string|null}
     */
    public function parsearRespuesta(string $body, int $httpStatus): array
    {
        $decoded = json_decode($body, true);

        if ($httpStatus === 200 && is_array($decoded) && isset($decoded['estado'])) {
            $esOk = $decoded['estado'] === self::ESTADO_RECIBIDO;

            return [
                'track_id' => isset($decoded['trackid']) ? (string) $decoded['trackid'] : null,
                'error_code' => $esOk ? 0 : -2,
                'glosa' => $esOk ? null : ('SII (boleta) respondio estado='.$decoded['estado']),
            ];
        }

        // 400/401/405/500 u otro cuerpo inesperado: sin JSON valido, tratamos como error permanente
        // (no reintentable via token-refresh, salvo 401 que EnvioBoletaService puede interpretar
        // como token expirado igual que el flujo legacy).
        $glosa = is_array($decoded) && isset($decoded['message'])
            ? (string) $decoded['message']
            : ('HTTP '.$httpStatus.($body !== '' ? ': '.substr($body, 0, 300) : ''));

        return [
            'track_id' => null,
            'error_code' => $httpStatus === 401 ? 99 : -2,
            'glosa' => $glosa,
        ];
    }

    private function reconstruirRequestParaAuditoria(
        string $url,
        string $userAgent,
        string $rutSender,
        string $dvSender,
        string $rutCompany,
        string $dvCompany
    ): string {
        return json_encode([
            'url' => $url,
            'method' => 'POST',
            'headers' => [
                'User-Agent' => $userAgent,
                'Cookie' => 'TOKEN=[REDACTED]',
            ],
            'form' => [
                'rutSender' => $rutSender,
                'dvSender' => $dvSender,
                'rutCompany' => $rutCompany,
                'dvCompany' => $dvCompany,
            ],
            'attach' => [
                'archivo' => '[XML de la Boleta persistido en sii_dte_emitido.xml_path]',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
