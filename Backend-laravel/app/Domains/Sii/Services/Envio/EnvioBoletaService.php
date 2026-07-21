<?php

namespace App\Domains\Sii\Services\Envio;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\EnvioSiiException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoEvento;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use App\Domains\Sii\Services\Integridad\XmlDteIntegrityService;
use App\Domains\Sii\Services\Ws\Boleta\SiiBoletaTokenService;
use App\Domains\Sii\Services\Ws\Boleta\SiiBoletaUploadService;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquestador del envio de una Boleta Electronica (39/41) firmada al endpoint REST
 * boleta.electronica.envio del SII -- estructuralmente calcado de EnvioSiiService (mismo
 * SiiEnvioDte, mismos estados terminales), pero usa los servicios de auth/upload especificos
 * de boleta (token propio, servidores propios, respuesta JSON). Separado en su propia clase
 * (no un parametro en EnvioSiiService) porque el protocolo HTTP real difiere lo suficiente
 * (JSON vs texto/HTML, servidores distintos) como para que compartir la clase confundiera mas
 * de lo que ahorraria: ver README del dominio Sii, "cuando aterrice (Fase 6-bis), EnvioBoletaService
 * quedara separado de EnvioDteService".
 *
 * NO incluye el polling de estado post-envio (GET boleta.electronica.envio/{rut}-{dv}-{trackid}) --
 * eso y el RCOF (Reporte de Consumo de Folios) quedan fuera de este alcance, documentados como
 * pendiente.
 */
class EnvioBoletaService
{
    private const ERROR_SII_TOKEN_EXPIRADO = 99;

    private const ERROR_SII_OK = 0;

    /** Tipos DTE que son boleta; usado para validar que este service solo procese boletas. */
    private const TIPOS_BOLETA = [39, 41];

    public function __construct(
        private readonly XmlDteIntegrityService $integrityService,
        private readonly SiiBoletaTokenService $tokenService,
        private readonly SiiBoletaUploadService $uploadService,
        private readonly CertificadoService $certificadoService
    ) {}

    /**
     * @throws EnvioSiiException si el DTE no se puede enviar, no es boleta, o el SII rechaza.
     */
    public function enviar(int $dteEmitidoId): SiiEnvioDte
    {
        $envio = DB::transaction(function () use ($dteEmitidoId) {
            $dte = SiiDteEmitido::query()
                ->where('id', $dteEmitidoId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validarEsBoleta($dte);
            $this->validarDtePuedeEnviarse($dte);

            return SiiEnvioDte::create([
                'empresa_id' => $dte->empresa_id,
                'dte_emitido_id' => $dte->id,
                'ambiente_sii' => $dte->empresa->ambiente_sii,
                'estado_envio' => SiiEnvioDte::ESTADO_ENVIANDO,
                'intentos_envio' => 0,
            ]);
        });

        try {
            $envio = $envio->fresh(['dteEmitido.empresa']);
            /** @var SiiDteEmitido $dte */
            $dte = $envio->dteEmitido;
            /** @var Empresa $empresa */
            $empresa = $dte->empresa;

            $xmlEnvio = $this->integrityService->leerVerificado($dte->id);

            $sesion = $this->tokenService->obtenerSesionActiva($empresa);
            $envio->update(['token_sesion_id' => $sesion->id]);

            [$rutSender,  $dvSender] = $this->extraerRutSender($empresa);
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
                $e::class.': '.$e->getMessage(),
                'Excepcion no manejada al enviar boleta al SII'
            );
        }

        if ($resultado['transport_failed'] || $resultado['error_code'] === -1) {
            return $this->marcarErrorTransporte(
                $envio,
                $resultado['http_status'],
                $resultado['response_body'],
                $resultado['glosa'] ?? 'Transport failed tras reintentos (boleta)'
            );
        }

        if ($resultado['error_code'] !== self::ERROR_SII_OK || empty($resultado['track_id'])) {
            return $this->marcarErrorPermanente($envio, $resultado);
        }

        return DB::transaction(function () use ($envio, $resultado) {
            $envio->update([
                'estado_envio' => SiiEnvioDte::ESTADO_ENVIADO,
                'track_id' => $resultado['track_id'],
                'glosa_sii' => $resultado['glosa'],
                'request_body_completo_cifrado' => Crypt::encryptString($resultado['request_body']),
                'respuesta_body_completo_cifrado' => Crypt::encryptString($resultado['response_body']),
                'http_status_ultimo_envio' => $resultado['http_status'],
                'fecha_envio' => now(),
            ]);

            /** @var SiiDteEmitido $dte */
            $dte = SiiDteEmitido::query()
                ->where('id', $envio->dte_emitido_id)
                ->lockForUpdate()
                ->firstOrFail();

            $dte->update([
                'estado' => SiiDteEmitido::ESTADO_ENVIADO_SII,
                'track_id' => $resultado['track_id'],
                'fecha_envio_sii' => now(),
            ]);

            SiiDteEmitidoEvento::registrarEnvio($dte, $resultado['track_id'], [
                'envio_id' => $envio->id,
                'ambiente' => $envio->ambiente_sii,
                'sesion_id' => $envio->token_sesion_id,
                'intentos_envio' => $envio->intentos_envio,
            ]);

            Log::channel('sii')->info('Boleta enviada al SII', [
                'dte_id' => $dte->id,
                'envio_id' => $envio->id,
                'track_id' => $resultado['track_id'],
                'ambiente' => $envio->ambiente_sii,
                'intentos' => $envio->intentos_envio,
            ]);

            return $envio->fresh();
        });
    }

