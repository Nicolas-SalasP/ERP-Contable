<?php

namespace App\Domains\Sii\Services\Ws;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\SiiAutenticacionException;
use App\Domains\Sii\Exceptions\SiiConfiguracionIncompletaException;
use App\Domains\Sii\Models\SiiTokenSesion;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F5.1 — Orquesta el flujo completo de autenticacion con el WS SII y persiste
 * la sesion en sii_token_sesion. Reutiliza tokens activos para minimizar
 * autenticaciones contra el SII.
 *
 * Flujo:
 *   1. obtenerSesionActiva() busca sesion vigente (empresa+ambiente, no expirada).
 *      Si existe -> registrarUso() e incrementa contador, retorna.
 *   2. Si no, generarSesionNueva() ejecuta el flujo completo:
 *      a. SiiSeedService::obtener() -> semilla del SII.
 *      b. SiiSeedSigner::firmar() -> XML firmado con cert empresa.
 *      c. POST a getToken con el XML firmado -> extrae <TOKEN>.
 *      d. Persiste sesion con TTL conservador (50 min vs ~60 min real).
 */
class SiiTokenService
{
    /** Margen de seguridad: SII dice ~60 min, persistimos con 50 para no usar token expirando. */
    private const TTL_MINUTOS = 50;

    private const HTTP_TIMEOUT_SEGUNDOS = 30;
    private const HTTP_RETRIES          = 3;
    private const HTTP_RETRY_DELAY_MS   = 1000;

    private const ESTADO_OK = '00';

    public function __construct(
        private readonly SiiSeedService $seedService,
        private readonly SiiSeedSigner $seedSigner,
        private readonly CertificadoService $certificadoService
    ) {
    }

    /**
     * Retorna sesion activa, generando una nueva si no hay vigente.
     *
     * @throws SiiConfiguracionIncompletaException si empresa no tiene config valida.
     * @throws \App\Domains\Sii\Exceptions\CertificadoInvalidoException si cert inactivo.
     * @throws SiiAutenticacionException si el WS SII falla.
     */
    public function obtenerSesionActiva(Empresa $empresa): SiiTokenSesion
    {
        $this->validarConfiguracion($empresa);

        $sesionActiva = SiiTokenSesion::query()
            ->porEmpresa($empresa->id)
            ->porAmbiente($empresa->ambiente_sii)
            ->activa()
            ->orderByDesc('fecha_expiracion')
            ->first();

        if ($sesionActiva !== null) {
            $sesionActiva->registrarUso();
            return $sesionActiva->fresh();
        }

        return $this->generarSesionNueva($empresa);
    }

    /**
     * Fuerza la generacion de una nueva sesion (ignora el cache).
     */
    public function generarSesionNueva(Empresa $empresa): SiiTokenSesion
    {
        $this->validarConfiguracion($empresa);

        $ambiente = $empresa->ambiente_sii;

        // a) Semilla.
        $semilla = $this->seedService->obtener($ambiente);

        // b) Firmar.
        $xmlFirmado       = $this->seedSigner->firmar($semilla, $empresa);
        $hashFirmaSemilla = hash('sha256', $xmlFirmado);

        // c) POST al endpoint getToken.
        $token = $this->postGetToken($xmlFirmado, $ambiente);

        // d) Persistir.
        $sesion = SiiTokenSesion::create([
            'empresa_id'         => $empresa->id,
            'ambiente'           => $ambiente,
            'token'              => $token,
            'semilla_usada'      => $semilla,
            'hash_firma_semilla' => $hashFirmaSemilla,
            'fecha_obtencion'    => now(),
            'fecha_expiracion'   => now()->addMinutes(self::TTL_MINUTOS),
            'intentos_uso'       => 1,
            'ultimo_uso_en'      => now(),
        ]);

        Log::channel('sii')->info('Token SII obtenido', [
            'empresa_id'       => $empresa->id,
            'ambiente'         => $ambiente,
            'token_truncado'   => substr($token, 0, 8) . '...',
            'fecha_expiracion' => $sesion->fecha_expiracion->toIso8601String(),
        ]);

        return $sesion;
    }

