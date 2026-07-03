import React, { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api';
import { FilePlus, X } from 'lucide-react';
import { formatearMoneda } from '../../../Utilidades/formato';

const formatCurrency = formatearMoneda;

const EMPTY = { numeroNd: '', montoNeto: '', montoIva: '', montoBruto: '', razon: '', fechaEmision: new Date().toISOString().split('T')[0], emitirDte: false };

const ModalNotaDebito = ({ isOpen, onClose, factura, onNdEmitida }) => {
    const [form, setForm] = useState(EMPTY);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen && factura) {
            setForm({ ...EMPTY, fechaEmision: new Date().toISOString().split('T')[0] });
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
        if (!form.numeroNd.trim()) return Swal.fire('Campo requerido', 'Ingrese el número de ND.', 'warning');
        if (!form.razon.trim() || form.razon.trim().length < 5) return Swal.fire('Campo requerido', 'La razón debe tener al menos 5 caracteres.', 'warning');
        const bruto = parseFloat(form.montoBruto) || 0;
        if (bruto <= 0) return Swal.fire('Campo requerido', 'El monto total debe ser mayor a 0.', 'warning');

        setLoading(true);
        try {
            const res = await api.post(`/facturas/${factura.id}/nota-debito`, {
                numero_nd:     form.numeroNd,
                monto_neto:    parseFloat(form.montoNeto) || 0,
                monto_iva:     parseFloat(form.montoIva) || 0,
                monto_bruto:   bruto,
                razon:         form.razon,
                fecha_emision: form.fechaEmision,
                emitir_dte:    form.emitirDte,
            });
            if (res.success || res.data) {
                Swal.fire({ icon: 'success', title: 'Nota de Débito emitida', text: `ND N° ${form.numeroNd} registrada correctamente.`, timer: 2500, showConfirmButton: false });
                onNdEmitida?.();
                onClose();
            } else {
                Swal.fire('Error', res.message || 'No se pudo emitir la Nota de Débito.', 'error');
            }
        } catch (err) {
            const msg = err?.response?.data?.message || err?.response?.data?.error || 'Error al emitir la Nota de Débito.';
            Swal.fire('Error', msg, 'error');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 dark:border-slate-700">
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-amber-100 dark:bg-amber-900/40 rounded-lg">
                            <FilePlus size={18} className="text-amber-600 dark:text-amber-400" strokeWidth={2} />
                        </div>
                        <div>
                            <h2 className="font-bold text-slate-800 dark:text-slate-100 text-base">Emitir Nota de Débito</h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Factura N° {factura.numero_factura} — {formatCurrency(factura.monto_bruto)}</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <X size={18} className="text-slate-400" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="px-6 py-5 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">N° Nota de Débito <span className="text-red-500">*</span></label>
                            <input
                                type="text"
                                value={form.numeroNd}
                                onChange={set('numeroNd')}
                                placeholder="ND-00001"
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Fecha Emisión</label>
                            <input
                                type="date"
                                value={form.fechaEmision}
                                onChange={set('fechaEmision')}
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Monto Neto</label>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                value={form.montoNeto}
                                onChange={set('montoNeto')}
                                onBlur={recalcBruto}
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">IVA</label>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                value={form.montoIva}
                                onChange={set('montoIva')}
                                onBlur={recalcBruto}
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Total ND <span className="text-red-500">*</span></label>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                value={form.montoBruto}
                                onChange={set('montoBruto')}
                                className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none font-bold"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Razón de la ND <span className="text-red-500">*</span> <span className="text-slate-400 font-normal">(min. 5 caracteres)</span></label>
                        <textarea
                            value={form.razon}
                            onChange={set('razon')}
                            rows={2}
                            placeholder="Ej: Cobro de intereses por mora"
                            className="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none resize-none"
                            required
                            minLength={5}
                        />
                    </div>

                    <label className="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" checked={form.emitirDte} onChange={set('emitirDte')} className="rounded border-slate-300 text-amber-600 focus:ring-amber-500 w-4 h-4" />
                        <div>
                            <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">Emitir DTE al SII (tipo 56)</span>
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
                            className="flex-1 px-4 py-2.5 bg-amber-600 text-white rounded-xl text-sm font-bold hover:bg-amber-700 disabled:opacity-50 transition-colors flex items-center justify-center gap-2"
                        >
                            {loading ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <FilePlus size={16} />}
                            {loading ? 'Procesando…' : 'Emitir Nota de Débito'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default ModalNotaDebito;
