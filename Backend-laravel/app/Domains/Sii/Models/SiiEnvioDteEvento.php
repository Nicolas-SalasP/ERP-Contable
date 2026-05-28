<?php

namespace App\Domains\Sii\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F5.3 — Registro INMUTABLE de un cambio de estado del envio SII.
 *
 * Espejo de SiiDteEmitidoEvento (HARDENING-1 R4) pero scoped al envio.
 * Cada polling + cada transicion terminal insertan una fila aqui dentro
 * de la misma DB::transaction que actualiza el envio (event-sourcing).
 *
 * Sin updated_at: los registros NO se sobreescriben jamas.
 */
class SiiEnvioDteEvento extends Model
{
    protected $table = 'sii_envio_dte_evento';

    public $timestamps = false;

    protected $fillable = [
        'envio_dte_id',
        'estado_anterior',
        'estado_nuevo',
        'glosa',
        'payload',
        'codigo_sii_raw',
        'http_status',
    ];

    protected $casts = [
        'payload'     => 'array',
        'created_at'  => 'datetime',
        'http_status' => 'integer',
    ];

    public function envio(): BelongsTo
    {
        return $this->belongsTo(SiiEnvioDte::class, 'envio_dte_id');
    }

    /**
     * Factory generica para cualquier transicion.
     *
     * @param array<string, mixed> $payloadExtra
     */
    public static function registrarTransicion(
        SiiEnvioDte $envio,
        string $estadoAnterior,
        string $estadoNuevo,
        ?string $codigoSiiRaw = null,
        ?string $glosa = null,
        ?int $httpStatus = null,
        array $payloadExtra = []
    ): self {
        return self::create([
            'envio_dte_id'    => $envio->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo'    => $estadoNuevo,
            'glosa'           => $glosa,
            'codigo_sii_raw'  => $codigoSiiRaw,
            'http_status'     => $httpStatus,
            'payload'         => array_merge([
                'track_id'         => $envio->track_id,
                'intentos_polling' => $envio->intentos_polling,
            ], $payloadExtra),
        ]);
    }

    /**
     * Registra un fallo de transporte durante polling sin alterar
     * estado_envio (el envio sigue en ENVIADO esperando el proximo ciclo).
     */
    public static function registrarErrorTransporte(SiiEnvioDte $envio, int $httpStatus, string $detalle): self
    {
        return self::create([
            'envio_dte_id'    => $envio->id,
            'estado_anterior' => $envio->estado_envio,
            'estado_nuevo'    => $envio->estado_envio,
            'glosa'           => "Error transporte al pollear: {$detalle}",
            'http_status'     => $httpStatus,
            'payload'         => [
                'track_id'         => $envio->track_id,
                'intentos_polling' => $envio->intentos_polling,
                'detalle'          => $detalle,
            ],
        ]);
    }

    /**
     * Registra la transicion automatica a ERROR_TIMEOUT tras exceder el
     * timeout acumulado de polling.
     */
    public static function registrarTimeout(SiiEnvioDte $envio, int $intentos): self
    {
        return self::create([
            'envio_dte_id'    => $envio->id,
            'estado_anterior' => $envio->estado_envio,
            'estado_nuevo'    => SiiEnvioDte::ESTADO_ERROR_TIMEOUT,
            'glosa'           => "Timeout acumulado de polling tras {$intentos} intentos. Intervencion manual requerida.",
            'payload'         => [
                'track_id'         => $envio->track_id,
                'intentos_polling' => $intentos,
            ],
        ]);
    }
}
