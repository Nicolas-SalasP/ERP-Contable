<?php

namespace App\Domains\Sii\Http\Controllers;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Sii\Exceptions\FacturaNoEmisibleException;
use App\Domains\Sii\Exceptions\ReintentoNoAplicableException;
use App\Domains\Sii\Http\Requests\ReintentarRequest;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Integracion\ReintentarEmisionFacturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F6.3 — Endpoints REST de SOLO LECTURA del estado SII de facturas.
 *
 * Bajo /api/sii/facturas/*. Cero modificacion al FacturaController del
 * Comercial. Multi-tenant safe via where('empresa_id', $userEmpresaId) +
 * findOrFail (IDOR retorna 404). Throttle aplicado por la ruta.
 */
class FacturaSiiController
{
    /** Estados donde el flujo SII ya esta resuelto (no toca polleo). */
    private const ESTADOS_TERMINALES = [
        SiiDteEmitido::ESTADO_ACEPTADO,
        SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS,
        SiiDteEmitido::ESTADO_RECHAZADO,
        SiiDteEmitido::ESTADO_ANULADO_CON_NC,
        SiiDteEmitido::ESTADO_ANULADO_FALLO_INTERNO,
    ];

    /** Estados donde el polling automatico de F5.3 aun puede transicionar. */
    private const ESTADOS_POLLABLES = [
        SiiDteEmitido::ESTADO_BORRADOR,
        SiiDteEmitido::ESTADO_FOLIO_RESERVADO,
        SiiDteEmitido::ESTADO_XML_GENERADO,
        SiiDteEmitido::ESTADO_FIRMADO,
        SiiDteEmitido::ESTADO_ENVIADO_SII,
        SiiDteEmitido::ESTADO_EN_PROCESO_SII,
    ];

    /**
     * F6.4 — Estados del ultimo envio que el frontend interpreta como
     * "error reintentable" para mostrar el boton condicional.
     */
    private const ESTADOS_ENVIO_ERROR_REINTENTABLES = [
        SiiEnvioDte::ESTADO_ERROR_TRANSPORTE,
        SiiEnvioDte::ESTADO_ERROR_TIMEOUT,
        SiiEnvioDte::ESTADO_ERROR_PERMANENTE,
    ];

    /** Glosas humanas en espanol para mostrar al operador. */
    private const GLOSAS_ESTADO = [
        SiiDteEmitido::ESTADO_BORRADOR              => 'Borrador, pendiente de emision',
        SiiDteEmitido::ESTADO_FOLIO_RESERVADO       => 'Folio reservado, pendiente firma',
        SiiDteEmitido::ESTADO_XML_GENERADO          => 'XML generado, pendiente firma',
        SiiDteEmitido::ESTADO_FIRMADO               => 'Firmado, pendiente envio al SII',
        SiiDteEmitido::ESTADO_ENVIADO_SII           => 'Enviado al SII, esperando respuesta',
        SiiDteEmitido::ESTADO_EN_PROCESO_SII        => 'En proceso de validacion en el SII',
        SiiDteEmitido::ESTADO_ACEPTADO              => 'Aceptado por el SII',
        SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS  => 'Aceptado con reparos por el SII',
        SiiDteEmitido::ESTADO_RECHAZADO             => 'Rechazado por el SII',
        SiiDteEmitido::ESTADO_REEMITIDO             => 'Reemitido (DTE reemplazado)',
        SiiDteEmitido::ESTADO_ANULADO_CON_NC        => 'Anulado mediante nota de credito',
        SiiDteEmitido::ESTADO_ANULADO_FALLO_INTERNO => 'Anulado por fallo interno',
    ];

