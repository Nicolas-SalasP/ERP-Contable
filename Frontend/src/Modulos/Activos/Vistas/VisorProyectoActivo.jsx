import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import ModalGenerico from '../../../Componentes/ModalGenerico';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const VisorProyectoActivo = ({ proyectoId, onVolver, onNotificar }) => {
    const [proyecto, setProyecto] = useState(null);
    const [loading, setLoading] = useState(true);
    
    // Estados Modal Facturas
    const [modalFacturasAbierto, setModalFacturasAbierto] = useState(false);
    const [facturasDisponibles, setFacturasDisponibles] = useState([]);
    const [facturaSeleccionadaId, setFacturaSeleccionadaId] = useState('');
    const [montoImputar, setMontoImputar] = useState('');

    const cargarProyecto = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/activos/proyectos/${proyectoId}/analisis`);
            if (res.success) {
                setProyecto(res.data);
            }
        } catch (error) {
            onNotificar('error', 'No se pudo cargar el análisis del proyecto.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarProyecto();
    }, [proyectoId]);

    const abrirModalImputar = async () => {
        try {
            const res = await api.get('/activos/proyectos/facturas-disponibles');
            if (res.success) {
                setFacturasDisponibles(res.data);
                if (res.data.length === 0) {
                    onNotificar('error', 'No hay facturas contables disponibles para imputar.');
                    return;
                }
                setModalFacturasAbierto(true);
            }
        } catch (error) {
            onNotificar('error', 'Error al cargar facturas disponibles.');
        }
    };

    const handleSelectFactura = (e) => {
        const id = e.target.value;
        setFacturaSeleccionadaId(id);
        const fac = facturasDisponibles.find(f => f.factura_id.toString() === id);
        if (fac) {
            setMontoImputar(fac.monto);
        } else {
            setMontoImputar('');
        }
    };

    const handleImputarFactura = async (e) => {
        e.preventDefault();
        try {
            const res = await api.post(`/activos/proyectos/${proyectoId}/facturas`, {
                factura_id: facturaSeleccionadaId,
                monto: parseFloat(montoImputar)
            });
            if (res.success) {
                onNotificar('success', 'Costo imputado al proyecto.');
                setModalFacturasAbierto(false);
                setFacturaSeleccionadaId('');
                setMontoImputar('');
                cargarProyecto(); 
            }
        } catch (error) {
            onNotificar('error', error.message || 'Error al imputar factura.');
        }
    };

    const handleActivarProyecto = async () => {
        if (window.confirm("¿Estás seguro de capitalizar este proyecto? El costo se congelará y comenzará la depreciación mensual.")) {
            try {
                const res = await api.put(`/activos/proyectos/${proyectoId}/activar`);
                if (res.success) {
                    onNotificar('success', 'Proyecto Activado Exitosamente.');
                    cargarProyecto();
                }
            } catch (error) {
                onNotificar('error', error.message || 'Error al activar el proyecto.');
            }
        }
    };

    if (loading || !proyecto) return <div className="text-center py-12"><i className="fas fa-spinner fa-spin text-3xl text-slate-400"></i></div>;

    const valorLibro = proyecto.valor_total_original - proyecto.depreciacion_acumulada;

    return (
        <div className="space-y-6 animate-fade-in">
            {/* Header / Navegación */}
            <div className="flex items-center gap-4 border-b border-slate-200 pb-4">
                <button onClick={onVolver} className="h-10 w-10 bg-slate-200 hover:bg-slate-300 rounded-full flex items-center justify-center transition-colors text-slate-600">
                    <i className="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h2 className="text-2xl font-black text-slate-800 tracking-tight">{proyecto.nombre}</h2>
                    <span className={`px-3 py-1 rounded-full text-xs font-bold inline-block mt-1 ${proyecto.estado === 'EN_CONSTRUCCION' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>
                        {proyecto.estado.replace('_', ' ')}
                    </span>
                </div>
            </div>

            {/* Dashboard KPIs */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-indigo-500">
                    <p className="text-xs font-bold text-slate-500 uppercase">Costo Histórico</p>
                    <h3 className="text-2xl font-black text-slate-800 mt-1">{formatCurrency(proyecto.valor_total_original)}</h3>
                </div>
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-rose-500">
                    <p className="text-xs font-bold text-slate-500 uppercase">Depreciación Acum.</p>
                    <h3 className="text-2xl font-black text-rose-600 mt-1">-{formatCurrency(proyecto.depreciacion_acumulada)}</h3>
                </div>
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-emerald-500">
                    <p className="text-xs font-bold text-slate-500 uppercase">Valor Libro Actual</p>
                    <h3 className="text-2xl font-black text-emerald-600 mt-1">{formatCurrency(valorLibro)}</h3>
                </div>
            </div>

            {/* Panel de Acciones (Solo visible en construcción) */}
            {proyecto.estado === 'EN_CONSTRUCCION' && (
                <div className="bg-indigo-50 border border-indigo-100 p-5 rounded-xl flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h4 className="font-bold text-indigo-900">Fase de Acumulación</h4>
                        <p className="text-sm text-indigo-700">Añade facturas al costo. Cuando finalice la obra, capitaliza el activo.</p>
                    </div>
                    <div className="flex gap-3 w-full md:w-auto">
                        <button onClick={abrirModalImputar} className="flex-1 md:flex-none px-4 py-2 bg-white text-indigo-700 font-bold border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                            <i className="fas fa-file-invoice mr-2"></i>Imputar Factura
                        </button>
                        <button onClick={handleActivarProyecto} className="flex-1 md:flex-none px-4 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                            <i className="fas fa-check-circle mr-2"></i>Capitalizar Activo
                        </button>
                    </div>
                </div>
            )}

            {/* Tabla de Facturas */}
            <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div className="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h3 className="font-bold text-slate-700">Facturas Imputadas al Costo</h3>
                </div>
                <table className="w-full text-left border-collapse">
                    <thead className="bg-white border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-6 py-4">Factura N°</th>
                            <th className="px-6 py-4">Proveedor</th>
                            <th className="px-6 py-4 text-right">Monto Asignado</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 text-sm">
                        {proyecto.facturas && proyecto.facturas.length > 0 ? (
                            proyecto.facturas.map((f, i) => (
                                <tr key={i} className="hover:bg-slate-50">
                                    <td className="px-6 py-4 font-bold text-indigo-600">{f.numero}</td>
                                    <td className="px-6 py-4 text-slate-700">{f.proveedor}</td>
                                    <td className="px-6 py-4 font-black text-slate-800 text-right">{formatCurrency(f.monto)}</td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="3" className="px-6 py-8 text-center text-slate-500">No se han imputado facturas a este proyecto aún.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* MODAL DE IMPUTACIÓN */}
            {modalFacturasAbierto && (
                <ModalGenerico isOpen={modalFacturasAbierto} onClose={() => setModalFacturasAbierto(false)} title="Vincular Factura" icon="fa-link">
                    <form onSubmit={handleImputarFactura} className="space-y-5">
                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200">
                            <label className="block text-xs font-bold text-slate-600 uppercase mb-2">Seleccionar Factura Pendiente</label>
                            <select 
                                required 
                                value={facturaSeleccionadaId}
                                onChange={handleSelectFactura} 
                                className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-slate-700 font-medium"
                            >
                                <option value="">Seleccione una factura...</option>
                                {facturasDisponibles.map(f => (
                                    <option key={f.factura_id} value={f.factura_id}>
                                        Doc. {f.numero_factura} - {f.proveedor} ({formatCurrency(f.monto)})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-slate-600 uppercase mb-2">Monto a Imputar (CLP)</label>
                            <input 
                                type="number" 
                                required 
                                value={montoImputar} 
                                onChange={(e) => setMontoImputar(e.target.value)} 
                                className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-slate-700 font-bold" 
                            />
                            <p className="text-xs text-slate-500 mt-1">Puedes imputar el total o un monto parcial de la factura.</p>
                        </div>

                        <div className="flex gap-3 pt-2">
                            <button type="button" onClick={() => setModalFacturasAbierto(false)} className="w-1/2 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg transition-colors">
                                Cancelar
                            </button>
                            <button type="submit" className="w-1/2 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm transition-colors">
                                Imputar Costo
                            </button>
                        </div>
                    </form>
                </ModalGenerico>
            )}
        </div>
    );
};

export default VisorProyectoActivo;