import React, { useState } from 'react';
import useEstadoSii from '../Hooks/useEstadoSii';
import ModalReintentarSii from './ModalReintentarSii';

const ESTILO_POR_ESTADO = {
    BORRADOR:              'bg-gray-100 text-gray-800 border-gray-300',
    FOLIO_RESERVADO:       'bg-gray-100 text-gray-800 border-gray-300',
    XML_GENERADO:          'bg-blue-50 text-blue-700 border-blue-200',
    FIRMADO:               'bg-blue-100 text-blue-800 border-blue-300',
    ENVIADO_SII:           'bg-yellow-100 text-yellow-800 border-yellow-300',
    EN_PROCESO_SII:        'bg-yellow-100 text-yellow-800 border-yellow-300',
    ACEPTADO:              'bg-green-100 text-green-800 border-green-300',
    ACEPTADO_CON_REPAROS:  'bg-yellow-200 text-yellow-900 border-yellow-400',
    RECHAZADO:             'bg-red-100 text-red-800 border-red-300',
    REEMITIDO:             'bg-purple-100 text-purple-800 border-purple-300',
    ANULADO_CON_NC:        'bg-purple-200 text-purple-900 border-purple-400',
    ANULADO_FALLO_INTERNO: 'bg-red-200 text-red-900 border-red-400',
};

function formatoFecha(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        return d.toLocaleString('es-CL', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit',
        });
    } catch {
        return iso;
    }
}

function Fila({ etiqueta, valor, testid }) {
    return (
        <div className="flex justify-between gap-4 py-1 text-sm border-b border-slate-100 last:border-b-0">
            <span className="text-slate-500 font-medium">{etiqueta}</span>
            <span className="text-slate-800 font-mono text-right" data-testid={testid}>
                {valor ?? '—'}
            </span>
        </div>
    );
}

/**
 * F6.3 — Panel expandible con TODOS los detalles del estado SII de una factura.
 * F6.4 — Suma boton "Reintentar emision" + modal de confirmacion.
 *
 * @param {{ facturaId: number }} props
 */
export function EstadoSiiPanel({ facturaId }) {
    const { data, cargando, error, recargar } = useEstadoSii(facturaId);
    const [modalReintentarAbierto, setModalReintentarAbierto] = useState(false);

    const esElegibleReintento = (estadoData) => {
        if (!estadoData) return false;
        if (estadoData.tiene_dte === false) return true;
        if (estadoData.estado === 'BORRADOR') return true;
        if (estadoData.estado === 'FIRMADO') return true;
        if (estadoData.estado === 'ENVIADO_SII' && estadoData.ultimo_envio_estado_error === true) return true;
        return false;
    };

    if (cargando) {
        return (
            <div
                className="p-4 border border-slate-200 rounded bg-slate-50 text-slate-500 text-sm"
                data-testid="estado-sii-panel-cargando"
            >
                <div className="flex items-center gap-2">
                    <div className="w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
                    <span>Cargando estado SII...</span>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div
                className="p-4 border border-red-200 rounded bg-red-50 text-red-700 text-sm"
                data-testid="estado-sii-panel-error"
            >
                <div className="flex items-center justify-between">
                    <span>
                        Error al obtener el estado SII:{' '}
                        {error?.message ?? 'desconocido'}
                    </span>
                    <button
                        type="button"
                        onClick={recargar}
                        className="ml-3 px-2 py-1 text-xs bg-red-100 hover:bg-red-200 rounded"
                        data-testid="estado-sii-panel-reintentar"
                    >
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    if (!data?.tiene_dte) {
        return (
            <>
                <div
                    className="p-4 border border-slate-200 rounded bg-slate-50 text-slate-600 text-sm flex flex-wrap items-center justify-between gap-3"
                    data-testid="estado-sii-panel-sin-dte"
                >
                    <span>Esta factura aun no ha sido emitida al SII (sin DTE asociado).</span>
                    {esElegibleReintento(data) && (
                        <button
                            type="button"
                            onClick={() => setModalReintentarAbierto(true)}
                            className="px-3 py-1 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-md"
                            data-testid="estado-sii-panel-btn-reintentar"
                        >
                            <i className="fas fa-redo mr-1" /> Emitir / Reintentar
                        </button>
                    )}
                </div>
                <ModalReintentarSii
                    abierto={modalReintentarAbierto}
                    facturaId={facturaId}
                    resumenEstado={null}
                    onCerrar={() => setModalReintentarAbierto(false)}
                    onReintentoExitoso={() => recargar()}
                />
            </>
        );
    }

    const estilo = ESTILO_POR_ESTADO[data.estado] ?? 'bg-gray-100 text-gray-800 border-gray-300';

    return (
        <div
            className={`border rounded ${estilo}`}
            data-testid={`estado-sii-panel-${data.estado}`}
        >
            <header className="px-4 py-3 flex items-center justify-between border-b border-current/20">
                <div className="flex items-center gap-3">
                    <span className="font-bold uppercase tracking-wide text-sm">
                        {data.estado}
                    </span>
                    {data.es_pollable && (
                        <span
                            className="inline-flex items-center gap-1 text-xs opacity-75"
                            data-testid="estado-sii-panel-spinner-pollable"
                        >
                            <span className="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin" />
                            En seguimiento
                        </span>
                    )}
                    {data.es_terminal && (
                        <span className="text-xs opacity-75" data-testid="estado-sii-panel-terminal">
                            Estado final
                        </span>
                    )}
                </div>
                <button
                    type="button"
                    onClick={recargar}
                    className="text-xs px-2 py-1 bg-white/40 hover:bg-white/60 rounded"
                    data-testid="estado-sii-panel-refrescar"
                >
                    Refrescar
                </button>
            </header>

            <div className="px-4 py-3 bg-white/60 space-y-1">
                {data.estado_glosa_humana && (
                    <p
                        className="text-sm italic mb-2 text-slate-700"
                        data-testid="estado-sii-panel-glosa-humana"
                    >
                        {data.estado_glosa_humana}
                    </p>
                )}

                <Fila
                    etiqueta="Folio"
                    valor={data.folio ?? '—'}
                    testid="estado-sii-panel-folio"
                />
                <Fila
                    etiqueta="Tipo DTE"
                    valor={data.tipo_dte ?? '—'}
                    testid="estado-sii-panel-tipo-dte"
                />
                <Fila
                    etiqueta="Track ID"
                    valor={data.track_id ?? '—'}
                    testid="estado-sii-panel-track-id"
                />
                <Fila
                    etiqueta="Ambiente"
                    valor={data.ambiente ?? '—'}
                    testid="estado-sii-panel-ambiente"
                />
                <Fila
                    etiqueta="Fecha emision"
                    valor={data.fecha_emision ?? '—'}
                    testid="estado-sii-panel-fecha-emision"
                />
                <Fila
                    etiqueta="Fecha envio SII"
                    valor={formatoFecha(data.fecha_envio_sii)}
                    testid="estado-sii-panel-fecha-envio-sii"
                />

                {data.glosa_sii && (
                    <div
                        className="mt-3 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-800"
                        data-testid="estado-sii-panel-glosa-sii"
                    >
                        <span className="font-bold">Glosa SII: </span>
                        {data.glosa_sii}
                    </div>
                )}

                {data.ultimo_evento && (
                    <div
                        className="mt-3 p-2 bg-slate-50 border border-slate-200 rounded text-xs text-slate-700"
                        data-testid="estado-sii-panel-ultimo-evento"
                    >
                        <span className="font-bold">Ultimo evento: </span>
                        {data.ultimo_evento.estado_anterior ?? '—'}{' → '}
                        {data.ultimo_evento.estado_nuevo ?? '—'}
                        <span className="ml-2 opacity-70">
                            ({formatoFecha(data.ultimo_evento.fecha)})
                        </span>
                    </div>
                )}

                {esElegibleReintento(data) && (
                    <div className="mt-4 flex justify-end">
                        <button
                            type="button"
                            onClick={() => setModalReintentarAbierto(true)}
                            className="px-3 py-1 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-md inline-flex items-center gap-1"
                            data-testid="estado-sii-panel-btn-reintentar"
                        >
                            <i className="fas fa-redo" />
                            Reintentar emision
                        </button>
                    </div>
                )}
            </div>

            <ModalReintentarSii
                abierto={modalReintentarAbierto}
                facturaId={facturaId}
                resumenEstado={{
                    estado: data.estado,
                    ultimo_envio_estado: data.ultimo_envio_estado,
                }}
                onCerrar={() => setModalReintentarAbierto(false)}
                onReintentoExitoso={() => recargar()}
            />
        </div>
    );
}

export default EstadoSiiPanel;
