<?php

namespace App\Domains\Sii\Http\Controllers;

use App\Domains\Sii\Http\Requests\ReintentarRequest;
use App\Domains\Sii\Jobs\ReintentarEmisionDteJob;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Http\JsonResponse;

/** Endpoint de reintento directo sobre un SiiDteEmitido; multi-tenant seguro via where('empresa_id') + findOrFail (IDOR → 404). */
class DteRetryController
{
    /** Estados que admiten reintento manual directo; RECHAZADO y ANULADO_FALLO_INTERNO quedan fuera porque el folio CAF ya fue consumido o liberado como HUERFANO y EmitirDteService::emitir() exige estado===BORRADOR (recuperarlos requiere emitir un DTE nuevo, mismo criterio que ReintentarEmisionFacturaService::ESTADOS_TERMINALES_DTE). */
    private const ESTADOS_REINTENTABLES = [
        SiiDteEmitido::ESTADO_BORRADOR,
        SiiDteEmitido::ESTADO_FOLIO_RESERVADO,
        SiiDteEmitido::ESTADO_XML_GENERADO,
        SiiDteEmitido::ESTADO_FIRMADO,
    ];

    /** Estados donde solo hace falta reenviar (ya tienen firma valida). */
    private const ESTADOS_SOLO_REENVIO = [
        SiiDteEmitido::ESTADO_FIRMADO,
    ];

    /** POST /api/sii/dte/{siiDteEmitido}/reintentar — encola la accion correcta segun el estado del DTE y retorna 202, sin ejecutar nada sincronamente. */
    public function reintentar(ReintentarRequest $request, int $siiDteEmitido): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_activa_id;

        /** @var SiiDteEmitido $dte */
        $dte = SiiDteEmitido::query()
            ->where('empresa_id', $empresaId)
            ->findOrFail($siiDteEmitido);

        if (!in_array($dte->estado, self::ESTADOS_REINTENTABLES, true)) {
            $mensaje = in_array($dte->estado, [SiiDteEmitido::ESTADO_RECHAZADO, SiiDteEmitido::ESTADO_ANULADO_FALLO_INTERNO], true)
                ? "El DTE en estado '{$dte->estado}' no puede reanudarse: el folio ya fue consumido o liberado. Debe emitir un documento nuevo."
                : "El DTE en estado '{$dte->estado}' no admite reintento manual.";

            return response()->json([
                'error' => [
                    'razon'         => 'estado_no_reintentable',
                    'mensaje'       => $mensaje,
                    'estado_actual' => $dte->estado,
                ],
            ], 422);
        }

        $accion = in_array($dte->estado, self::ESTADOS_SOLO_REENVIO, true)
            ? ReintentarEmisionDteJob::ACCION_REANUDAR_ENVIO
            : ReintentarEmisionDteJob::ACCION_REANUDAR_FIRMA;

        ReintentarEmisionDteJob::dispatch(
            $dte->id,
            $accion,
            $request->input('razon'),
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'mensaje' => 'Reintento programado',
            'data'    => [
                'dte_id'          => $dte->id,
                'accion_encolada' => $accion,
                'estado_anterior' => $dte->estado,
            ],
        ], 202);
    }
}
