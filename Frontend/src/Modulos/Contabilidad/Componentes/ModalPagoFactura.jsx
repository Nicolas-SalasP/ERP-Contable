import React, { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api';
import BotonAccion from '../../../Componentes/BotonAccion';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const ModalPagoFactura = ({ isOpen, onClose, factura, onPagoExitoso }) => {
    const [loading, setLoading] = useState(false);
    const [formPago, setFormPago] = useState({
        fechaPago: new Date().toISOString().split('T')[0],
        medioPago: 'TRANSFERENCIA',
        numeroOperacion: '',
        cuentaOrigen: '1'
    });

    useEffect(() => {
        if (isOpen && factura) {
            setFormPago({
                fechaPago: new Date().toISOString().split('T')[0],
                medioPago: 'TRANSFERENCIA',
                numeroOperacion: '',
                cuentaOrigen: '1'
            });
        }
    }, [isOpen, factura]);

    if (!isOpen || !factura) return null;

    const handleSubmit = async (e) => {
        e.preventDefault();

        setLoading(true);
        try {
            const res = await api.post(`/facturas/${factura.id}/pagar`, formPago);

            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Pago Contabilizado!',
                    html: `La factura <b>#${factura.numero_factura}</b> ha sido marcada como pagada.<br/><span class="text-sm text-slate-500">Fecha de egreso: ${new Date(formPago.fechaPago).toLocaleDateString('es-CL')}</span>`,
                    customClass: { confirmButton: 'bg-emerald-600 text-white font-bold py-2.5 px-6 rounded-lg shadow-md' },
                    buttonsStyling: false,
                    timer: 3000
                });
                onPagoExitoso();
                onClose();
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error al procesar el egreso',
                text: error.response?.data?.message || 'Hubo un problema de conexión con el servidor.',
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2.5 px-6 rounded-lg' },
                buttonsStyling: false
            });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-[100] p-4 animate-fade-in">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[90vh]">
                <div className="bg-slate-900 px-6 py-5 flex justify-between items-center text-white shrink-0">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl">
                            <i className="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <h2 className="text-xl font-black tracking-tight">Ejecutar Pago de Factura</h2>
                            <p className="text-xs text-slate-400 font-medium uppercase tracking-widest mt-0.5">Comprobante de Egreso</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="text-slate-400 hover:text-rose-400 transition-colors bg-slate-800 hover:bg-slate-700 w-8 h-8 rounded-full flex items-center justify-center">
                        <i className="fas fa-times"></i>
                    </button>
                </div>

                <div className="overflow-y-auto custom-scrollbar">
                    <form id="form-pago-factura" onSubmit={handleSubmit} className="p-6 md:p-8 space-y-8">
                        <section>
                            <h3 className="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <i className="fas fa-file-invoice"></i> Resumen de la Deuda
                            </h3>
                            <div className="bg-slate-50 border border-slate-200 rounded-xl p-5 flex flex-col md:flex-row justify-between items-center gap-4">
                                <div className="w-full md:w-auto">
                                    <p className="text-[10px] font-bold text-slate-400 uppercase">Proveedor</p>
                                    <p className="font-bold text-slate-800 text-lg truncate max-w-[250px]">{factura.nombre_proveedor}</p>
                                    <p className="text-sm font-mono text-slate-500">Factura N° {factura.numero_factura}</p>
                                </div>
                                <div className="w-full md:w-auto text-left md:text-right border-t md:border-t-0 md:border-l border-slate-200 pt-3 md:pt-0 md:pl-6">
                                    <p className="text-[10px] font-bold text-emerald-600 uppercase">Monto Total a Pagar</p>
                                    <p className="text-3xl font-black text-emerald-600 tracking-tight">{formatCurrency(factura.monto_bruto)}</p>
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 className="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <i className="fas fa-sliders-h"></i> Parámetros de Egreso
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">

                                <div className="md:col-span-2">
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-2">Medio de Pago</label>
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        {['TRANSFERENCIA', 'CHEQUE', 'EFECTIVO', 'TARJETA'].map((medio) => (
                                            <label
                                                key={medio}
                                                className={`cursor-pointer border rounded-xl p-3 text-center transition-all ${formPago.medioPago === medio ? 'bg-indigo-50 border-indigo-500 ring-1 ring-indigo-500' : 'bg-white border-slate-200 hover:bg-slate-50 hover:border-slate-300'}`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="medioPago"
                                                    value={medio}
                                                    checked={formPago.medioPago === medio}
                                                    onChange={e => setFormPago({ ...formPago, medioPago: e.target.value })}
                                                    className="hidden"
                                                />
                                                <i className={`text-xl mb-1 block ${formPago.medioPago === medio ? 'text-indigo-600' : 'text-slate-400'} 
                                                    ${medio === 'TRANSFERENCIA' ? 'fas fa-exchange-alt' :
                                                        medio === 'CHEQUE' ? 'fas fa-money-check-alt' :
                                                            medio === 'EFECTIVO' ? 'fas fa-coins' : 'fas fa-credit-card'}`}>
                                                </i>
                                                <span className={`text-xs font-bold ${formPago.medioPago === medio ? 'text-indigo-800' : 'text-slate-600'}`}>
                                                    {medio.charAt(0) + medio.slice(1).toLowerCase()}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-2">Fecha Exacta del Egreso</label>
                                    <div className="relative">
                                        <i className="fas fa-calendar-alt absolute left-4 top-3.5 text-slate-400"></i>
                                        <input
                                            type="date"
                                            required
                                            value={formPago.fechaPago}
                                            onChange={e => setFormPago({ ...formPago, fechaPago: e.target.value })}
                                            className="w-full pl-11 pr-4 py-3 border border-slate-300 rounded-xl font-semibold text-slate-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-2">N° Ref. / Operación</label>
                                    <div className="relative">
                                        <i className="fas fa-hashtag absolute left-4 top-3.5 text-slate-400"></i>
                                        <input
                                            type="text"
                                            placeholder="Ej: 884721"
                                            value={formPago.numeroOperacion}
                                            onChange={e => setFormPago({ ...formPago, numeroOperacion: e.target.value })}
                                            className="w-full pl-11 pr-4 py-3 border border-slate-300 rounded-xl font-mono font-bold text-slate-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white"
                                        />
                                    </div>
                                </div>

                                <div className="md:col-span-2">
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-2">Cuenta Contable / Banco de Origen</label>
                                    <div className="relative">
                                        <i className="fas fa-university absolute left-4 top-3.5 text-slate-400"></i>
                                        <select
                                            className="w-full pl-11 pr-4 py-3 border border-slate-200 rounded-xl font-medium text-slate-600 outline-none bg-slate-50 cursor-not-allowed appearance-none"
                                            value={formPago.cuentaOrigen}
                                            disabled
                                        >
                                            <option value="1">Banco Scotiabank - Cta Corriente (...0431)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </form>
                </div>

                <div className="bg-slate-50 px-6 py-4 flex flex-col-reverse md:flex-row justify-end gap-3 border-t border-slate-200 shrink-0">
                    <button
                        type="button"
                        onClick={onClose}
                        className="w-full md:w-auto px-6 py-3 text-slate-600 bg-white border border-slate-300 hover:bg-slate-100 rounded-xl text-sm font-bold transition-all"
                    >
                        Cancelar
                    </button>
                    <BotonAccion
                        type="submit"
                        form="form-pago-factura"
                        cargando={loading}
                        color="emerald"
                        tamano="lg"
                        textoCargando="Procesando..."
                        icono="fas fa-check-double"
                        className="w-full md:w-auto"
                    >
                        Confirmar Egreso
                    </BotonAccion>
                </div>

            </div>
        </div>
    );
};

export default ModalPagoFactura;