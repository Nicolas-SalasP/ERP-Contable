<?php

namespace App\Domains\Sii\Services\Polling;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoEvento;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Models\SiiEnvioDteEvento;
use App\Domains\Sii\Services\Ws\SiiEstadoUpService;
use App\Domains\Sii\Services\Ws\SiiTokenService;
use App\Domains\Sii\Support\RutHelper;
use Carbon\Carbon;
// Importacion necesaria para el match en mapearEstadosTerminales; el helper
// privado partirRutEnNumeroYDv tambien usa RutHelper.
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F5.3 — Orquestador del polling individual de UN envio SII.
 *
 * Llamado por PollearEnviosPendientesJob para cada envio en ENVIADO que ya
 * toca pollear segun backoff. Lleva adelante:
 *
 *   - Reintento UNA vez con sesion nueva si RESP_HDR.ESTADO=99 (token expirado).
 *   - Mapeo del codigo SII (EPR/EOK/RPR/LOC/etc.) a estado local.
 *   - Transicion atomica del envio + DTE + 2 audit events (envio + DTE).
 *   - Marcado de ERROR_TIMEOUT si excede 10hr acumuladas desde fecha_envio.
 */
class PollearEstadoSiiService
{
    /** Delays (minutos) por intento (1-indexed, clamped al ultimo). */
    private const DELAYS_MINUTOS = [5, 10, 30, 60, 180, 360];

    /** Tope total de horas para considerar el envio en limbo y marcar TIMEOUT. */
    private const TIMEOUT_HORAS_ACUMULADAS = 10;

    /** Jitter ±20% sobre el delay calculado, para evitar thundering herd. */
    private const JITTER_PCT = 20;

    /**
     * Codigos SII oficiales mapeados a acciones locales. Cualquier codigo
     * fuera de esta tabla → 'DESCONOCIDO' y el envio se marca ERROR_PERMANENTE
     * preservando la glosa SII.
     *
     * SOK/CRT/EPR: aun procesando, seguir polleando.
     * EOK/LOK:     aceptado.
     * LOC:         aceptado con reparos.
     * RPR/RCT/RCH/RFR/RSC: rechazado.
     */
    private const MAPEO_ESTADO_SII = [
        'SOK' => 'CONTINUAR',
        'CRT' => 'CONTINUAR',
        'EPR' => 'CONTINUAR',
        'EOK' => 'ACEPTADO',
        'LOK' => 'ACEPTADO',
        'LOC' => 'ACEPTADO_CON_REPAROS',
        'RPR' => 'RECHAZADO',
        'RCT' => 'RECHAZADO',
        'RCH' => 'RECHAZADO',
        'RFR' => 'RECHAZADO',
        'RSC' => 'RECHAZADO',
    ];

    /** ESTADO del RESP_HDR que indica token expirado. */
    private const ESTADO_HDR_TOKEN_EXPIRADO = '99';

    public function __construct(
        private readonly SiiEstadoUpService $estadoUpService,
        private readonly SiiTokenService $tokenService
    ) {
    }

