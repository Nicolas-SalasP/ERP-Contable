import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import VisorProyectoActivo from './VisorProyectoActivo';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const GestionProyectosActivos = ({ onNotificar }) => {
    const [proyectos, setProyectos] = useState([]);
    const [tiposActivos, setTiposActivos] = useState([]);
    const [loading, setLoading] = useState(true);
    
    const [proyectoSeleccionado, setProyectoSeleccionado] = useState(null);
    const [modalAbierto, setModalAbierto] = useState(false);
    
    const [nuevoProyecto, setNuevoProyecto] = useState({
        nombre: '', tipo_activo_id: '', anio_fabricacion: new Date().getFullYear(), vida_util_meses: 60, centro_costo_id: 1, empleado_id: 1
    });

    const cargarDatos = async () => {
        setLoading(true);
        try {
            const [resProyectos, resParams] = await Promise.all([
                api.get('/activos/proyectos'),
                api.get('/activos/parametros') 
            ]);
            
            if (resProyectos.success) setProyectos(resProyectos.data);
            if (resParams.success) setTiposActivos(resParams.data.cuentas_activo || []);
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { 
        cargarDatos(); 
    }, []);

    const handleCrearProyecto = async (e) => {
        e.preventDefault();
        try {
            const res = await api.post('/activos/proyectos', nuevoProyecto);
            if (res.success) {
                onNotificar('success', 'Proyecto creado exitosamente.');
                setModalAbierto(false);
                setNuevoProyecto({ nombre: '', tipo_activo_id: '', anio_fabricacion: new Date().getFullYear(), vida_util_meses: 60, centro_costo_id: 1, empleado_id: 1 });
                cargarDatos();
            }
        } catch (error) {
            onNotificar('error', error.message || 'Error al crear proyecto.');
        }
    };

    if (proyectoSeleccionado) {
        return <VisorProyectoActivo 
            proyectoId={proyectoSeleccionado} 
            onVolver={() => { setProyectoSeleccionado(null); cargarDatos(); }} 
            onNotificar={onNotificar} 
        />;
    }

    if (loading) return <div className="text-center py-8"><i className="fas fa-spinner fa-spin text-2xl text-slate-400"></i></div>;

    const getEstadoBadge = (estado) => {
        if (estado === 'EN_CONSTRUCCION') return <span className="bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-xs font-bold border border-amber-200">En Construcción</span>;
        return <span className="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full text-xs font-bold border border-emerald-200">Activo Operativo</span>;
    };

    return (
        <div className="space-y-6 animate-fade-in">
            <div className="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div className="flex items-center gap-4 w-full sm:w-auto">
                    <div className="h-12 w-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-2xl border border-emerald-100 shrink-0">
                        <i className="fas fa-tools"></i>
                    </div>
                    <div>
                        <h2 className="text-xl font-black text-slate-800">Proyectos en Curso</h2>
                        <p className="text-sm text-slate-500">{proyectos.length} proyectos gestionados</p>
                    </div>
                </div>
                <button onClick={() => setModalAbierto(true)} className="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                    <i className="fas fa-plus mr-2"></i> Nuevo Proyecto
                </button>
            </div>
            {proyectos.length === 0 ? (
                <div className="bg-white border border-slate-200 rounded-xl p-10 text-center">
                    <div className="h-16 w-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center text-2xl mx-auto mb-4"><i className="fas fa-folder-open"></i></div>
                    <h3 className="text-lg font-bold text-slate-700">No hay proyectos activos</h3>
                    <p className="text-slate-500 mt-1">Comienza creando un nuevo proyecto para acumular facturas y capitalizar activos.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {proyectos.map((p) => (
                        <div key={p.id_proyecto} className="bg-white rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition-all flex flex-col relative overflow-hidden group">
                            <div className={`h-1.5 w-full ${p.estado === 'EN_CONSTRUCCION' ? 'bg-amber-400' : 'bg-emerald-500'}`}></div>
                            <div className="p-5 flex-grow">
                                <h3 className="font-bold text-slate-800 text-lg mb-4">{p.nombre}</h3>
                                <div className="space-y-2 bg-slate-50 p-4 rounded-lg">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-slate-500 font-bold">Costo Acumulado:</span>
                                        <span className="font-black text-indigo-700">{formatCurrency(p.valor_total_original)}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="px-5 py-4 border-t border-slate-100 flex justify-between items-center bg-white">
                                {getEstadoBadge(p.estado)}
                                <button onClick={() => setProyectoSeleccionado(p.id_proyecto)} className="text-indigo-600 text-sm font-bold hover:text-indigo-800 bg-indigo-50 px-3 py-1.5 rounded transition-colors opacity-0 group-hover:opacity-100">
                                    Analizar <i className="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
            {modalAbierto && (
                <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden animate-fade-in-up">
                        <div className="flex justify-between items-center p-5 border-b border-slate-100">
                            <h3 className="text-xl font-black text-slate-800 flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">
                                    <i className="fas fa-hard-hat"></i>
                                </div>
                                Crear Proyecto
                            </h3>
                            <button onClick={() => setModalAbierto(false)} className="text-slate-400 hover:text-rose-500 transition-colors text-xl">
                                <i className="fas fa-times"></i>
                            </button>
                        </div>
                        <div className="p-6 bg-slate-50">
                            <form onSubmit={handleCrearProyecto} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Nombre del Proyecto</label>
                                    <input 
                                        type="text" required 
                                        value={nuevoProyecto.nombre} 
                                        onChange={(e) => setNuevoProyecto({...nuevoProyecto, nombre: e.target.value})} 
                                        className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-slate-700 font-medium" 
                                        placeholder="Ej: Maquinaria Industrial"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Cuenta de Activo</label>
                                    <select 
                                        required 
                                        value={nuevoProyecto.tipo_activo_id} 
                                        onChange={(e) => setNuevoProyecto({...nuevoProyecto, tipo_activo_id: e.target.value})} 
                                        className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-slate-700 font-medium bg-white"
                                    >
                                        <option value="">Seleccione Cuenta...</option>
                                        {tiposActivos?.map(t => <option key={t.id} value={t.id}>{t.codigo} - {t.nombre}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Vida Útil (Meses)</label>
                                    <input 
                                        type="number" required min="1"
                                        value={nuevoProyecto.vida_util_meses} 
                                        onChange={(e) => setNuevoProyecto({...nuevoProyecto, vida_util_meses: e.target.value})} 
                                        className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-slate-700 font-medium" 
                                    />
                                </div>
                                <div className="flex gap-3 pt-4 border-t border-slate-200 mt-6">
                                    <button type="button" onClick={() => setModalAbierto(false)} className="w-1/2 py-2.5 text-slate-600 font-bold bg-white border border-slate-300 hover:bg-slate-100 rounded-lg transition-colors shadow-sm">
                                        Cancelar
                                    </button>
                                    <button type="submit" className="w-1/2 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm transition-colors">
                                        Guardar Proyecto
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionProyectosActivos;