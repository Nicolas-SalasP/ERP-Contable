<?php

namespace App\Domains\Sii\Services\Envio;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\EnvioSiiException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoEvento;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use App\Domains\Sii\Services\Integridad\XmlDteIntegrityService;
use App\Domains\Sii\Services\Ws\SiiTokenService;
use App\Domains\Sii\Services\Ws\SiiUploadService;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F5.2 — Orquestador del envio de un DTE firmado al WS DTEUpload del SII.
 *
 * Persistencia hibrida (mismo patron que F4.4):
 *
 *   Tx 1 (corta, committed): lock + validar DTE FIRMADO + crear sii_envio_dte
 *        en ENVIANDO con intentos_envio=0. Si la siguiente fase falla, el
 *        envio queda auditable en BD.
 *
 *   Fase 2 (sin tx): leer XML verificado (HARDENING R2), obtener token activo,
 *        extraer RUT sender/company, POST al SII con manejo de ERROR=99.
 *
 *   Tx 3 (committed): actualizar envio con track_id + bodies cifrados +
 *        transicion DTE FIRMADO -> ENVIADO_SII + audit event registrarEnvio.
 *        Atomico: si algo falla, rollback de las tres operaciones.
 *
 *   Catch global: cualquier excepcion de fase 2/3 marca el envio como
 *        ERROR_TRANSPORTE o ERROR_PERMANENTE segun corresponda. El DTE
 *        permanece en FIRMADO.
 */
class EnvioSiiService
{
    /** ERROR del SII que indica token expirado. Sugiere reintento con sesion nueva. */
    private const ERROR_SII_TOKEN_EXPIRADO = 99;

    /** ERROR=0 del SII indica recepcion exitosa del envio. */
    private const ERROR_SII_OK = 0;

    public function __construct(
        private readonly XmlDteIntegrityService $integrityService,
        private readonly SiiTokenService $tokenService,
        private readonly SiiUploadService $uploadService,
        private readonly CertificadoService $certificadoService
    ) {
    }

