import React, { useEffect, useState } from 'react';

const MOTIVO_MIN = 5;
const MOTIVO_MAX = 200;

const ModalRevocarCaf = ({ abierto, caf, onCerrar, onConfirmar, revocando = false }) => {
    const [motivo, setMotivo] = useState('');

    useEffect(() => {
        if (abierto) setMotivo('');
    }, [abierto, caf?.id]);

    if (!abierto || !caf) return null;

    const motivoValido = motivo.trim().length >= MOTIVO_MIN && motivo.length <= MOTIVO_MAX;

    const handleConfirmar = () => {
        if (!motivoValido || revocando) return;
        onConfirmar(motivo);
    };

    return (
        <div
            className="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="revocar-caf-titulo"
            data-testid="modal-revocar"
        >
            <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div
                    className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
                    onClick={revocando ? undefined : onCerrar}
                />
                <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full animate-fade-in-up">
                    <div className="px-6 pt-6 pb-4">
                        <div className="flex items-start gap-3">
                            <div className="flex-shrink-0 w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center">
                                <i className="fas fa-exclamation-triangle text-rose-600" />
                            </div>
                            <div className="flex-1">
                                <h3 id="revocar-caf-titulo" className="text-lg font-bold text-slate-900">
                                    Revocar CAF #{caf.id}
                                </h3>
                                <dl className="mt-3 text-sm space-y-1">
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-slate-500">Tipo DTE</dt>
                                        <dd className="text-slate-800 font-mono">{caf.tipo_dte}</dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-slate-500">Rango de folios</dt>
                                        <dd className="text-slate-800 font-mono">{caf.folio_desde} - {caf.folio_hasta}</dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-slate-500">Folios ya usados</dt>
                                        <dd className="text-slate-800 font-mono">{caf.folios_usados}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div className="mt-4 bg-rose-50 border border-rose-200 rounded-lg p-3 text-xs text-rose-800">
                            <i className="fas fa-info-circle mr-1" />
                            <strong>Esta accion es irreversible.</strong> Los folios reservados sin emitir
                            se marcaran como huerfanos y no podran reusarse. Los folios ya emitidos
                            permanecen consultables.
                        </div>

                        <div className="mt-4">
                            <label htmlFor="motivo-revocacion" className="block text-sm font-medium text-slate-700 mb-1">
                                Motivo de la revocacion <span className="text-rose-500">*</span>
                            </label>
                            <textarea
                                id="motivo-revocacion"
                                data-testid="motivo-textarea"
                                value={motivo}
                                onChange={(e) => setMotivo(e.target.value)}
                                disabled={revocando}
                                maxLength={MOTIVO_MAX}
                                rows={3}
                                placeholder="Ej: Cambio de razon social, CAF descontinuado, etc."
                                className="w-full border border-slate-300 rounded-md p-2 text-sm focus:border-rose-500 focus:ring-rose-500"
                            />
                            <div className="flex justify-between text-xs mt-1">
                                <span className={motivo.trim().length < MOTIVO_MIN ? 'text-rose-600' : 'text-slate-500'}>
                                    Minimo {MOTIVO_MIN} caracteres.
                                </span>
                                <span data-testid="motivo-contador" className="text-slate-500">
                                    {motivo.length} / {MOTIVO_MAX}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="bg-slate-50 px-6 py-3 sm:flex sm:flex-row-reverse gap-2">
                        <button
                            type="button"
                            data-testid="btn-confirmar-revocar"
                            onClick={handleConfirmar}
                            disabled={!motivoValido || revocando}
                            className="w-full sm:w-auto inline-flex justify-center items-center gap-2 rounded-md px-4 py-2 text-sm font-bold text-white shadow-sm bg-rose-600 hover:bg-rose-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
                        >
                            {revocando ? (<><i className="fas fa-spinner fa-spin" /> Revocando...</>) : (<><i className="fas fa-ban" /> Revocar CAF</>)}
                        </button>
                        <button
                            type="button"
                            data-testid="btn-cancelar-revocar"
                            onClick={onCerrar}
                            disabled={revocando}
                            className="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 bg-white hover:bg-slate-100 disabled:opacity-50"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ModalRevocarCaf;
