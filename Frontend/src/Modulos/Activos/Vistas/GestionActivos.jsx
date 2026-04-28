import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import GestionProyectosActivos from './GestionProyectosActivos';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const GestionActivos = () => {
    const [activosPendientes, setActivosPendientes] = useState([]);
    const [activosRegistrados, setActivosRegistrados] = useState([]);

    const [tabActiva, setTabActiva] = useState('PENDIENTES');
    const [notificacion, setNotificacion] = useState(null);
    const [loading, setLoading] = useState(true);
    const [depreciando, setDepreciando] = useState(false);
    const [mesDepreciacion, setMesDepreciacion] = useState(new Date().toISOString().slice(0, 7));

    const mostrarNotificacion = (tipo, mensaje) => {
        setNotificacion({ tipo, mensaje });
        setTimeout(() => setNotificacion(null), 4000);
    };

    const cargarDatos = async () => {
        setLoading(true);

        try {
            const resPendientes = await api.get('/activos/pendientes');
            if (resPendientes.success) {
                setActivosPendientes(resPendientes.data);
            }
        } catch (error) {
            console.error("Error al cargar activos pendientes:", error);
        }

        try {
            const resRegistrados = await api.get('/activos');
            if (resRegistrados.success) {
                setActivosRegistrados(resRegistrados.data);
            }
        } catch (error) {
            console.error("Error al cargar activos registrados:", error);
        }

        setLoading(false);
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const handleEjecutarDepreciacion = async () => {
        if (!mesDepreciacion) {
            return mostrarNotificacion('error', 'Debes seleccionar un mes para depreciar.');
        }

        setDepreciando(true);
        try {
            const response = await api.post('/activos/depreciar-mes', {
                mes_anio: mesDepreciacion 
            });

            if (response.success) {
                mostrarNotificacion('success', response.message || response.data?.mensaje);
                cargarDatos(); 
            }
        } catch (error) {
            mostrarNotificacion('error', error.response?.data?.message || error.message || 'Error al ejecutar depreciación.');
        } finally {
            setDepreciando(false);
        }
    };

    return (
        <div className="p-4 md:p-6 lg:p-8 max-w-7xl mx-auto bg-slate-50 min-h-screen">
            <div className="mb-6 md:mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">Activos Fijos</h1>
                    <p className="text-slate-500 font-medium text-sm md:text-base mt-1">
                        Gestión patrimonial, depreciación y control de proyectos en construcción.
                    </p>
                </div>
            </div>

            {notificacion && (
                <div className={`mb-6 p-4 rounded-lg flex items-center shadow-sm animate-fade-in ${notificacion.tipo === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800'}`}>
                    <i className={`fas ${notificacion.tipo === 'success' ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-circle text-rose-500'} text-xl mr-3`}></i>
                    <span className="font-medium">{notificacion.mensaje}</span>
                </div>
            )}

            <div className="flex overflow-x-auto hide-scrollbar border-b border-slate-200 mb-6 gap-6">
                <button
                    onClick={() => setTabActiva('PENDIENTES')}
                    className={`pb-3 font-bold text-sm whitespace-nowrap transition-colors relative ${tabActiva === 'PENDIENTES' ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-700'}`}
                >
                    <i className="fas fa-inbox mr-2"></i> Pendientes ({activosPendientes.length})
                    {tabActiva === 'PENDIENTES' && <span className="absolute bottom-0 left-0 w-full h-0.5 bg-indigo-600 rounded-t-full"></span>}
                </button>
                <button
                    onClick={() => setTabActiva('REGISTRADOS')}
                    className={`pb-3 font-bold text-sm whitespace-nowrap transition-colors relative ${tabActiva === 'REGISTRADOS' ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-700'}`}
                >
                    <i className="fas fa-cubes mr-2"></i> Activos Registrados ({activosRegistrados.length})
                    {tabActiva === 'REGISTRADOS' && <span className="absolute bottom-0 left-0 w-full h-0.5 bg-indigo-600 rounded-t-full"></span>}
                </button>
                <button
                    onClick={() => setTabActiva('PROYECTOS')}
                    className={`pb-3 font-bold text-sm whitespace-nowrap transition-colors relative ${tabActiva === 'PROYECTOS' ? 'text-emerald-600' : 'text-slate-500 hover:text-slate-700'}`}
                >
                    <i className="fas fa-hard-hat mr-2"></i> Proyectos en Curso
                    {tabActiva === 'PROYECTOS' && <span className="absolute bottom-0 left-0 w-full h-0.5 bg-emerald-600 rounded-t-full"></span>}
                </button>
            </div>

            {loading ? (
                <div className="py-12 flex justify-center items-center text-slate-400">
                    <i className="fas fa-circle-notch fa-spin text-3xl"></i>
                </div>
            ) : (
                <div className="transition-all duration-300">
                    {tabActiva === 'PENDIENTES' && (
                        <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                            <table className="w-full text-left border-collapse">
                                <thead className="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
                                    <tr>
                                        <th className="px-6 py-4 font-bold">Documento</th>
                                        <th className="px-6 py-4 font-bold">Proveedor</th>
                                        <th className="px-6 py-4 font-bold">Cuenta Sugerida</th>
                                        <th className="px-6 py-4 font-bold text-right">Monto</th>
                                        <th className="px-6 py-4 font-bold text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-sm">
                                    {activosPendientes.map((activo, idx) => (
                                        <tr key={idx} className="hover:bg-slate-50 transition-colors">
                                            <td className="px-6 py-4 font-bold text-indigo-600">{activo.numero_factura}</td>
                                            <td className="px-6 py-4 text-slate-700">{activo.proveedor}</td>
                                            <td className="px-6 py-4 text-slate-500"><span className="bg-slate-100 px-2 py-1 rounded text-xs">{activo.nombre_cuenta}</span></td>
                                            <td className="px-6 py-4 font-black text-slate-800 text-right">{formatCurrency(activo.monto_adquisicion)}</td>
                                            <td className="px-6 py-4 text-center">
                                                <button className="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-bold rounded transition-colors text-xs">
                                                    Activar Activo
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                    {activosPendientes.length === 0 && <tr><td colSpan="5" className="px-6 py-8 text-center text-slate-500">No hay facturas pendientes de activar.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {tabActiva === 'REGISTRADOS' && (
                        <div className="space-y-4">
                            <div className="flex justify-end items-center gap-3">
                                <div className="flex flex-col items-end">
                                    <label className="text-[10px] font-bold text-slate-400 uppercase">Período a Depreciar</label>
                                    <input 
                                        type="month" 
                                        value={mesDepreciacion}
                                        onChange={(e) => setMesDepreciacion(e.target.value)}
                                        className="px-3 py-2 border border-slate-300 rounded-lg text-sm font-bold text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all bg-white"
                                    />
                                </div>
                                <button 
                                    onClick={handleEjecutarDepreciacion}
                                    disabled={depreciando || !mesDepreciacion}
                                    className="px-4 py-2 mt-4 bg-slate-800 hover:bg-slate-900 disabled:opacity-50 text-white text-sm font-bold rounded-lg shadow-sm transition-all flex items-center gap-2 h-10"
                                >
                                    {depreciando ? <i className="fas fa-spinner fa-spin"></i> : <i className="fas fa-calculator"></i>}
                                    <span>{depreciando ? 'Calculando...' : 'Ejecutar Depreciación'}</span>
                                </button>
                            </div>
                            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                <table className="w-full text-left border-collapse">
                                    <thead className="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
                                        <tr>
                                            <th className="px-6 py-4 font-bold">Nombre del Activo</th>
                                            <th className="px-6 py-4 font-bold">Categoría SII</th>
                                            <th className="px-6 py-4 font-bold">Vida Útil</th>
                                            <th className="px-6 py-4 font-bold text-right">Costo Original</th>
                                            <th className="px-6 py-4 font-bold text-right text-emerald-600">Valor Actual</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 text-sm">
                                        {activosRegistrados.map((activo) => {
                                            const valorActual = activo.monto_adquisicion - (activo.depreciacion_acumulada || 0);

                                            return (
                                                <tr key={activo.id} className="hover:bg-slate-50 transition-colors">
                                                    <td className="px-6 py-4">
                                                        <p className="font-bold text-slate-800">{activo.nombre_activo}</p>
                                                        <p className="text-xs text-slate-500 mt-0.5">{activo.cuenta_nombre}</p>
                                                    </td>
                                                    <td className="px-6 py-4 text-slate-600">{activo.categoria_sii}</td>
                                                    <td className="px-6 py-4 text-slate-600">{activo.vida_util_meses} meses</td>
                                                    <td className="px-6 py-4 font-black text-slate-500 text-right">{formatCurrency(activo.monto_adquisicion)}</td>
                                                    <td className="px-6 py-4 font-black text-emerald-600 text-right">{formatCurrency(valorActual)}</td>
                                                </tr>
                                            );
                                        })}
                                        {activosRegistrados.length === 0 && <tr><td colSpan="5" className="px-6 py-8 text-center text-slate-500">No hay activos operativos.</td></tr>}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {tabActiva === 'PROYECTOS' && (
                        <GestionProyectosActivos onNotificar={mostrarNotificacion} />
                    )}
                </div>
            )}
        </div>
    );
};

export default GestionActivos;