<?php

namespace App\Domains\Sii\Services\Ws;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * F5.2 — Cliente HTTP del endpoint DTEUpload (CGI legacy) del SII Chile.
 *
 * A diferencia de los WS SOAP de F5.1, DTEUpload recibe POST multipart/form-data
 * con el token en una cookie HTTP "TOKEN". La respuesta NO es XML estandar:
 * es texto plano o HTML con campos TRACKID/ERROR/GLOSA que extraemos con regex.
 *
 * NO orquesta — solo hace el POST y parsea. La orquestacion (validaciones,
 * persistencia, reintentos con nueva sesion) vive en EnvioSiiService.
 */
class SiiUploadService
{
    private const USER_AGENT_DEFAULT = 'Mozilla/4.0 (compatible; PROG 1.0; Windows NT 5.0; YComp 5.0.2.4)';

    /** Defaults para operacion real; override via config para tests. */
    private const HTTP_TIMEOUT_SEGUNDOS_DEFAULT = 30;
    private const HTTP_RETRIES_DEFAULT          = 3;
    private const HTTP_RETRY_DELAY_MS_DEFAULT   = 60000;

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
        string $xmlEnvioDte,
        string $rutSender,
        string $dvSender,
        string $rutCompany,
        string $dvCompany,
        string $token,
        string $ambiente
    ): array {
        $url = (string) config("sii.urls.{$ambiente}.upload");
        $userAgent = (string) config('sii.upload.user_agent', self::USER_AGENT_DEFAULT);

        $requestBodyParaAuditoria = $this->reconstruirRequestParaAuditoria(
            $url,
            $userAgent,
            $rutSender,
            $dvSender,
            $rutCompany,
            $dvCompany
        );

        $timeout    = (int) config('sii.upload.timeout_seconds', self::HTTP_TIMEOUT_SEGUNDOS_DEFAULT);
        $retries    = (int) config('sii.upload.retries', self::HTTP_RETRIES_DEFAULT);
        $retryDelay = (int) config('sii.upload.retry_delay_ms', self::HTTP_RETRY_DELAY_MS_DEFAULT);

        try {
            $response = Http::timeout($timeout)
                ->retry($retries, $retryDelay, throw: false)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Cookie'     => "TOKEN={$token}",
                ])
                ->attach('archivo', $xmlEnvioDte, 'envio.xml', ['Content-Type' => 'text/xml'])
                ->post($url, [
                    'rutSender'  => $rutSender,
                    'dvSender'   => $dvSender,
                    'rutCompany' => $rutCompany,
                    'dvCompany'  => $dvCompany,
                ]);
        } catch (ConnectionException $e) {
            return [
                'track_id'         => null,
                'error_code'       => -1,
                'glosa'            => 'ConnectionException: ' . $e->getMessage(),
                'request_body'     => $requestBodyParaAuditoria,
                'response_body'    => '',
                'http_status'      => 0,
                'transport_failed' => true,
            ];
        }

        $body   = (string) $response->body();
        $parsed = $this->parsearRespuesta($body);

        return [
            'track_id'         => $parsed['track_id'],
            'error_code'       => $parsed['error_code'],
            'glosa'            => $parsed['glosa'],
            'request_body'     => $requestBodyParaAuditoria,
            'response_body'    => $body,
            'http_status'      => (int) $response->status(),
            'transport_failed' => $response->failed(),
        ];
    }

    /**
     * Extrae TRACKID, ERROR, GLOSA de la respuesta texto/HTML del SII.
     * Regex case-insensitive, multilinea para tolerar HTML embebido.
     *
     * @return array{track_id: string|null, error_code: int, glosa: string|null}
     */
    public function parsearRespuesta(string $body): array
    {
        $trackId    = null;
        $errorCode  = -1;
        $glosa      = null;

        // Usamos \h* (horizontal whitespace: solo espacio/tab, NO newline)
        // entre el label y el valor para no consumir saltos de linea y capturar
        // accidentalmente el label siguiente. Permitimos alfanumerico+guiones/_
        // por defensividad (track_id real es numerico).
        if (preg_match('/TRACKID:\h*([A-Za-z0-9_\-]+)/i', $body, $m)) {
            $trackId = $m[1];
        }
        if (preg_match('/ERROR:\h*(-?\d+)/i', $body, $m)) {
            $errorCode = (int) $m[1];
        }
        if (preg_match('/GLOSA:\h*(.+?)(?:\r?\n|<|$)/i', $body, $m)) {
            $glosa = trim($m[1]);
        }

        return [
            'track_id'   => $trackId,
            'error_code' => $errorCode,
            'glosa'      => $glosa,
        ];
    }

    /**
     * Reconstruye una representacion textual del request para persistir como
     * auditoria. IMPORTANTE: NO incluimos el XML del archivo (ya esta en
     * sii_dte_emitido.xml_completo_cifrado, no duplicamos GB de XML aqui).
     * El TOKEN se redacta para no exponerlo en backups de BD.
     */
    private function reconstruirRequestParaAuditoria(
        string $url,
        string $userAgent,
        string $rutSender,
        string $dvSender,
        string $rutCompany,
        string $dvCompany
    ): string {
        return json_encode([
            'url'     => $url,
            'method'  => 'POST',
            'headers' => [
                'User-Agent' => $userAgent,
                'Cookie'     => 'TOKEN=[REDACTED]',
            ],
            'form'    => [
                'rutSender'  => $rutSender,
                'dvSender'   => $dvSender,
                'rutCompany' => $rutCompany,
                'dvCompany'  => $dvCompany,
            ],
            'attach'  => [
                'archivo' => '[XML del DTE persistido en sii_dte_emitido.xml_path]',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
