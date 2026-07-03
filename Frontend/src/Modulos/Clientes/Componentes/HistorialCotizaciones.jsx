import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import { useNavigate } from 'react-router-dom';
import { logger } from '../../../Configuracion/logger';
import { ArrowUpRight } from 'lucide-react';
import { formatearMoneda } from '../../../Utilidades/formato';
const formatCurrency = formatearMoneda;

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
                logger.error("Error cargando historial", error);
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
            <EstadoCarga
                cargando={true}
                mensajeCargando="Cargando historial..."
                tamano="compacto"
                color="emerald"
            />
        );
    }

    if (cotizaciones.length === 0) {
        return (
            <EstadoCarga
                vacio={true}
                mensajeVacio="Este cliente aún no tiene cotizaciones registradas."
                iconoVacio="📋"
                tamano="compacto"
            />
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <table className="w-full text-left text-sm">
                <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase text-xs">
                    <tr>
                        <th className="px-4 py-3 font-bold tracking-wider">Fecha</th>
                        <th className="px-4 py-3 font-bold tracking-wider">Folio</th>
                        <th className="px-4 py-3 font-bold text-right tracking-wider">Total</th>
                        <th className="px-4 py-3 font-bold text-center tracking-wider">Estado</th>
                        <th className="px-4 py-3 text-center"></th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
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
                                className="hover:bg-emerald-50 dark:hover:bg-slate-700 transition-colors cursor-pointer group"
                                title="Abrir cotización completa"
                            >
                                <td className="px-4 py-3 font-medium text-slate-600 dark:text-slate-400 group-hover:text-emerald-700 transition-colors">
                                    {fechaFormateada}
                                </td>
                                <td className="px-4 py-3 font-mono font-bold text-emerald-600">
                                    {folioDisplay}
                                </td>
                                <td className="px-4 py-3 font-black text-slate-800 dark:text-slate-200 text-right">
                                    {formatCurrency(cot.total || 0)}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <span className={`inline-block px-3 py-1 text-[10px] font-bold rounded-full uppercase border ${colorClases}`}>
                                        {estadoCot}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-center text-slate-300 group-hover:text-emerald-600 transition-colors">
                                    <ArrowUpRight size={16} strokeWidth={1.75} className="inline" />
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
