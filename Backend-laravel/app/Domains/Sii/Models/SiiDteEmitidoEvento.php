<?php

namespace App\Domains\Sii\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HARDENING-1 R4 — Registro INMUTABLE de un cambio de estado del DTE.
 *
 * Se crea siempre dentro de la misma DB::transaction que la transicion de
 * estado, garantizando atomicidad event-sourcing-like. Si el rollback se
 * dispara, el evento desaparece junto con la transicion fallida.
 *
 * Por diseño NO tiene updated_at: los registros no se sobreescriben nunca.
 * Para corregir errores se crea un nuevo evento (compensating event).
 */
class SiiDteEmitidoEvento extends Model
{
    protected $table = 'sii_dte_emitido_evento';

    /** Solo created_at (sin updated_at): registros inmutables. */
    public $timestamps = false;

    protected $fillable = [
        'dte_emitido_id',
        'estado_anterior',
        'estado_nuevo',
        'glosa',
        'payload',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function dteEmitido(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitido::class, 'dte_emitido_id');
    }

    /**
     * Registra la transicion BORRADOR -> FIRMADO en F4.4.
     *
     * @param SiiDteEmitido $dte         DTE recien firmado (folio ya asignado).
     * @param int           $folio       Folio CAF reservado para este DTE.
     * @param string        $hashSha256  Hash SHA256 del XML EnvioDTE firmado.
     */
    public static function registrarFirma(SiiDteEmitido $dte, int $folio, string $hashSha256): self
    {
        return self::create([
            'dte_emitido_id'  => $dte->id,
            'estado_anterior' => SiiDteEmitido::ESTADO_BORRADOR,
            'estado_nuevo'    => SiiDteEmitido::ESTADO_FIRMADO,
            'glosa'           => 'DTE firmado y persistido (F4.4)',
            'payload'         => [
                'folio'           => $folio,
                'tipo_dte'        => (int) $dte->tipo_dte,
                'caf_id'          => $dte->caf_id,
                'xml_hash_sha256' => $hashSha256,
                'xml_path'        => $dte->xml_path,
            ],
        ]);
    }

    /**
     * Registra el envio al SII (F5.2). Se invoca cuando DTEUpload acepta el
     * archivo y devuelve un track_id.
     *
     * @param array<string, mixed> $payloadExtra contexto adicional del envio
     *        (envio_id, ambiente, sesion_id, intentos_envio, etc.)
     */
    public static function registrarEnvio(SiiDteEmitido $dte, string $trackId, array $payloadExtra = []): self
    {
        return self::create([
            'dte_emitido_id'  => $dte->id,
            'estado_anterior' => SiiDteEmitido::ESTADO_FIRMADO,
            'estado_nuevo'    => SiiDteEmitido::ESTADO_ENVIADO_SII,
            'glosa'           => "Enviado al SII; track_id={$trackId}",
            'payload'         => array_merge([
                'track_id' => $trackId,
                'folio'    => (int) $dte->folio,
            ], $payloadExtra),
        ]);
    }

    /**
     * Registra aceptacion por el SII (uso futuro F5).
     */
    public static function registrarAceptacion(SiiDteEmitido $dte, ?string $glosa = null): self
    {
        return self::create([
            'dte_emitido_id'  => $dte->id,
            'estado_anterior' => SiiDteEmitido::ESTADO_ENVIADO_SII,
            'estado_nuevo'    => SiiDteEmitido::ESTADO_ACEPTADO,
            'glosa'           => $glosa ?? 'DTE aceptado por el SII',
            'payload'         => [
                'folio'    => (int) $dte->folio,
                'track_id' => $dte->track_id,
            ],
        ]);
    }

    /**
     * Registra rechazo por el SII (uso futuro F5).
     */
    public static function registrarRechazo(SiiDteEmitido $dte, ?string $glosa = null): self
    {
        return self::create([
            'dte_emitido_id'  => $dte->id,
            'estado_anterior' => SiiDteEmitido::ESTADO_ENVIADO_SII,
            'estado_nuevo'    => SiiDteEmitido::ESTADO_RECHAZADO,
            'glosa'           => $glosa ?? 'DTE rechazado por el SII',
            'payload'         => [
                'folio'                 => (int) $dte->folio,
                'track_id'              => $dte->track_id,
                'codigo_respuesta_sii'  => $dte->codigo_respuesta_sii,
            ],
        ]);
    }
}