    private function validarConfiguracion(Empresa $empresa): void
    {
        // Bloqueo PROD sin resolucion (decision D6=A).
        if (
            $empresa->ambiente_sii === SiiTokenSesion::AMBIENTE_PRODUCCION
            && empty($empresa->resolucion_sii_numero)
        ) {
            throw SiiConfiguracionIncompletaException::ambienteProdSinResolucion($empresa->id);
        }

        // Verifica cert activo upfront: lanza CertificadoInvalidoException si no hay.
        // Asi fallamos antes de pegarle al SII si la empresa no tiene cert.
        $this->certificadoService->extraerParPemDeEmpresa($empresa);
    }

    /**
     * Envia el XML getToken firmado al WS y extrae el TOKEN.
     */
    private function postGetToken(string $xmlFirmado, string $ambiente): string
    {
        $url = config("sii.urls.{$ambiente}.token");
        if (! is_string($url) || $url === '') {
            throw SiiAutenticacionException::tokenInvalido(
                "config('sii.urls.{$ambiente}.token') no definido"
            );
        }

        $soapBody = $this->construirSoapGetToken($xmlFirmado);

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
            throw SiiAutenticacionException::tokenNoObtenido(
                $response->status(),
                $response->body()
            );
        }

        return $this->extraerTokenDeRespuesta($response->body());
    }

    /**
     * SOAP envelope para getToken. El xmlFirmado va embebido como string
     * dentro de <pszXml><![CDATA[...]]></pszXml>.
     */
    private function construirSoapGetToken(string $xmlFirmado): string
    {
        // Escapamos CDATA-terminators por seguridad (defensive). El xmlFirmado
        // no debe contener "]]>" pero blindamos.
        $payload = str_replace(']]>', ']]]]><![CDATA[>', $xmlFirmado);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:def="http://DefaultNamespace">
  <soapenv:Header/>
  <soapenv:Body>
    <def:getToken>
      <pszXml><![CDATA[{$payload}]]></pszXml>
    </def:getToken>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function extraerTokenDeRespuesta(string $soapBody): string
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            if (! @$dom->loadXML($soapBody)) {
                throw SiiAutenticacionException::tokenInvalido('respuesta no es XML parseable');
            }

            $returns = $dom->getElementsByTagName('getTokenReturn');
            if ($returns->length === 0) {
                throw SiiAutenticacionException::tokenInvalido(
                    'no se encontro <getTokenReturn> en la respuesta SOAP'
                );
            }
            $xmlSii = trim((string) $returns->item(0)->textContent);
            if ($xmlSii === '') {
                throw SiiAutenticacionException::tokenInvalido('<getTokenReturn> vacio');
            }

            $domSii = new DOMDocument();
            if (! @$domSii->loadXML($xmlSii)) {
                throw SiiAutenticacionException::tokenInvalido('XML SII interno no es parseable');
            }

            $estadoNodos = $domSii->getElementsByTagName('ESTADO');
            if ($estadoNodos->length === 0) {
                throw SiiAutenticacionException::tokenInvalido('falta <ESTADO> en respuesta SII');
            }
            $estado = trim((string) $estadoNodos->item(0)->textContent);
            if ($estado !== self::ESTADO_OK) {
                $glosaNodos = $domSii->getElementsByTagName('GLOSA');
                $glosa      = $glosaNodos->length > 0 ? trim((string) $glosaNodos->item(0)->textContent) : '';
                throw SiiAutenticacionException::tokenInvalido(
                    "SII respondio ESTADO={$estado}" . ($glosa !== '' ? " GLOSA={$glosa}" : '')
                );
            }

            $tokenNodos = $domSii->getElementsByTagName('TOKEN');
            if ($tokenNodos->length === 0) {
                throw SiiAutenticacionException::tokenInvalido('falta <TOKEN> en RESP_BODY');
            }
            $token = trim((string) $tokenNodos->item(0)->textContent);
            if ($token === '') {
                throw SiiAutenticacionException::tokenInvalido('<TOKEN> vacio');
            }

            return $token;
        } catch (SiiAutenticacionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw SiiAutenticacionException::tokenInvalido($e->getMessage());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }
}
