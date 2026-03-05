import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import { useNavigate } from 'react-router-dom';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const HistorialCotizaciones = ({ clienteId }) => {
    const [cotizaciones, setCotizaciones] = useState([]);
    const [loading, setLoading] = useState(true);
    const navigate = useNavigate();

    useEffect(() => {
        const fetchHistorial = async () => {
            if (!clienteId) {
                setLoading(false);
                return;
            }
            try {
                const res = await api.get(`/cotizaciones?cliente_id=${clienteId}`);
                if (res.success) {
                    setCotizaciones(res.data || []);
                }
            } catch (error) {
                console.error("Error cargando historial", error);
            } finally {
                setLoading(false);
            }
        };
        
        fetchHistorial();
    }, [clienteId]);

    if (!clienteId) {
        return (
            <div className="p-10 text-center text-slate-400">
                <i className="fas fa-save text-4xl mb-3 text-slate-300"></i>
                <p className="font-medium">Debes registrar y guardar al cliente primero para ver su historial.</p>
            </div>
        );
    }

    if (loading) {
        return (
            <div className="p-10 text-center text-slate-400">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500 mx-auto mb-3"></div>
                <p className="font-medium">Cargando historial...</p>
            </div>
        );
    }

    if (cotizaciones.length === 0) {
        return (
            <div className="p-10 text-center text-slate-400">
                <i className="fas fa-folder-open text-4xl mb-3 text-slate-300"></i>
                <p className="font-medium">Este cliente aún no tiene cotizaciones registradas.</p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
            <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-xs">
                    <tr>
                        <th className="px-4 py-3 font-bold tracking-wider">Fecha</th>
                        <th className="px-4 py-3 font-bold tracking-wider">Folio</th>
                        <th className="px-4 py-3 font-bold text-right tracking-wider">Total</th>
                        <th className="px-4 py-3 font-bold text-center tracking-wider">Estado</th>
                        <th className="px-4 py-3 text-center"></th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {cotizaciones.map(cot => {
                        const fechaReal = cot.fecha || cot.fecha_emision || cot.created_at || '';
                        let fechaFormateada = '---';
                        if (fechaReal) {
                            const fechaLimpia = fechaReal.split('T')[0];
                            const [year, month, day] = fechaLimpia.split('-');
                            fechaFormateada = `${day}-${month}-${year}`;
                        }

                        const valorFolio = cot.folio || cot.numero || cot.id || 0;
                        const folioDisplay = `#${String(valorFolio).padStart(5, '0')}`;

                        const estadoCot = (cot.estado_nombre || 'PENDIENTE').toUpperCase();
                        let colorClases = 'bg-amber-50 text-amber-700 border-amber-200';
                        
                        if (['ACEPTADA', 'APROBADA', 'FACTURADA'].includes(estadoCot)) {
                            colorClases = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                        } else if (['RECHAZADA', 'ANULADA', 'VENCIDA'].includes(estadoCot)) {
                            colorClases = 'bg-red-50 text-red-700 border-red-200';
                        }

                        return (
                            <tr 
                                key={cot.id} 
                                onClick={() => navigate(`/cotizaciones`)} 
                                className="hover:bg-emerald-50 transition-colors cursor-pointer group"
                                title="Abrir cotización completa"
                            >
                                <td className="px-4 py-3 font-medium text-slate-600 group-hover:text-emerald-700 transition-colors">
                                    {fechaFormateada}
                                </td>
                                <td className="px-4 py-3 font-mono font-bold text-emerald-600">
                                    {folioDisplay}
                                </td>
                                <td className="px-4 py-3 font-black text-slate-800 text-right">
                                    {formatCurrency(cot.total || 0)}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <span className={`inline-block px-3 py-1 text-[10px] font-bold rounded-full uppercase border ${colorClases}`}>
                                        {estadoCot}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-center text-slate-300 group-hover:text-emerald-600 transition-colors">
                                    <svg className="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
};

export default HistorialCotizaciones;