    /**
     * GET /api/sii/facturas
     */
    public function index(Request $request): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;
        $perPage   = (int) $request->integer('por_pagina', 25);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 25;
        }

        $facturas = Factura::query()
            ->where('empresa_id', $empresaId)
            ->with(['cliente', 'dteEmitido'])
            ->orderBy('fecha_emision', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $facturas->getCollection()->map(fn (Factura $f) => $this->formatoLiviano($f))->all(),
            'paginacion' => [
                'total'         => $facturas->total(),
                'por_pagina'    => $facturas->perPage(),
                'pagina_actual' => $facturas->currentPage(),
                'ultima_pagina' => $facturas->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/sii/facturas/{facturaId}/estado
     * Payload liviano para polling.
     */
    public function estado(Request $request, int $facturaId): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;

        $factura = Factura::query()
            ->where('empresa_id', $empresaId)
            ->with(['dteEmitido' => function ($q) {
                $q->with(['eventos' => function ($qq) {
                    $qq->orderByDesc('created_at')->limit(1);
                }]);
                // F6.4: necesario para derivar ultimo_envio_estado_error.
                $q->with(['envios' => function ($qq) {
                    $qq->orderByDesc('created_at');
                }]);
            }])
            ->findOrFail($facturaId);

        return response()->json(['data' => $this->formatoEstado($factura)]);
    }

    /**
     * POST /api/sii/facturas/{facturaId}/reintentar  (F6.4)
     *
     * Encola la accion correcta segun el estado actual y retorna 202 con la
     * accion programada. NO ejecuta nada sincronamente. La UI debe refrescar
     * el estado tras este endpoint.
     */
    public function reintentar(
        ReintentarRequest $request,
        int $facturaId,
        ReintentarEmisionFacturaService $service
    ): JsonResponse {
        $empresaId = (int) $request->user()->empresa_id;
        $factura   = Factura::query()
            ->where('empresa_id', $empresaId)
            ->findOrFail($facturaId);

        try {
            $accion = $service->reintentar(
                $factura,
                $request->input('razon'),
                $request->user()->id
            );

            return response()->json([
                'data' => [
                    'factura_id'      => $factura->id,
                    'accion_encolada' => $accion,
                    'mensaje'         => 'Reintento encolado, monitoreando estado...',
                ],
            ], 202);
        } catch (ReintentoNoAplicableException $e) {
            return response()->json([
                'error' => [
                    'razon'         => $e->razon,
                    'mensaje'       => $e->getMessage(),
                    'estado_actual' => $e->estadoActual,
                ],
            ], 422);
        } catch (FacturaNoEmisibleException $e) {
            return response()->json([
                'error' => [
                    'razon'         => $e->razon,
                    'mensaje'       => $e->getMessage(),
                    'estado_actual' => null,
                ],
            ], 422);
        }
    }

    /**
     * GET /api/sii/facturas/{facturaId}
     */
    public function mostrar(Request $request, int $facturaId): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;

        $factura = Factura::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'cliente',
                'detalles',
                'dteEmitido.eventos',
                'dteEmitido.envios.eventos',
                'dteEmitido.detalles',
                'dteEmitido.referencias',
            ])
            ->findOrFail($facturaId);

        return response()->json(['data' => $this->formatoCompleto($factura)]);
    }

    // ----------------- helpers de formato -----------------

    private function formatoLiviano(Factura $f): array
    {
        $dte = $f->dteEmitido;
        return [
            'factura_id'    => $f->id,
            'numero_factura' => $f->numero_factura,
            'tipo_documento' => $f->tipo_documento,
            'fecha_emision' => $f->fecha_emision?->toDateString(),
            'monto_bruto'   => (float) $f->monto_bruto,
            'cliente' => $f->cliente ? [
                'id'           => $f->cliente->id,
                'rut'          => $f->cliente->rut,
                'razon_social' => $f->cliente->razon_social,
            ] : null,
            'estado_sii' => $dte ? [
                'tiene_dte'           => true,
                'dte_id'              => (int) $dte->id,
                'estado'              => $dte->estado,
                'estado_glosa_humana' => $this->glosaHumana($dte->estado),
                'es_terminal'         => $this->esTerminal($dte->estado),
                'es_pollable'         => $this->esPollable($dte->estado),
                'folio'               => $dte->folio,
                'track_id'            => $dte->track_id,
            ] : [
                'tiene_dte' => false,
            ],
        ];
    }

    private function formatoEstado(Factura $f): array
    {
        $dte = $f->dteEmitido;

        if ($dte === null) {
            return [
                'factura_id'                => $f->id,
                'tiene_dte'                 => false,
                'dte_id'                    => null,
                'estado'                    => null,
                'estado_glosa_humana'       => null,
                'es_terminal'               => false,
                'es_pollable'               => false,
                'tipo_dte'                  => null,
                'folio'                     => null,
                'track_id'                  => null,
                'fecha_emision'             => $f->fecha_emision?->toDateString(),
                'fecha_envio_sii'           => null,
                'ambiente'                  => null,
                'glosa_sii'                 => null,
                'ultimo_evento'             => null,
                'ultimo_envio_estado'       => null,
                'ultimo_envio_estado_error' => false,
            ];
        }

        $ultimoEvento = $dte->eventos->first();
        // F6.4: envios viene cargado desc por created_at (ver controller::estado).
        $ultimoEnvio  = $dte->relationLoaded('envios') ? $dte->envios->first() : null;
        $ultimoEnvioEstado = $ultimoEnvio?->estado_envio;

        return [
            'factura_id'                => $f->id,
            'tiene_dte'                 => true,
            'dte_id'                    => (int) $dte->id,
            'estado'                    => $dte->estado,
            'estado_glosa_humana'       => $this->glosaHumana($dte->estado),
            'es_terminal'               => $this->esTerminal($dte->estado),
            'es_pollable'               => $this->esPollable($dte->estado),
            'tipo_dte'                  => $dte->tipo_dte !== null ? (int) $dte->tipo_dte : null,
            'folio'                     => $dte->folio,
            'track_id'                  => $dte->track_id,
            'fecha_emision'             => $dte->fecha_emision?->toDateString(),
            'fecha_envio_sii'           => $dte->fecha_envio_sii?->toIso8601String(),
            'ambiente'                  => $f->empresa?->ambiente_sii,
            'glosa_sii'                 => $dte->glosa_sii,
            'ultimo_evento'             => $ultimoEvento ? [
                'estado_anterior' => $ultimoEvento->estado_anterior,
                'estado_nuevo'    => $ultimoEvento->estado_nuevo,
                'fecha'           => $ultimoEvento->created_at?->toIso8601String(),
            ] : null,
            'ultimo_envio_estado'       => $ultimoEnvioEstado,
            'ultimo_envio_estado_error' => $ultimoEnvioEstado !== null
                && in_array($ultimoEnvioEstado, self::ESTADOS_ENVIO_ERROR_REINTENTABLES, true),
        ];
    }

    private function formatoCompleto(Factura $f): array
    {
        $base = $this->formatoLiviano($f);
        $dte  = $f->dteEmitido;

        $base['cliente_completo'] = $f->cliente ? [
            'id'             => $f->cliente->id,
            'rut'            => $f->cliente->rut,
            'razon_social'   => $f->cliente->razon_social,
            'giro'           => $f->cliente->giro,
            'direccion'      => $f->cliente->direccion,
            'comuna'         => $f->cliente->comuna,
            'ciudad'         => $f->cliente->ciudad,
            'contacto_email' => $f->cliente->contacto_email,
            'email'          => $f->cliente->email,
        ] : null;

        $base['detalles_factura'] = $f->detalles->map(fn ($d) => [
            'numero_linea'    => (int) $d->numero_linea,
            'nombre_item'     => $d->nombre_item,
            'cantidad'        => (float) $d->cantidad,
            'precio_unitario' => (float) $d->precio_unitario,
            'monto_item'      => (float) $d->monto_item,
            'exento'          => (bool) $d->exento,
        ])->all();

        if ($dte === null) {
            $base['dte'] = null;
            return $base;
        }

        $base['dte'] = [
            'id'              => (int) $dte->id,
            'estado'          => $dte->estado,
            'folio'           => $dte->folio,
            'tipo_dte'        => (int) $dte->tipo_dte,
            'monto_total'     => (float) $dte->monto_total,
            'xml_path'        => $dte->xml_path,
            'xml_hash_sha256' => $dte->xml_hash_sha256,
            'fecha_firma'     => $dte->fecha_firma?->toIso8601String(),
            'fecha_envio_sii' => $dte->fecha_envio_sii?->toIso8601String(),
            'track_id'        => $dte->track_id,
            'detalles'        => $dte->detalles->map(fn ($d) => [
                'numero_linea'    => (int) $d->numero_linea,
                'nombre_item'     => $d->nombre_item,
                'monto_item'      => (float) $d->monto_item,
            ])->all(),
            'referencias'     => $dte->referencias->map(fn ($r) => [
                'tipo_doc'  => $r->tipo_documento_referencia,
                'folio_ref' => $r->folio_referencia,
                'fecha_ref' => $r->fecha_referencia?->toDateString(),
                'razon'     => $r->razon_referencia,
            ])->all(),
            'eventos'         => $dte->eventos->map(fn ($e) => [
                'estado_anterior' => $e->estado_anterior,
                'estado_nuevo'    => $e->estado_nuevo,
                'glosa'           => $e->glosa,
                'fecha'           => $e->created_at?->toIso8601String(),
            ])->all(),
            'envios'          => $dte->envios->map(fn ($env) => [
                'id'              => (int) $env->id,
                'estado_envio'    => $env->estado_envio,
                'track_id'        => $env->track_id,
                'http_status'     => $env->http_status_ultimo_envio,
                'fecha_envio'     => $env->fecha_envio?->toIso8601String(),
                'fecha_resolucion' => $env->fecha_resolucion?->toIso8601String(),
                'eventos'         => $env->eventos->map(fn ($evEnv) => [
                    'estado_anterior' => $evEnv->estado_anterior,
                    'estado_nuevo'    => $evEnv->estado_nuevo,
                    'codigo_sii'      => $evEnv->codigo_sii_raw,
                    'fecha'           => $evEnv->created_at?->toIso8601String(),
                ])->all(),
            ])->all(),
        ];

        return $base;
    }

    private function esTerminal(?string $estado): bool
    {
        return $estado !== null && in_array($estado, self::ESTADOS_TERMINALES, true);
    }

    private function esPollable(?string $estado): bool
    {
        return $estado !== null && in_array($estado, self::ESTADOS_POLLABLES, true);
    }

    private function glosaHumana(?string $estado): ?string
    {
        return self::GLOSAS_ESTADO[$estado] ?? null;
    }
}
