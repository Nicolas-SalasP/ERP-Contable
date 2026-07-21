<?php

namespace App\Domains\Sii\Services\Ws\Boleta;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\CertificadoInvalidoException;
use App\Domains\Sii\Exceptions\SiiAutenticacionException;
use App\Domains\Sii\Exceptions\SiiConfiguracionIncompletaException;
use App\Domains\Sii\Models\SiiTokenSesion;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use App\Domains\Sii\Services\Ws\SiiSeedSigner;
use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquesta la autenticacion contra la API REST de Boleta Electronica (39/41) y persiste la
 * sesion en sii_token_sesion con ambito='boleta' -- el SII exige un token especifico para
 * boleta, no reutilizable con el de Factura/NC/ND (ver SiiTokenService, ambito='factura').
 * Reutiliza SiiSeedSigner sin cambios: la estructura y firma XMLDSig del payload <getToken> es
 * identica para ambos flujos segun el spec del SII, solo cambia el transporte (POST XML plano
 * con Content-Type: application/xml, sin el envoltorio SOAP del WS legacy).
 */
class SiiBoletaTokenService
{
    private const TTL_MINUTOS = 50;

    private const HTTP_TIMEOUT_SEGUNDOS = 30;

    private const HTTP_RETRIES = 3;

    private const HTTP_RETRY_DELAY_MS = 1000;

    private const ESTADO_OK = '00';

    public function __construct(
        private readonly SiiBoletaSeedService $seedService,
        private readonly SiiSeedSigner $seedSigner,
        private readonly CertificadoService $certificadoService
    ) {}

    /**
     * @throws SiiConfiguracionIncompletaException si empresa no tiene config valida.
     * @throws CertificadoInvalidoException si cert inactivo.
     * @throws SiiAutenticacionException si el WS SII falla.
     */
    public function obtenerSesionActiva(Empresa $empresa): SiiTokenSesion
    {
        $this->validarConfiguracion($empresa);

        $sesionActiva = SiiTokenSesion::query()
            ->porEmpresa($empresa->id)
            ->porAmbiente($empresa->ambiente_sii)
            ->porAmbito(SiiTokenSesion::AMBITO_BOLETA)
            ->activa()
            ->orderByDesc('fecha_expiracion')
            ->first();

        if ($sesionActiva !== null) {
            $sesionActiva->registrarUso();

            return $sesionActiva->fresh();
        }

        return $this->generarSesionNueva($empresa);
    }

    public function generarSesionNueva(Empresa $empresa): SiiTokenSesion
    {
        $this->validarConfiguracion($empresa);

        $ambiente = $empresa->ambiente_sii;

        $semilla = $this->seedService->obtener($ambiente);

        $xmlFirmado = $this->seedSigner->firmar($semilla, $empresa);
        $hashFirmaSemilla = hash('sha256', $xmlFirmado);

        $token = $this->postGetToken($xmlFirmado, $ambiente);

        $sesion = SiiTokenSesion::create([
            'empresa_id' => $empresa->id,
            'ambiente' => $ambiente,
            'ambito' => SiiTokenSesion::AMBITO_BOLETA,
            'token' => $token,
            'semilla_usada' => $semilla,
            'hash_firma_semilla' => $hashFirmaSemilla,
            'fecha_obtencion' => now(),
            'fecha_expiracion' => now()->addMinutes(self::TTL_MINUTOS),
            'intentos_uso' => 1,
            'ultimo_uso_en' => now(),
        ]);

        Log::channel('sii')->info('Token SII (boleta) obtenido', [
            'empresa_id' => $empresa->id,
            'ambiente' => $ambiente,
            'token_truncado' => substr($token, 0, 8).'...',
            'fecha_expiracion' => $sesion->fecha_expiracion->toIso8601String(),
        ]);

        return $sesion;
    }

    private function validarConfiguracion(Empresa $empresa): void
    {
        if (
            $empresa->ambiente_sii === SiiTokenSesion::AMBIENTE_PRODUCCION
            && empty($empresa->resolucion_sii_numero)
        ) {
            throw SiiConfiguracionIncompletaException::ambienteProdSinResolucion($empresa->id);
        }

        $this->certificadoService->extraerParPemDeEmpresa($empresa);
    }

    private function postGetToken(string $xmlFirmado, string $ambiente): string
    {
        $url = config("sii.urls_boleta.{$ambiente}.token");
        if (! is_string($url) || $url === '') {
            throw SiiAutenticacionException::tokenInvalido(
                "config('sii.urls_boleta.{$ambiente}.token') no definido"
            );
        }

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT_SEGUNDOS)
                ->retry(self::HTTP_RETRIES, self::HTTP_RETRY_DELAY_MS, throw: false)
                ->withBody($xmlFirmado, 'application/xml')
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

    private function extraerTokenDeRespuesta(string $xmlBody): string
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument;
            if (! @$dom->loadXML($xmlBody)) {
                throw SiiAutenticacionException::tokenInvalido('respuesta no es XML parseable (boleta)');
            }

            $estadoNodos = $dom->getElementsByTagName('ESTADO');
            if ($estadoNodos->length === 0) {
                throw SiiAutenticacionException::tokenInvalido('falta <ESTADO> en respuesta SII (boleta)');
            }
            $estado = trim((string) $estadoNodos->item(0)->textContent);
            if ($estado !== self::ESTADO_OK) {
                $glosaNodos = $dom->getElementsByTagName('GLOSA');
                $glosa = $glosaNodos->length > 0 ? trim((string) $glosaNodos->item(0)->textContent) : '';
                throw SiiAutenticacionException::tokenInvalido(
                    "SII (boleta) respondio ESTADO={$estado}".($glosa !== '' ? " GLOSA={$glosa}" : '')
                );
            }

            $tokenNodos = $dom->getElementsByTagName('TOKEN');
            if ($tokenNodos->length === 0) {
                throw SiiAutenticacionException::tokenInvalido('falta <TOKEN> en respuesta SII (boleta)');
            }
            $token = trim((string) $tokenNodos->item(0)->textContent);
            if ($token === '') {
                throw SiiAutenticacionException::tokenInvalido('<TOKEN> vacio (boleta)');
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
