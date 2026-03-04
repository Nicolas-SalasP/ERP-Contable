import React, { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api'; // Ajusta la ruta a tu api

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const ModalPagoFactura = ({ isOpen, onClose, factura, onPagoExitoso }) => {
    const [loading, setLoading] = useState(false);
    const [formPago, setFormPago] = useState({
        fecha_pago: new Date().toISOString().split('T')[0],
        monto_pagado: '',
        cuenta_bancaria_empresa_id: '1', // ID 1 = Scotiabank en tu BD
        cuenta_contable_banco: '110107', // Código contable del Scotiabank
        numero_operacion: ''
    });

    // Cuando se abre el modal, seteamos el monto por defecto al total de la factura
    useEffect(() => {
        if (factura) {
            setFormPago(prev => ({ ...prev, monto_pagado: factura.monto_bruto }));
        }
    }, [factura]);

    if (!isOpen || !factura) return null;

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (formPago.monto_pagado <= 0) {
            return Swal.fire('Error', 'El monto a pagar debe ser mayor a cero.', 'error');
        }

        setLoading(true);
        try {
            const res = await api.post(`/facturas/${factura.id}/pagar`, formPago);
            
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Pago Registrado!',
                    text: `El pago ha sido contabilizado con éxito. (Asiento N° ${res.asiento_codigo})`,
                    customClass: { confirmButton: 'bg-emerald-600 text-white font-bold py-2 px-6 rounded-lg' },
                    buttonsStyling: false
                });
                onPagoExitoso(); // Recarga la tabla en el componente padre
                onClose(); // Cierra el modal
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error al procesar el pago',
                text: error.response?.data?.mensaje || 'Hubo un problema de conexión.',
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' },
                buttonsStyling: false
            });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200">
                <div className="bg-slate-900 px-6 py-4 flex justify-between items-center text-white">
                    <div>
                        <h2 className="text-lg font-bold">Pagar Factura N° {factura.numero_factura}</h2>
                        <p className="text-xs text-slate-400 mt-1">{factura.nombre_proveedor}</p>
                    </div>
                    <button onClick={onClose} className="text-slate-400 hover:text-white transition-colors">
                        <i className="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form onSubmit={handleSubmit} className="p-6 space-y-5">
                    {/* Resumen de la Deuda */}
                    <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex justify-between items-center">
                        <div>
                            <p className="text-xs font-bold text-emerald-700 uppercase">Total a Pagar</p>
                            <p className="text-2xl font-black text-emerald-900">{formatCurrency(factura.monto_bruto)}</p>
                        </div>
                        <i className="fas fa-file-invoice-dollar text-3xl text-emerald-300"></i>
                    </div>

                    {/* Banco de Origen */}
                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Banco de Origen (Empresa)</label>
                        <select 
                            className="w-full border border-slate-300 rounded-lg p-3 font-medium text-slate-800 outline-none bg-slate-50 cursor-not-allowed"
                            value={formPago.cuenta_bancaria_empresa_id}
                            disabled
                        >
                            <option value="1">Scotiabank - Cta Corriente (...0431)</option>
                        </select>
                        <p className="text-[10px] text-slate-400 mt-1">* Por ahora usando tu cuenta principal registrada.</p>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Fecha de Pago</label>
                            <input 
                                type="date" 
                                required
                                value={formPago.fecha_pago}
                                onChange={e => setFormPago({...formPago, fecha_pago: e.target.value})}
                                className="w-full border border-slate-300 rounded-lg p-3 font-bold text-slate-800 outline-none focus:border-emerald-500 transition-all"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-500 uppercase mb-2">N° Transf. / Cheque</label>
                            <input 
                                type="text" 
                                placeholder="Opcional"
                                value={formPago.numero_operacion}
                                onChange={e => setFormPago({...formPago, numero_operacion: e.target.value})}
                                className="w-full border border-slate-300 rounded-lg p-3 font-bold text-slate-800 outline-none focus:border-emerald-500 transition-all"
                            />
                        </div>
                    </div>

                    <div className="pt-4 flex justify-end gap-3 border-t border-slate-100 mt-6">
                        <button type="button" onClick={onClose} className="px-5 py-2.5 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-bold transition-all">
                            Cancelar
                        </button>
                        <button 
                            type="submit" 
                            disabled={loading}
                            className="px-6 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-bold shadow-lg hover:bg-emerald-700 disabled:opacity-50 transition-all flex items-center"
                        >
                            {loading ? <i className="fas fa-spinner fa-spin mr-2"></i> : <i className="fas fa-check-circle mr-2"></i>}
                            Procesar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default ModalPagoFactura;