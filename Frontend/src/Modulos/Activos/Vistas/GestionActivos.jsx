import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import GestionProyectosActivos from './GestionProyectosActivos';
import { logger } from '../../../Configuracion/logger';
const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const calcularVidaUtilRestante = (activo) => {
    if (!activo.vida_util_meses || !activo.valor_adquisicion || activo.valor_adquisicion === 0) {
        return activo.vida_util_meses || 0;
    }
    const depreciado = activo.depreciacion_acumulada || 0;
    const porcentaje = depreciado / activo.valor_adquisicion;
    const mesesRestantes = activo.vida_util_meses - Math.floor(porcentaje * activo.vida_util_meses);
    return Math.max(0, mesesRestantes);
};

const GestionActivos = () => {
    const [activosPendientes, setActivosPendientes] = useState([]);
    const [activosRegistrados, setActivosRegistrados] = useState([]);

    const [tabActiva, setTabActiva] = useState('PENDIENTES');
    const [notificacion, setNotificacion] = useState(null);
    const [loading, setLoading] = useState(true);
    const [depreciando, setDepreciando] = useState(false);
    const [mesDepreciacion, setMesDepreciacion] = useState(new Date().toISOString().slice(0, 7));

    // Estados para la Baja de Activos
    const [modalBajaAbierto, setModalBajaAbierto] = useState(false);
    const [activoABajar, setActivoABajar] = useState(null);
    const [motivoBaja, setMotivoBaja] = useState('');

    // Estados para la Edicion de Activos
    const [modalEditarAbierto, setModalEditarAbierto] = useState(false);
    const [activoEditando, setActivoEditando] = useState(null);
    const [formEditar, setFormEditar] = useState({ nombre: '', descripcion: '' });
    const [guardandoEdicion, setGuardandoEdicion] = useState(false);

    const mostrarNotificacion = (tipo, mensaje) => {
        setNotificacion({ tipo, mensaje });
        setTimeout(() => setNotificacion(null), 4000);
    };

    const cargarDatos = async () => {
        setLoading(true);
        try {
            const resPendientes = await api.get('/activos/pendientes');
            if (resPendientes.success) setActivosPendientes(resPendientes.data);
        } catch (error) { logger.error(error); }

        try {
            const resRegistrados = await api.get('/activos');
            if (resRegistrados.success) setActivosRegistrados(resRegistrados.data);
        } catch (error) { logger.error(error); }

        setLoading(false);
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const handleEjecutarDepreciacion = async () => {
        if (!mesDepreciacion) return mostrarNotificacion('error', 'Debes seleccionar un mes para depreciar.');
        setDepreciando(true);
        try {
            const response = await api.post('/activos/depreciar-mes', { mes_anio: mesDepreciacion });
            if (response.success) {
                mostrarNotificacion('success', response.message || response.data?.mensaje);
                cargarDatos(); 
            }
        } catch (error) {
            mostrarNotificacion('error', error.response?.data?.message || error.message || 'Error al depreciar.');
        } finally {
            setDepreciando(false);
        }
    };

    // Funciones para Baja de Activo
    const abrirModalBaja = (activo) => {
        setActivoABajar(activo);
        setMotivoBaja('');
        setModalBajaAbierto(true);
    };

    const confirmarBajaActivo = async (e) => {
        e.preventDefault();
        try {
            const res = await api.put(`/activos/${activoABajar.id}/baja`, {
                motivo: motivoBaja
            });
            if (res.success) {
                mostrarNotificacion('success', res.message);
                setModalBajaAbierto(false);
                setActivoABajar(null);
                cargarDatos();
            }
        } catch (error) {
            alert("Error al dar de baja: " + (error.response?.data?.message || error.message));
        }
    };
    const abrirModalEditar = (activo) => {
        setActivoEditando(activo);
        setFormEditar({
            nombre: activo.nombre || '',
            descripcion: activo.descripcion || ''
        });
        setModalEditarAbierto(true);
    };

    const cerrarModalEditar = () => {
        setModalEditarAbierto(false);
        setActivoEditando(null);
        setFormEditar({ nombre: '', descripcion: '' });
    };

    const confirmarEdicionActivo = async (e) => {
        e.preventDefault();
        if (!formEditar.nombre.trim()) {
            mostrarNotificacion('error', 'El nombre del activo es obligatorio.');
            return;
        }
        setGuardandoEdicion(true);
        try {
            const res = await api.put(`/activos/${activoEditando.id}`, {
                nombre: formEditar.nombre.trim(),
                descripcion: formEditar.descripcion?.trim() || null,
            });
            if (res.success) {
                mostrarNotificacion('success', 'Activo actualizado correctamente.');
                cerrarModalEditar();
                cargarDatos();
            }
        } catch (error) {
            mostrarNotificacion('error', error.message || 'Error al guardar la edicion.');
        } finally {
            setGuardandoEdicion(false);
        }
    };

    return (
        <div className="p-4 md:p-6 lg:p-8 max-w-7xl mx-auto bg-slate-50 min-h-screen pb-20">
            <div className="mb-6 md:mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div className="flex items-center gap-3"><h1 className="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">Activos Fijos</h1><AyudaModulo moduloId="activoFijo" size={26} /></div>
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
                <button onClick={() => setTabActiva('PENDIENTES')} className={`pb-3 font-bold text-sm whitespace-nowrap transition-colors relative ${tabActiva === 'PENDIENTES' ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-700'}`}>
                    <i className="fas fa-inbox mr-2"></i> Pendientes ({activosPendientes.length})
                    {tabActiva === 'PENDIENTES' && <span className="absolute bottom-0 left-0 w-full h-0.5 bg-indigo-600 rounded-t-full"></span>}
                </button>
                <button onClick={() => setTabActiva('REGISTRADOS')} className={`pb-3 font-bold text-sm whitespace-nowrap transition-colors relative ${tabActiva === 'REGISTRADOS' ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-700'}`}>
                    <i className="fas fa-cubes mr-2"></i> Activos Registrados ({activosRegistrados.filter(a => a.estado === 'ACTIVO').length})
                    {tabActiva === 'REGISTRADOS' && <span className="absolute bottom-0 left-0 w-full h-0.5 bg-indigo-600 rounded-t-full"></span>}
                </button>
                <button onClick={() => setTabActiva('PROYECTOS')} className={`pb-3 font-bold text-sm whitespace-nowrap transition-colors relative ${tabActiva === 'PROYECTOS' ? 'text-emerald-600' : 'text-slate-500 hover:text-slate-700'}`}>
                    <i className="fas fa-hard-hat mr-2"></i> Proyectos en Curso
                    {tabActiva === 'PROYECTOS' && <span className="absolute bottom-0 left-0 w-full h-0.5 bg-emerald-600 rounded-t-full"></span>}
                </button>
            </div>

            {loading ? (
                <EstadoCarga
                    cargando={true}
                    mensajeCargando="Cargando activos..."
                    tamano="compacto"
                    color="emerald"
                />
            ) : (
                <div className="transition-all duration-300">
                    {/* TAB: PENDIENTES */}
                    {tabActiva === 'PENDIENTES' && (
                        <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                            <table className="w-full text-left border-collapse">
                                <thead className="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
                                    <tr>
                                        <th className="px-6 py-4 font-bold">Documento</th>
                                        <th className="px-6 py-4 font-bold">Proveedor</th>
                                        <th className="px-6 py-4 font-bold">Cuenta Sugerida</th>
                                        <th className="px-6 py-4 font-bold text-right">Monto Neto</th>
                                        <th className="px-6 py-4 font-bold text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-sm">
                                    {activosPendientes.map((activo, idx) => (
                                        <tr key={idx} className="hover:bg-slate-50 transition-colors">
                                            <td className="px-6 py-4 font-bold text-indigo-600">{activo.numero_factura}</td>
                                            <td className="px-6 py-4 text-slate-700">{activo.proveedor}</td>
                                            <td className="px-6 py-4 text-slate-500"><span className="bg-slate-100 px-2 py-1 rounded text-xs">{activo.nombre_cuenta}</span></td>
                                            <td className="px-6 py-4 font-black text-slate-800 text-right">{formatCurrency(activo.valor_adquisicion)}</td>
                                            <td className="px-6 py-4 text-center">
                                                <button 
                                                    onClick={() => alert("Próxima mejora: Transformación automática a Proyecto Activo.")}
                                                    className="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-bold rounded transition-colors text-xs"
                                                >
                                                    Crear Proyecto
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                    {activosPendientes.length === 0 && <tr><td colSpan="5" className="px-6 py-8 text-center text-slate-500 italic">No hay facturas pendientes de activar.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* TAB: ACTIVOS REGISTRADOS */}
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
                                            <th className="px-6 py-4 font-bold">Código / Nombre</th>
                                            <th className="px-6 py-4 font-bold">Clasificación</th>
                                            <th className="px-6 py-4 font-bold">Vida Útil</th>
                                            <th className="px-6 py-4 font-bold text-right">Costo Orig.</th>
                                            <th className="px-6 py-4 font-bold text-right text-emerald-600">Valor Libro</th>
                                            <th className="px-6 py-4 font-bold text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 text-sm">
                                        {activosRegistrados.map((activo) => {
                                            const valorActual = (activo.valor_adquisicion || 0) - (activo.depreciacion_acumulada || 0);
                                            const dadoDeBaja = activo.estado === 'DADO_DE_BAJA';

                                            return (
                                                <tr key={activo.id} className={`transition-colors ${dadoDeBaja ? 'bg-slate-50 opacity-60' : 'hover:bg-slate-50'}`}>
                                                    <td className="px-6 py-4">
                                                        <p className={`font-bold ${dadoDeBaja ? 'text-slate-500 line-through' : 'text-slate-800'}`}>
                                                            <span className="text-xs text-slate-400 font-normal mr-2">{activo.codigo}</span>
                                                            {activo.nombre}
                                                        </p>
                                                        <p className="text-[10px] text-slate-500 mt-0.5 uppercase tracking-wider">{activo.cuenta?.nombre || 'Sin cuenta'}</p>
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <span className="text-slate-600 bg-slate-100 px-2 py-1 rounded text-[10px] font-bold uppercase">
                                                            {activo.cuenta?.categoria_sii || 'General'}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 text-slate-600 font-medium">
                                                        {dadoDeBaja ? '-' : <><span className="font-bold">{calcularVidaUtilRestante(activo)}</span> / {activo.vida_util_meses || 0}m</>}
                                                    </td>
                                                    <td className="px-6 py-4 font-black text-slate-500 text-right">{formatCurrency(activo.valor_adquisicion)}</td>
                                                    <td className={`px-6 py-4 font-black text-right ${dadoDeBaja ? 'text-slate-400' : 'text-emerald-600'}`}>{formatCurrency(valorActual)}</td>
                                                    <td className="px-6 py-4 text-center">
                                                        {!dadoDeBaja ? (
                                                            <div className="flex items-center justify-center gap-2">
                                                                <button
                                                                    onClick={() => abrirModalEditar(activo)}
                                                                    className="text-blue-500 hover:text-blue-700 hover:bg-blue-50 p-2 rounded-lg transition-colors shadow-sm border border-transparent hover:border-blue-200"
                                                                    title="Editar nombre/descripcion"
                                                                >
                                                                    <i className="fas fa-pen"></i>
                                                                </button>
                                                                <button
                                                                    onClick={() => abrirModalBaja(activo)}
                                                                    className="text-rose-400 hover:text-rose-600 hover:bg-rose-50 p-2 rounded-lg transition-colors shadow-sm border border-transparent hover:border-rose-200"
                                                                    title="Dar de Baja el Activo"
                                                                >
                                                                    <i className="fas fa-arrow-down"></i> Baja
                                                                </button>
                                                            </div>
                                                        ) : (
                                                            <span className="text-[10px] font-black text-slate-400 bg-slate-200 px-2 py-1 rounded">RETIRADO</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {activosRegistrados.length === 0 && <tr><td colSpan="6" className="px-6 py-10 text-center text-slate-400 italic">No hay activos registrados en el sistema.</td></tr>}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* TAB: PROYECTOS */}
                    {tabActiva === 'PROYECTOS' && (
                        <GestionProyectosActivos onNotificar={mostrarNotificacion} />
                    )}
                </div>
            )}

            {/* MODAL: CONFIRMACIÓN DE BAJA */}
            {modalBajaAbierto && activoABajar && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-fade-in-up border border-slate-200">
                        <div className="p-6 border-b border-slate-100 flex justify-between items-center bg-rose-50">
                            <h3 className="text-xl font-black text-rose-800 flex items-center gap-2">
                                <i className="fas fa-exclamation-triangle"></i> Retirar Activo Fijo
                            </h3>
                            <button onClick={() => setModalBajaAbierto(false)} className="text-rose-400 hover:text-rose-600 transition-colors"><i className="fas fa-times"></i></button>
                        </div>
                        <div className="p-6">
                            <p className="text-sm text-slate-600 mb-4">
                                Estás a punto de dar de baja el activo <strong className="text-slate-800">{activoABajar.codigo} - {activoABajar.nombre}</strong>.
                            </p>
                            
                            <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-5 space-y-2">
                                <div className="flex justify-between text-xs">
                                    <span className="text-slate-500">Valor Adquisición:</span>
                                    <span className="font-bold text-slate-700">{formatCurrency(activoABajar.valor_adquisicion)}</span>
                                </div>
                                <div className="flex justify-between text-xs">
                                    <span className="text-slate-500">Depreciación Acumulada:</span>
                                    <span className="font-bold text-slate-700">-{formatCurrency(activoABajar.depreciacion_acumulada)}</span>
                                </div>
                                <div className="flex justify-between text-sm pt-2 border-t border-slate-200">
                                    <span className="font-bold text-slate-700">Pérdida al Castigar:</span>
                                    <span className="font-black text-rose-600">{formatCurrency(activoABajar.valor_adquisicion - activoABajar.depreciacion_acumulada)}</span>
                                </div>
                            </div>

                            <form onSubmit={confirmarBajaActivo}>
                                <div className="mb-6">
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-2">Motivo de la Baja (Opcional)</label>
                                    <input 
                                        type="text" 
                                        value={motivoBaja}
                                        onChange={(e) => setMotivoBaja(e.target.value)}
                                        placeholder="Ej: Obsolescencia, Destrucción, Robo..."
                                        className="w-full p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-rose-500 outline-none text-slate-700 font-medium" 
                                    />
                                </div>

                                <div className="flex gap-3">
                                    <button type="button" onClick={() => setModalBajaAbierto(false)} className="w-1/2 py-3 bg-white border border-slate-300 text-slate-600 font-bold rounded-xl hover:bg-slate-100 transition-all">Cancelar</button>
                                    <button type="submit" className="w-1/2 py-3 bg-rose-600 text-white font-bold rounded-xl hover:bg-rose-700 shadow-lg shadow-rose-200 transition-all">Confirmar Baja</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL EDITAR ACTIVO */}
            {modalEditarAbierto && activoEditando && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-fade-in">
                        <div className="bg-gradient-to-br from-blue-600 to-indigo-700 px-6 py-4 flex justify-between items-center">
                            <div>
                                <h3 className="font-black text-xl text-white">Editar Activo</h3>
                                <p className="text-blue-100 text-xs mt-0.5">{activoEditando.codigo} · {activoEditando.cuenta?.nombre || 'Sin cuenta'}</p>
                            </div>
                            <button onClick={cerrarModalEditar} className="text-white/80 hover:text-white text-2xl leading-none" aria-label="Cerrar">×</button>
                        </div>
                        <form onSubmit={confirmarEdicionActivo} className="p-6 space-y-4">
                            <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900">
                                <strong>Importante:</strong> solo podes editar nombre y descripcion.
                                Los valores contables (valor de adquisicion, vida util, depreciacion)
                                no se modifican porque ya tienen movimientos calculados.
                            </div>

                            <div>
                                <label className="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">
                                    Nombre del activo
                                </label>
                                <input
                                    type="text"
                                    value={formEditar.nombre}
                                    onChange={(e) => setFormEditar({ ...formEditar, nombre: e.target.value })}
                                    className="w-full border border-slate-300 rounded-xl p-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                    placeholder="Ej: Notebook Lenovo T14"
                                    maxLength={255}
                                    required
                                    autoFocus
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">
                                    Descripcion (opcional)
                                </label>
                                <textarea
                                    value={formEditar.descripcion}
                                    onChange={(e) => setFormEditar({ ...formEditar, descripcion: e.target.value })}
                                    className="w-full border border-slate-300 rounded-xl p-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 resize-none"
                                    placeholder="Detalles adicionales del activo"
                                    rows={3}
                                />
                            </div>

                            <div className="flex gap-4 pt-2">
                                <button
                                    type="button"
                                    onClick={cerrarModalEditar}
                                    disabled={guardandoEdicion}
                                    className="w-1/2 py-3 bg-white border border-slate-300 text-slate-600 font-bold rounded-xl hover:bg-slate-100 transition-all disabled:opacity-50"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={guardandoEdicion}
                                    className="w-1/2 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all disabled:opacity-50"
                                >
                                    {guardandoEdicion ? 'Guardando...' : 'Guardar Cambios'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionActivos;