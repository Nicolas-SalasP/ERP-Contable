import React, { useEffect, useState } from 'react';
import siiApi from '../Servicios/siiApi';

const RAZON_MAX = 200;

/**
 * F6.4 — Modal de confirmacion para reintentar emision al SII.
 *
 * Props:
 *   - abierto         : boolean
 *   - facturaId       : number
 *   - resumenEstado   : { estado?: string, ultimo_envio_estado?: string }
 *                       Solo informativo, mostrar contexto al operador.
 *   - onCerrar        : () => void
 *   - onReintentoExitoso : (respuesta) => void   - Padre invoca recargar().
 *
 * Comportamiento:
 *   - Submit invoca siiApi.facturas.reintentar({ razon }).
 *   - 202: cierra modal + dispara onReintentoExitoso(respuesta).
 *   - 422: muestra mensaje inline (no cierra).
 *   - Otros errores: muestra mensaje inline generico.
 */
export function ModalReintentarSii({
    abierto,
    facturaId,
    resumenEstado = null,
    onCerrar,
    onReintentoExitoso,
}) {
    const [razon, setRazon] = useState('');
    const [enviando, setEnviando] = useState(false);
    const [errorInline, setErrorInline] = useState(null);

    useEffect(() => {
        if (abierto) {
            setRazon('');
            setEnviando(false);
            setErrorInline(null);
        }
    }, [abierto, facturaId]);

    if (!abierto) return null;

    const razonValida = razon.length <= RAZON_MAX;

    const handleConfirmar = async () => {
        if (!razonValida || enviando) return;
        setEnviando(true);
        setErrorInline(null);

        try {
            const respuesta = await siiApi.facturas.reintentar(
                facturaId,
                razon.trim() ? { razon: razon.trim() } : {}
            );
            onReintentoExitoso?.(respuesta);
            onCerrar?.();
        } catch (err) {
            // 422 del controller: { error: { razon, mensaje, estado_actual } }.
            const status = err?.status ?? err?.response?.status;
            const errorPayload = err?.raw?.error ?? err?.response?.data?.error ?? null;
            if (status === 422 && errorPayload?.mensaje) {
                setErrorInline(errorPayload.mensaje);
            } else if (status === 422 && err?.message) {
                // Validacion Laravel (razon > 200 chars).
                setErrorInline(err.message);
            } else {
                setErrorInline(err?.message ?? 'No se pudo encolar el reintento.');
            }
        } finally {
            setEnviando(false);
        }
    };

    return (
        <div
            className="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reintentar-sii-titulo"
            data-testid="modal-reintentar-sii"
        >
            <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div
                    className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
                    onClick={enviando ? undefined : onCerrar}
                />
                <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full animate-fade-in-up">
                    <div className="px-6 pt-6 pb-4">
                        <div className="flex items-start gap-3">
                            <div className="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                                <i className="fas fa-redo text-amber-600" />
                            </div>
                            <div className="flex-1">
                                <h3 id="reintentar-sii-titulo" className="text-lg font-bold text-slate-900">
                                    Reintentar emision al SII
                                </h3>
                                <p className="text-sm text-slate-600 mt-1">
                                    Se encolara una nueva accion para esta factura.
                                    El proceso es asincrono; el panel se actualizara cuando
                                    el job termine.
                                </p>
                                {resumenEstado && (
                                    <dl className="mt-3 text-xs space-y-1">
                                        {resumenEstado.estado && (
                                            <div className="flex justify-between gap-4">
                                                <dt className="text-slate-500">Estado DTE actual</dt>
                                                <dd
                                                    className="text-slate-800 font-mono"
                                                    data-testid="reintentar-sii-estado-actual"
                                                >
                                                    {resumenEstado.estado}
                                                </dd>
                                            </div>
                                        )}
                                        {resumenEstado.ultimo_envio_estado && (
                                            <div className="flex justify-between gap-4">
                                                <dt className="text-slate-500">Ultimo envio</dt>
                                                <dd
                                                    className="text-slate-800 font-mono"
                                                    data-testid="reintentar-sii-ultimo-envio"
                                                >
                                                    {resumenEstado.ultimo_envio_estado}
                                                </dd>
                                            </div>
                                        )}
                                    </dl>
                                )}
                            </div>
                        </div>

                        <div className="mt-4">
                            <label htmlFor="razon-reintento" className="block text-sm font-medium text-slate-700 mb-1">
                                Razon del reintento <span className="text-slate-400">(opcional)</span>
                            </label>
                            <textarea
                                id="razon-reintento"
                                data-testid="razon-reintento-textarea"
                                value={razon}
                                onChange={(e) => setRazon(e.target.value)}
                                disabled={enviando}
                                maxLength={RAZON_MAX}
                                rows={3}
                                placeholder="Ej: error transitorio de red, sesion SII expirada, etc."
                                className="w-full border border-slate-300 rounded-md p-2 text-sm focus:border-amber-500 focus:ring-amber-500"
                            />
                            <div className="flex justify-between text-xs mt-1">
                                <span className="text-slate-500">
                                    Queda en el log de auditoria.
                                </span>
                                <span data-testid="razon-reintento-contador" className="text-slate-500">
                                    {razon.length} / {RAZON_MAX}
                                </span>
                            </div>
                        </div>

                        {errorInline && (
                            <div
                                className="mt-3 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-800"
                                data-testid="reintentar-sii-error-inline"
                            >
                                {errorInline}
                            </div>
                        )}
                    </div>

                    <div className="bg-slate-50 px-6 py-3 sm:flex sm:flex-row-reverse gap-2">
                        <button
                            type="button"
                            data-testid="btn-confirmar-reintento"
                            onClick={handleConfirmar}
                            disabled={!razonValida || enviando}
                            className="w-full sm:w-auto inline-flex justify-center items-center gap-2 rounded-md px-4 py-2 text-sm font-bold text-white shadow-sm bg-amber-600 hover:bg-amber-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
                        >
                            {enviando ? (
                                <><i className="fas fa-spinner fa-spin" /> Encolando...</>
                            ) : (
                                <><i className="fas fa-redo" /> Confirmar reintento</>
                            )}
                        </button>
                        <button
                            type="button"
                            data-testid="btn-cancelar-reintento"
                            onClick={onCerrar}
                            disabled={enviando}
                            className="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 bg-white hover:bg-slate-100 disabled:opacity-50"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default ModalReintentarSii;
