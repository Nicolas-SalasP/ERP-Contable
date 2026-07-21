import React, { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api';
import { FileMinus, X } from 'lucide-react';
import { formatearMoneda } from '../../../Utilidades/formato';

const formatCurrency = formatearMoneda;

const EMPTY = { numeroNc: '', montoNeto: '', montoIva: '', montoBruto: '', razon: '', fechaEmision: new Date().toISOString().split('T')[0], emitirDte: false };

const ModalNotaCredito = ({ isOpen, onClose, factura, onNcEmitida }) => {
    const [form, setForm] = useState(EMPTY);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen && factura) {
            setForm({
                ...EMPTY,
                montoNeto: String(factura.monto_neto ?? ''),
                montoIva: String(factura.monto_iva ?? ''),
                montoBruto: String(factura.monto_bruto ?? ''),
                fechaEmision: new Date().toISOString().split('T')[0],
            });
        }
    }, [isOpen, factura]);

    if (!isOpen || !factura) return null;

    const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }));

    const recalcBruto = () => {
        const neto = parseFloat(form.montoNeto) || 0;
        const iva  = parseFloat(form.montoIva) || 0;
        setForm(f => ({ ...f, montoBruto: String(Math.round(neto + iva)) }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.numeroNc.trim()) return Swal.fire('Campo requerido', 'Ingrese el número de NC.', 'warning');
        if (!form.razon.trim() || form.razon.trim().length < 5) return Swal.fire('Campo requerido', 'La razón debe tener al menos 5 caracteres.', 'warning');
        if ((parseFloat(form.montoNeto) || 0) <= 0) return Swal.fire('Monto inválido', 'El monto neto debe ser mayor a 0.', 'warning');
        if ((parseFloat(form.montoBruto) || 0) <= 0) return Swal.fire('Monto inválido', 'El total de la NC debe ser mayor a 0.', 'warning');

        setLoading(true);
        try {
            const res = await api.post(`/facturas/${factura.id}/nota-credito`, {
                numero_nc:     form.numeroNc,
                monto_neto:    parseFloat(form.montoNeto) || 0,
                monto_iva:     parseFloat(form.montoIva) || 0,
                monto_bruto:   parseFloat(form.montoBruto) || 0,
                razon:         form.razon,
                fecha_emision: form.fechaEmision,
                emitir_dte:    form.emitirDte,
            });
            if (res.success || res.data) {
                Swal.fire({ icon: 'success', title: 'Nota de Crédito emitida', text: `NC N° ${form.numeroNc} registrada correctamente.`, timer: 2500, showConfirmButton: false });
                onNcEmitida?.();
                onClose();
            } else {
                Swal.fire('Error', res.message || 'No se pudo emitir la Nota de Crédito.', 'error');
            }
        } catch (err) {
            const msg = err?.response?.data?.message || err?.response?.data?.error || 'Error al emitir la Nota de Crédito.';
            Swal.fire('Error', msg, 'error');
        } finally {
            setLoading(false);
        }
    };

    const montoOrig = parseFloat(factura.monto_bruto) || 0;

    return (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 dark:border-slate-700">
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-indigo-100 dark:bg-indigo-900/40 rounded-lg">
                            <FileMinus size={18} className="text-indigo-600 dark:text-indigo-400" strokeWidth={2} />
                        </div>
                        <div>
                            <h2 className="font-bold text-slate-800 dark:text-slate-100 text-base">Emitir Nota de Crédito</h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Factura N° {factura.numero_factura} — {formatCurrency(montoOrig)}</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <X size={18} className="text-slate-400" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="px-6 py-5 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="nc-numero" className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">N° Nota de Crédito <span className="text-red-500">*</span></label>
                            <input
                                id="nc-numero"
                                type="text"
                                value={form.numeroNc}
                                onChange={set('numeroNc')}
                                placeholder="NC-00001"
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                                required
                            />
                        </div>
                        <div>
                            <label htmlFor="nc-fecha-emision" className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Fecha Emisión</label>
                            <input
                                id="nc-fecha-emision"
                                type="date"
                                value={form.fechaEmision}
                                onChange={set('fechaEmision')}
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label htmlFor="nc-monto-neto" className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Monto Neto <span className="text-red-500">*</span></label>
                            <input
                                id="nc-monto-neto"
                                type="number"
                                min="1"
                                step="1"
                                required
                                value={form.montoNeto}
                                onChange={set('montoNeto')}
                                onBlur={recalcBruto}
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                            />
                        </div>
                        <div>
                            <label htmlFor="nc-monto-iva" className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">IVA</label>
                            <input
                                id="nc-monto-iva"
                                type="number"
                                min="0"
                                step="1"
                                value={form.montoIva}
                                onChange={set('montoIva')}
                                onBlur={recalcBruto}
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                            />
                        </div>
                        <div>
                            <label htmlFor="nc-monto-bruto" className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Total NC <span className="text-red-500">*</span></label>
                            <input
                                id="nc-monto-bruto"
                                type="number"
                                min="1"
                                max={montoOrig}
                                step="1"
                                value={form.montoBruto}
                                onChange={set('montoBruto')}
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none font-bold"
                                required
                            />
                            <p className="text-[10px] text-slate-400 mt-1">Máx: {formatCurrency(montoOrig)}</p>
                        </div>
                    </div>

                    <div>
                        <label htmlFor="nc-razon" className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Razón de la NC <span className="text-red-500">*</span> <span className="text-slate-400 font-normal">(min. 5 caracteres)</span></label>
                        <textarea
                            id="nc-razon"
                            value={form.razon}
                            onChange={set('razon')}
                            rows={2}
                            placeholder="Ej: Anulación por error en precio pactado"
                            className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-none"
                            required
                            minLength={5}
                        />
                    </div>

                    <label className="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" checked={form.emitirDte} onChange={set('emitirDte')} aria-label="Emitir DTE al SII (tipo 61)" className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
                        <div>
                            <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">Emitir DTE al SII (tipo 61)</span>
                            <p className="text-[11px] text-slate-400">Solo si la factura original ya tiene folio electrónico SII.</p>
                        </div>
                    </label>

                    <div className="flex gap-3 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 px-4 py-2.5 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 rounded-xl text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={loading}
                            className="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 disabled:opacity-50 transition-colors flex items-center justify-center gap-2"
                        >
                            {loading ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <FileMinus size={16} />}
                            {loading ? 'Procesando…' : 'Emitir Nota de Crédito'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default ModalNotaCredito;