    /**
     * @throws EnvioSiiException si el DTE no se puede enviar o el SII rechaza.
     */
    public function enviar(int $dteEmitidoId): SiiEnvioDte
    {
        // ---------- Tx 1: validar + crear sii_envio_dte en ENVIANDO ----------
        $envio = DB::transaction(function () use ($dteEmitidoId) {
            $dte = SiiDteEmitido::query()
                ->where('id', $dteEmitidoId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validarDtePuedeEnviarse($dte);

            return SiiEnvioDte::create([
                'empresa_id'     => $dte->empresa_id,
                'dte_emitido_id' => $dte->id,
                'ambiente_sii'   => $dte->empresa->ambiente_sii,
                'estado_envio'   => SiiEnvioDte::ESTADO_ENVIANDO,
                'intentos_envio' => 0,
            ]);
        });

        $envio = $envio->fresh(['dteEmitido.empresa']);
        $dte   = $envio->dteEmitido;
        /** @var Empresa $empresa */
        $empresa = $dte->empresa;

        // ---------- Fase 2: leer XML verificado + obtener token + POST ----------
        try {
            $xmlEnvio = $this->integrityService->leerVerificado($dte->id);

            $sesion = $this->tokenService->obtenerSesionActiva($empresa);
            $envio->update(['token_sesion_id' => $sesion->id]);

            [$rutSender,  $dvSender]  = $this->extraerRutSender($empresa);
            [$rutCompany, $dvCompany] = $this->extraerRutCompany($empresa);

            $resultado = $this->postConReintentoSesionExpirada(
                $envio,
                $empresa,
                $xmlEnvio,
                $rutSender,
                $dvSender,
                $rutCompany,
                $dvCompany,
                $sesion->token
            );
        } catch (Throwable $e) {
            return $this->marcarErrorTransporte(
                $envio,
                0,
                $e::class . ': ' . $e->getMessage(),
                'Excepcion no manejada al enviar al SII'
            );
        }

        // ---------- Procesar respuesta ----------
        if ($resultado['transport_failed'] || $resultado['error_code'] === -1) {
            return $this->marcarErrorTransporte(
                $envio,
                $resultado['http_status'],
                $resultado['response_body'],
                $resultado['glosa'] ?? 'Transport failed tras reintentos'
            );
        }

        if ($resultado['error_code'] !== self::ERROR_SII_OK || empty($resultado['track_id'])) {
            return $this->marcarErrorPermanente($envio, $resultado);
        }

        // ---------- Tx 3: exito atomico ----------
        return DB::transaction(function () use ($envio, $resultado) {
            $envio->update([
                'estado_envio'                    => SiiEnvioDte::ESTADO_ENVIADO,
                'track_id'                        => $resultado['track_id'],
                'glosa_sii'                       => $resultado['glosa'],
                'request_body_completo_cifrado'   => Crypt::encryptString($resultado['request_body']),
                'respuesta_body_completo_cifrado' => Crypt::encryptString($resultado['response_body']),
                'http_status_ultimo_envio'        => $resultado['http_status'],
                'fecha_envio'                     => now(),
            ]);

            $dte = SiiDteEmitido::query()
                ->where('id', $envio->dte_emitido_id)
                ->lockForUpdate()
                ->first();

            $dte->update([
                'estado'          => SiiDteEmitido::ESTADO_ENVIADO_SII,
                'track_id'        => $resultado['track_id'],
                'fecha_envio_sii' => now(),
            ]);

            SiiDteEmitidoEvento::registrarEnvio($dte, $resultado['track_id'], [
                'envio_id'       => $envio->id,
                'ambiente'       => $envio->ambiente_sii,
                'sesion_id'      => $envio->token_sesion_id,
                'intentos_envio' => $envio->intentos_envio,
            ]);

            Log::channel('sii')->info('DTE enviado al SII', [
                'dte_id'    => $dte->id,
                'envio_id'  => $envio->id,
                'track_id'  => $resultado['track_id'],
                'ambiente'  => $envio->ambiente_sii,
                'intentos'  => $envio->intentos_envio,
            ]);

            return $envio->fresh();
        });
    }

    /**
     * @throws EnvioSiiException
     */
    private function validarDtePuedeEnviarse(SiiDteEmitido $dte): void
    {
        if ($dte->estado === SiiDteEmitido::ESTADO_FIRMADO) {
            return;
        }

        $envioPrevio = SiiEnvioDte::query()
            ->where('dte_emitido_id', $dte->id)
            ->exitosos()
            ->orderByDesc('id')
            ->first();

        if ($envioPrevio !== null) {
            throw EnvioSiiException::yaEnviado($dte->id, (string) $envioPrevio->track_id);
        }

        throw EnvioSiiException::dteNoFirmado($dte->id, (string) $dte->estado);
    }

    /**
     * Postea al SII; si responde ERROR=99 (token expirado) regenera la sesion
     * y reintenta UNA vez. Incrementa intentos_envio en cada intento HTTP.
     *
     * @return array{
     *   track_id: string|null, error_code: int, glosa: string|null,
     *   request_body: string, response_body: string, http_status: int,
     *   transport_failed: bool
     * }
     */
    private function postConReintentoSesionExpirada(
        SiiEnvioDte $envio,
        Empresa $empresa,
        string $xmlEnvio,
        string $rutSender,
        string $dvSender,
        string $rutCompany,
        string $dvCompany,
        string $token
    ): array {
        for ($intentoToken = 0; $intentoToken < 2; $intentoToken++) {
            $envio->increment('intentos_envio');

            $resultado = $this->uploadService->subir(
                $xmlEnvio,
                $rutSender,
                $dvSender,
                $rutCompany,
                $dvCompany,
                $token,
                $empresa->ambiente_sii
            );

            // Solo reintentamos UNA vez en caso de token expirado (intentoToken=0).
            if ($intentoToken === 0 && $resultado['error_code'] === self::ERROR_SII_TOKEN_EXPIRADO) {
                Log::channel('sii')->warning('Token SII expirado; regenerando sesion y reintentando.', [
                    'envio_id'   => $envio->id,
                    'empresa_id' => $empresa->id,
                ]);
                $sesionNueva = $this->tokenService->generarSesionNueva($empresa);
                $envio->update(['token_sesion_id' => $sesionNueva->id]);
                $token = $sesionNueva->token;
                continue;
            }

            return $resultado;
        }

        // Caso defensivo (loop completo sin return): devolver ultimo resultado.
        return $resultado;
    }

    private function marcarErrorTransporte(
        SiiEnvioDte $envio,
        int $httpStatus,
        string $responseBody,
        string $glosa
    ): SiiEnvioDte {
        $envio->update([
            'estado_envio'                    => SiiEnvioDte::ESTADO_ERROR_TRANSPORTE,
            'glosa_sii'                       => $glosa,
            'respuesta_body_completo_cifrado' => $responseBody !== ''
                ? Crypt::encryptString($responseBody)
                : null,
            'http_status_ultimo_envio'        => $httpStatus,
        ]);

        Log::channel('sii')->error('Envio DTE marcado como ERROR_TRANSPORTE.', [
            'envio_id'    => $envio->id,
            'http_status' => $httpStatus,
            'glosa'       => $glosa,
        ]);

        return $envio->fresh();
    }

    /**
     * @param array{
     *   track_id: string|null, error_code: int, glosa: string|null,
     *   request_body: string, response_body: string, http_status: int,
     *   transport_failed: bool
     * } $resultado
     */
    private function marcarErrorPermanente(SiiEnvioDte $envio, array $resultado): SiiEnvioDte
    {
        $envio->update([
            'estado_envio'                    => SiiEnvioDte::ESTADO_ERROR_PERMANENTE,
            'glosa_sii'                       => $resultado['glosa'] ?? "SII respondio ERROR={$resultado['error_code']}",
            'request_body_completo_cifrado'   => Crypt::encryptString($resultado['request_body']),
            'respuesta_body_completo_cifrado' => Crypt::encryptString($resultado['response_body']),
            'http_status_ultimo_envio'        => $resultado['http_status'],
        ]);

        Log::channel('sii')->error('Envio DTE marcado como ERROR_PERMANENTE (SII rechazo).', [
            'envio_id'    => $envio->id,
            'error_code'  => $resultado['error_code'],
            'glosa'       => $resultado['glosa'],
            'http_status' => $resultado['http_status'],
        ]);

        return $envio->fresh();
    }

    /**
     * El RUT del FIRMANTE (sender) viene del subject del certificado digital.
     * Puede diferir del RUT de la empresa en escenarios de delegacion
     * (operador contable firma para varias empresas).
     *
     * @return array{0: string, 1: string} [rutSinDv, dv]
     */
    private function extraerRutSender(Empresa $empresa): array
    {
        $rutNormalizado = $this->certificadoService->extraerRutDelSujeto($empresa);
        return [
            (string) RutHelper::extraerNumero($rutNormalizado),
            RutHelper::extraerDv($rutNormalizado),
        ];
    }

    /**
     * RUT de la EMPRESA emisora (company), separado en numero + DV.
     *
     * @return array{0: string, 1: string} [rutSinDv, dv]
     */
    private function extraerRutCompany(Empresa $empresa): array
    {
        return [
            (string) RutHelper::extraerNumero($empresa->rut),
            RutHelper::extraerDv($empresa->rut),
        ];
    }
}