    /**
     * Pollea UN envio. Idempotente: si ya esta resuelto, retorna sin tocar SII.
     */
    public function pollear(SiiEnvioDte $envio): SiiEnvioDte
    {
        if ($envio->estado_envio !== SiiEnvioDte::ESTADO_ENVIADO) {
            return $envio;
        }

        if ($this->excedioTimeoutAcumulado($envio)) {
            return $this->marcarTimeout($envio);
        }

        $empresa = $envio->dteEmitido->empresa;

        try {
            $sesion = $this->tokenService->obtenerSesionActiva($empresa);
        } catch (Throwable $e) {
            return $this->registrarErrorTransporteSinTransicion($envio, 0, $e->getMessage());
        }

        [$rutCompany, $dvCompany] = $this->partirRutEnNumeroYDv($empresa->rut);

        $resultado = null;
        $token     = $sesion->token;
        for ($intentoToken = 0; $intentoToken < 2; $intentoToken++) {
            try {
                $resultado = $this->estadoUpService->consultar(
                    $rutCompany,
                    $dvCompany,
                    (string) $envio->track_id,
                    $token,
                    (string) $envio->ambiente_sii
                );
            } catch (Throwable $e) {
                return $this->registrarErrorTransporteSinTransicion($envio, 0, $e->getMessage());
            }

            if ($resultado['transport_failed']) {
                return $this->registrarErrorTransporteSinTransicion(
                    $envio,
                    $resultado['http_status'],
                    $resultado['glosa'] ?? "HTTP {$resultado['http_status']}"
                );
            }

            if ($resultado['estado_hdr'] === self::ESTADO_HDR_TOKEN_EXPIRADO && $intentoToken === 0) {
                Log::channel('sii')->warning('Token expirado durante polling; regenerando sesion.', [
                    'envio_id'   => $envio->id,
                    'track_id'   => $envio->track_id,
                ]);
                $sesion = $this->tokenService->generarSesionNueva($empresa);
                $token  = $sesion->token;
                continue;
            }

            break;
        }

        $codigoSii = $resultado['estado_sii'] ?? '';
        $accion    = self::MAPEO_ESTADO_SII[$codigoSii] ?? 'DESCONOCIDO';

        return DB::transaction(function () use ($envio, $resultado, $codigoSii, $accion, $sesion) {
            /** @var SiiEnvioDte $envioLock */
            $envioLock = SiiEnvioDte::query()
                ->where('id', $envio->id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = $envioLock->estado_envio;

            $envioLock->fecha_ultimo_polling       = now();
            $envioLock->intentos_polling           = $envioLock->intentos_polling + 1;
            $envioLock->http_status_ultimo_polling = $resultado['http_status'];
            $envioLock->estado_sii_ultimo          = $codigoSii !== '' ? $codigoSii : null;
            $envioLock->glosa_sii                  = $resultado['glosa'];
            $envioLock->token_sesion_id            = $sesion->id;
            $envioLock->respuesta_body_completo_cifrado = Crypt::encryptString($resultado['response_body']);

            if ($accion === 'CONTINUAR') {
                $envioLock->save();
                SiiEnvioDteEvento::registrarTransicion(
                    $envioLock,
                    $estadoAnterior,
                    $envioLock->estado_envio,
                    $codigoSii,
                    $resultado['glosa'],
                    $resultado['http_status']
                );
                return $envioLock->fresh();
            }

            if ($accion === 'DESCONOCIDO') {
                $envioLock->estado_envio    = SiiEnvioDte::ESTADO_ERROR_PERMANENTE;
                $envioLock->fecha_resolucion = now();
                $envioLock->save();

                SiiEnvioDteEvento::registrarTransicion(
                    $envioLock,
                    $estadoAnterior,
                    SiiEnvioDte::ESTADO_ERROR_PERMANENTE,
                    $codigoSii,
                    "Codigo SII desconocido: '{$codigoSii}'. GLOSA: " . ($resultado['glosa'] ?? '[sin glosa]'),
                    $resultado['http_status']
                );

                return $envioLock->fresh();
            }

            // Transicion terminal (ACEPTADO / ACEPTADO_CON_REPAROS / RECHAZADO)
            [$nuevoEstadoEnvio, $nuevoEstadoDte] = $this->mapearEstadosTerminales($accion);

            $envioLock->estado_envio    = $nuevoEstadoEnvio;
            $envioLock->fecha_resolucion = now();
            $envioLock->save();

            $dte = SiiDteEmitido::query()
                ->where('id', $envioLock->dte_emitido_id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnteriorDte = $dte->estado;
            $dte->estado    = $nuevoEstadoDte;
            $dte->glosa_sii = $resultado['glosa'];
            if ($nuevoEstadoDte === SiiDteEmitido::ESTADO_ACEPTADO || $nuevoEstadoDte === SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS) {
                $dte->fecha_aceptacion_sii = now();
            } elseif ($nuevoEstadoDte === SiiDteEmitido::ESTADO_RECHAZADO) {
                $dte->fecha_rechazo_sii = now();
            }
            $dte->save();

            // Audit events de ambos (atomicos en la misma transaccion).
            SiiEnvioDteEvento::registrarTransicion(
                $envioLock,
                $estadoAnterior,
                $nuevoEstadoEnvio,
                $codigoSii,
                $resultado['glosa'],
                $resultado['http_status']
            );

            SiiDteEmitidoEvento::create([
                'dte_emitido_id'  => $dte->id,
                'estado_anterior' => $estadoAnteriorDte,
                'estado_nuevo'    => $nuevoEstadoDte,
                'glosa'           => $resultado['glosa'],
                'payload'         => [
                    'codigo_sii' => $codigoSii,
                    'envio_id'   => $envioLock->id,
                    'track_id'   => $envioLock->track_id,
                ],
            ]);

            Log::channel('sii')->info('Polling SII resolvio envio.', [
                'envio_id'    => $envioLock->id,
                'dte_id'      => $dte->id,
                'track_id'    => $envioLock->track_id,
                'codigo_sii'  => $codigoSii,
                'nuevo_estado' => $nuevoEstadoEnvio,
            ]);

            return $envioLock->fresh();
        });
    }

    /**
     * Determina si un envio YA toca pollear segun backoff + jitter.
     * Falso si el envio no esta en ENVIADO (no pollear envios resueltos).
     */
    public function yaTocaPollear(SiiEnvioDte $envio): bool
    {
        if ($envio->estado_envio !== SiiEnvioDte::ESTADO_ENVIADO) {
            return false;
        }

        $idx       = min((int) $envio->intentos_polling, count(self::DELAYS_MINUTOS) - 1);
        $delayMin  = self::DELAYS_MINUTOS[$idx];
        $jitterPct = mt_rand(-self::JITTER_PCT, self::JITTER_PCT);
        $delayConJitter = (int) round($delayMin * (1 + $jitterPct / 100));

        $referencia = $envio->fecha_ultimo_polling ?? $envio->fecha_envio;
        if ($referencia === null) {
            return true;
        }

        return Carbon::parse($referencia)->copy()->addMinutes($delayConJitter)->isPast();
    }

    /**
     * Retorna [delayMinimo, delayMaximo] aplicando ±JITTER_PCT al delay
     * base del intento dado. Util para tests que necesitan verificar
     * el rango sin depender del jitter aleatorio.
     *
     * @return array{0: int, 1: int}
     */
    public function rangoDelayParaIntento(int $intento): array
    {
        $idx      = min(max(0, $intento), count(self::DELAYS_MINUTOS) - 1);
        $delayMin = self::DELAYS_MINUTOS[$idx];
        $low      = (int) floor($delayMin * (1 - self::JITTER_PCT / 100));
        $high     = (int) ceil($delayMin * (1 + self::JITTER_PCT / 100));
        return [$low, $high];
    }

    private function excedioTimeoutAcumulado(SiiEnvioDte $envio): bool
    {
        if ($envio->fecha_envio === null) {
            return false;
        }
        return Carbon::parse($envio->fecha_envio)
            ->copy()
            ->addHours(self::TIMEOUT_HORAS_ACUMULADAS)
            ->isPast();
    }

    private function marcarTimeout(SiiEnvioDte $envio): SiiEnvioDte
    {
        return DB::transaction(function () use ($envio) {
            $lock = SiiEnvioDte::query()->where('id', $envio->id)->lockForUpdate()->firstOrFail();
            $estadoAnterior = $lock->estado_envio;
            $lock->estado_envio    = SiiEnvioDte::ESTADO_ERROR_TIMEOUT;
            $lock->fecha_resolucion = now();
            $lock->save();

            SiiEnvioDteEvento::registrarTimeout($lock, (int) $lock->intentos_polling);

            Log::channel('sii')->warning('Envio marcado ERROR_TIMEOUT por exceder horas acumuladas.', [
                'envio_id' => $lock->id,
                'track_id' => $lock->track_id,
                'intentos' => $lock->intentos_polling,
            ]);

            return $lock->fresh();
        });
    }

    /**
     * Registra un fallo de transporte/red SIN cambiar estado_envio.
     * El envio sigue en ENVIADO esperando el proximo ciclo del job.
     */
    private function registrarErrorTransporteSinTransicion(
        SiiEnvioDte $envio,
        int $httpStatus,
        string $detalle
    ): SiiEnvioDte {
        $envio->refresh();
        $envio->fecha_ultimo_polling       = now();
        $envio->intentos_polling           = $envio->intentos_polling + 1;
        $envio->http_status_ultimo_polling = $httpStatus;
        $envio->save();

        SiiEnvioDteEvento::registrarErrorTransporte($envio, $httpStatus, $detalle);

        Log::channel('sii')->warning('Polling fallo por transporte; envio sigue en ENVIADO.', [
            'envio_id'    => $envio->id,
            'http_status' => $httpStatus,
            'detalle'     => $detalle,
        ]);

        return $envio->fresh();
    }

    /**
     * @return array{0: string, 1: string} [estadoEnvio, estadoDte]
     */
    private function mapearEstadosTerminales(string $accion): array
    {
        return match ($accion) {
            'ACEPTADO' => [
                SiiEnvioDte::ESTADO_ACEPTADO,
                SiiDteEmitido::ESTADO_ACEPTADO,
            ],
            'ACEPTADO_CON_REPAROS' => [
                SiiEnvioDte::ESTADO_ACEPTADO_REPAROS,
                SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS,
            ],
            'RECHAZADO' => [
                SiiEnvioDte::ESTADO_RECHAZADO,
                SiiDteEmitido::ESTADO_RECHAZADO,
            ],
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function partirRutEnNumeroYDv(string $rut): array
    {
        return [
            (string) RutHelper::extraerNumero($rut),
            RutHelper::extraerDv($rut),
        ];
    }
}