    private function validarEsBoleta(SiiDteEmitido $dte): void
    {
        if (! in_array((int) $dte->tipo_dte, self::TIPOS_BOLETA, true)) {
            throw EnvioSiiException::tipoDteInvalido($dte->id, (int) $dte->tipo_dte, self::class);
        }
    }

    /** Estados de un envio previo que admiten reintentar el envio de la misma boleta. */
    private const ESTADOS_ENVIO_REINTENTABLES = [
        SiiEnvioDte::ESTADO_ERROR_TRANSPORTE,
        SiiEnvioDte::ESTADO_ERROR_TIMEOUT,
        SiiEnvioDte::ESTADO_ERROR_PERMANENTE,
    ];

    private function validarDtePuedeEnviarse(SiiDteEmitido $dte): void
    {
        $envioEnCurso = SiiEnvioDte::query()
            ->where('dte_emitido_id', $dte->id)
            ->where('estado_envio', SiiEnvioDte::ESTADO_ENVIANDO)
            ->first();

        if ($envioEnCurso !== null) {
            throw EnvioSiiException::envioEnCursoOHuerfano($dte->id, $envioEnCurso->id);
        }

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

        if ($dte->estado === SiiDteEmitido::ESTADO_ENVIADO_SII) {
            $ultimoEnvio = SiiEnvioDte::query()
                ->where('dte_emitido_id', $dte->id)
                ->orderByDesc('id')
                ->first();

            if ($ultimoEnvio !== null && in_array($ultimoEnvio->estado_envio, self::ESTADOS_ENVIO_REINTENTABLES, true)) {
                return;
            }
        }

        throw EnvioSiiException::dteNoFirmado($dte->id, (string) $dte->estado);
    }

    /**
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

            if ($intentoToken === 0 && $resultado['error_code'] === self::ERROR_SII_TOKEN_EXPIRADO) {
                Log::channel('sii')->warning('Token SII (boleta) expirado; regenerando sesion y reintentando.', [
                    'envio_id' => $envio->id,
                    'empresa_id' => $empresa->id,
                ]);
                $sesionNueva = $this->tokenService->generarSesionNueva($empresa);
                $envio->update(['token_sesion_id' => $sesionNueva->id]);
                $token = $sesionNueva->token;

                continue;
            }

            return $resultado;
        }

        return $resultado;
    }

    private function marcarErrorTransporte(
        SiiEnvioDte $envio,
        int $httpStatus,
        string $responseBody,
        string $glosa
    ): SiiEnvioDte {
        $envio->update([
            'estado_envio' => SiiEnvioDte::ESTADO_ERROR_TRANSPORTE,
            'glosa_sii' => $glosa,
            'respuesta_body_completo_cifrado' => $responseBody !== ''
                ? Crypt::encryptString($responseBody)
                : null,
            'http_status_ultimo_envio' => $httpStatus,
        ]);

        Log::channel('sii')->error('Envio de boleta marcado como ERROR_TRANSPORTE.', [
            'envio_id' => $envio->id,
            'http_status' => $httpStatus,
            'glosa' => $glosa,
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
            'estado_envio' => SiiEnvioDte::ESTADO_ERROR_PERMANENTE,
            'glosa_sii' => $resultado['glosa'] ?? "SII (boleta) respondio error_code={$resultado['error_code']}",
            'request_body_completo_cifrado' => Crypt::encryptString($resultado['request_body']),
            'respuesta_body_completo_cifrado' => Crypt::encryptString($resultado['response_body']),
            'http_status_ultimo_envio' => $resultado['http_status'],
        ]);

        Log::channel('sii')->error('Envio de boleta marcado como ERROR_PERMANENTE (SII rechazo).', [
            'envio_id' => $envio->id,
            'error_code' => $resultado['error_code'],
            'glosa' => $resultado['glosa'],
            'http_status' => $resultado['http_status'],
        ]);

        return $envio->fresh();
    }

    /**
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
