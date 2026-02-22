import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import ModalGenerico from '../../../Componentes/ModalGenerico';
import Swal from 'sweetalert2';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const GestionActivos = () => {
    // --- ESTADOS DE DATOS ---
    const [activosRegistrados, setActivosRegistrados] = useState([]);
    const [activosPendientes, setActivosPendientes] = useState([]); 
    const [categoriasSII, setCategoriasSII] = useState([]);
    const [loading, setLoading] = useState(true);

    // --- ESTADOS DE UI ---
    const [tabActiva, setTabActiva] = useState('PENDIENTES'); 
    const [notificacion, setNotificacion] = useState({ show: false, title: '', message: '', type: 'info' });

    // --- ESTADOS DEL MODAL ---
    const [modalOpen, setModalOpen] = useState(false);
    const [activoSeleccionado, setActivoSeleccionado] = useState(null);
    const [modoActivacion, setModoActivacion] = useState('NUEVO'); 
    
    const [formActivacion, setFormActivacion] = useState({
        nombre_activo: '',
        categoria_sii_id: '',
        tipo_depreciacion: 'NORMAL',
        fecha_activacion: new Date().toISOString().split('T')[0]
    });

    const cargarDatos = async () => {
        setLoading(true);
        try {
            const resActivos = await api.get('/activos');
            if (resActivos.success) {
                setActivosRegistrados(resActivos.data);
                setCategoriasSII(resActivos.categorias);
            }

            const resPendientes = await api.get('/activos/pendientes');
            if (resPendientes.success) {
                setActivosPendientes(resPendientes.data);
            }
        } catch (error) {
            setNotificacion({ show: true, title: 'Error', message: 'No se pudieron cargar los datos.', type: 'danger' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { cargarDatos(); }, []);

    const abrirModalActivacion = (item, modo) => {
        setActivoSeleccionado(item);
        setModoActivacion(modo);
        
        setFormActivacion({
            nombre_activo: modo === 'NUEVO' ? `Equipo Fac. N° ${item.numero_factura}` : item.nombre_activo,
            categoria_sii_id: '',
            tipo_depreciacion: 'NORMAL',
            fecha_activacion: modo === 'NUEVO' ? item.fecha_emision : new Date().toISOString().split('T')[0]
        });
        
        setModalOpen(true);
    };

    const handleActivar = async () => {
        if (!formActivacion.categoria_sii_id) {
            return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar una categoría del SII.', confirmButtonColor: '#0f172a' });
        }

        try {
            if (modoActivacion === 'NUEVO') {
                const payload = {
                    factura_id: activoSeleccionado.factura_id,
                    cuenta_contable: activoSeleccionado.cuenta_contable,
                    monto_adquisicion: activoSeleccionado.monto_adquisicion,
                    fecha_adquisicion: activoSeleccionado.fecha_emision,
                    ...formActivacion
                };
                await api.post('/activos', payload); 
            } else {
                await api.post(`/activos/${activoSeleccionado.id}/activar`, formActivacion);
            }

            setNotificacion({ show: true, title: 'Éxito', message: 'El activo ha sido ingresado al cuadro de depreciación correctamente.', type: 'success' });
            setModalOpen(false);
            cargarDatos(); 
            setTabActiva('REGISTRADOS'); 
            
        } catch (error) {
            setNotificacion({ show: true, title: 'Error', message: 'Hubo un problema al procesar la activación.', type: 'danger' });
        }
    };

    const ejecutarMotorDepreciacion = () => {
        Swal.fire({
            title: '¿Ejecutar Depreciación?',
            text: "Se calculará la pérdida de valor mensual y se generará un comprobante de traspaso en contabilidad.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, procesar mes',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            buttonsStyling: false,
            customClass: {
                confirmButton: 'bg-emerald-600 text-white font-bold py-2.5 px-6 rounded-lg shadow-md hover:bg-emerald-700 ml-3 transition-colors',
                cancelButton: 'bg-slate-200 text-slate-800 border border-slate-300 font-bold py-2.5 px-6 rounded-lg hover:bg-slate-300 transition-colors'
            }
        }).then(async (result) => {
            if (result.isConfirmed) {
                setLoading(true);
                try {
                    const res = await api.post('/activos/depreciar-mes', {
                        fecha_cierre: new Date().toISOString().split('T')[0] // Hoy
                    });
                    
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Motor Ejecutado!',
                            text: `Se procesaron ${res.data.activos_procesados} activos por un total de ${formatCurrency(res.data.monto_total_depreciado)}. (Asiento N° ${res.data.asiento_codigo})`,
                            confirmButtonColor: '#059669'
                        });
                        cargarDatos();
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.response?.data?.mensaje || 'No se pudo ejecutar el motor contable.',
                        confirmButtonColor: '#0f172a'
                    });
                } finally {
                    setLoading(false);
                }
            }
        });
    };

    const getEstadoStyle = (estado) => {
        switch (estado?.toUpperCase()) {
            case 'ACTIVO': return 'bg-emerald-100 text-emerald-700 border-emerald-200';
            case 'DEPRECIADO': return 'bg-blue-100 text-blue-700 border-blue-200';
            default: return 'bg-amber-100 text-amber-700 border-amber-200';
        }
    };

    const categoriaSeleccionada = categoriasSII?.find(c => c.id.toString() === formActivacion.categoria_sii_id);
    const vidaUtil = formActivacion.tipo_depreciacion === 'NORMAL'
        ? categoriaSeleccionada?.vida_util_normal
        : categoriaSeleccionada?.vida_util_acelerada;

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 font-sans">
            <ModalGenerico
                isOpen={notificacion.show}
                onClose={() => setNotificacion({ ...notificacion, show: false })}
                title={notificacion.title}
                message={notificacion.message}
                type={notificacion.type}
            />

            {/* ENCABEZADO RESPONSIVO */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 md:mb-8 gap-4">
                <div>
                    <h2 className="text-2xl md:text-3xl font-bold text-slate-900">Gestión de Activos Fijos</h2>
                    <p className="text-slate-500 text-sm mt-1">Control de inventario, auditoría y activación de depreciación SII</p>
                </div>
            </div>

            {/* SISTEMA DE PESTAÑAS RESPONSIVO (Scroll horizontal en móviles) */}
            <div className="flex border-b border-slate-200 mb-6 overflow-x-auto whitespace-nowrap hide-scrollbar">
                <button
                    onClick={() => setTabActiva('PENDIENTES')}
                    className={`pb-4 px-4 md:px-6 font-bold text-sm transition-all relative ${tabActiva === 'PENDIENTES' ? 'text-blue-600' : 'text-slate-500 hover:text-slate-700'}`}
                >
                    <i className="fas fa-inbox mr-2"></i> Pendientes de Contabilidad
                    {activosPendientes.length > 0 && (
                        <span className="ml-2 bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full">{activosPendientes.length}</span>
                    )}
                    {tabActiva === 'PENDIENTES' && <div className="absolute bottom-0 left-0 w-full h-1 bg-blue-600 rounded-t-md"></div>}
                </button>
                <button
                    onClick={() => setTabActiva('REGISTRADOS')}
                    className={`pb-4 px-4 md:px-6 font-bold text-sm transition-all relative ${tabActiva === 'REGISTRADOS' ? 'text-blue-600' : 'text-slate-500 hover:text-slate-700'}`}
                >
                    <i className="fas fa-boxes mr-2"></i> Activos Registrados
                    {tabActiva === 'REGISTRADOS' && <div className="absolute bottom-0 left-0 w-full h-1 bg-blue-600 rounded-t-md"></div>}
                </button>
            </div>

            <div className="bg-white shadow-sm rounded-xl overflow-hidden border border-slate-200 min-h-[400px]">
                
                {/* VISTA 1: PENDIENTES DE CONTABILIDAD */}
                {tabActiva === 'PENDIENTES' && (
                    <div className="overflow-x-auto w-full">
                        <table className="min-w-[800px] w-full text-left">
                            <thead className="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider">Documento Origen</th>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider">Imputación Contable</th>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-right">Monto Neto (Adquisición)</th>
                                    <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-center">Acción Requerida</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {loading ? (
                                    <tr><td colSpan="4" className="p-10 text-center text-slate-400 font-medium"><i className="fas fa-circle-notch fa-spin mr-2"></i> Escaneando libros contables...</td></tr>
                                ) : activosPendientes.length === 0 ? (
                                    <tr>
                                        <td colSpan="4" className="p-16 text-center text-slate-500">
                                            <i className="fas fa-check-circle text-4xl text-emerald-400 mb-3 block"></i>
                                            <p className="text-lg font-bold text-slate-700">Contabilidad al día</p>
                                            <p className="text-sm">No hay compras recientes registradas como activos fijos.</p>
                                        </td>
                                    </tr>
                                ) : (
                                    activosPendientes.map((item, i) => (
                                        <tr key={i} className="hover:bg-blue-50/30 transition-colors">
                                            <td className="p-4">
                                                <p className="font-bold text-slate-900">Fac. N° {item.numero_factura}</p>
                                                <p className="text-xs text-slate-500 font-mono">{item.fecha_emision} | {item.proveedor}</p>
                                            </td>
                                            <td className="p-4">
                                                <span className="bg-slate-100 text-slate-700 text-xs font-bold px-2 py-1 rounded border border-slate-200 inline-block">
                                                    {item.cuenta_contable} - {item.nombre_cuenta}
                                                </span>
                                            </td>
                                            <td className="p-4 text-slate-900 font-black text-right text-lg">
                                                {formatCurrency(item.monto_adquisicion)}
                                            </td>
                                            <td className="p-4 text-center">
                                                <button 
                                                    onClick={() => abrirModalActivacion(item, 'NUEVO')}
                                                    className="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2 rounded-lg text-xs shadow transition-colors whitespace-nowrap w-full md:w-auto"
                                                >
                                                    <i className="fas fa-plus mr-1"></i> Crear Ficha
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* VISTA 2: ACTIVOS REGISTRADOS */}
                {tabActiva === 'REGISTRADOS' && (
                    <div className="w-full">
                        {/* --- PANEL DEL MOTOR DE DEPRECIACIÓN --- */}
                        <div className="bg-slate-50 border-b border-slate-200 p-4 md:p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <h3 className="font-bold text-slate-800 text-lg"><i className="fas fa-cogs text-emerald-600 mr-2"></i> Motor de Cierre Mensual</h3>
                                <p className="text-xs md:text-sm text-slate-500 mt-1">Genera el asiento contable de depreciación tributaria para todos los activos vigentes.</p>
                            </div>
                            <button
                                onClick={ejecutarMotorDepreciacion}
                                disabled={loading || activosRegistrados.length === 0}
                                className="w-full md:w-auto px-6 py-3 bg-slate-900 text-white text-sm font-bold rounded-lg shadow-md hover:bg-slate-800 disabled:opacity-50 transition-all flex items-center justify-center whitespace-nowrap"
                            >
                                {loading ? <i className="fas fa-circle-notch fa-spin mr-2"></i> : <i className="fas fa-bolt mr-2 text-yellow-400"></i>}
                                Ejecutar Motor
                            </button>
                        </div>

                        <div className="overflow-x-auto w-full">
                            <table className="min-w-[900px] w-full text-left">
                                <thead className="bg-white border-b border-slate-100">
                                    <tr>
                                        <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider">Activo / Cuenta</th>
                                        <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-center">Adquisición</th>
                                        <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-right">Valor Inicial</th>
                                        <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-center">Estado</th>
                                        <th className="p-4 font-bold text-slate-500 uppercase text-xs tracking-wider text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {loading ? (
                                        <tr><td colSpan="5" className="p-10 text-center text-slate-400 font-medium">Cargando activos...</td></tr>
                                    ) : activosRegistrados.length === 0 ? (
                                        <tr><td colSpan="5" className="p-10 text-center text-slate-400 font-medium">No hay activos registrados en la base de datos.</td></tr>
                                    ) : (
                                        activosRegistrados.map(a => (
                                            <tr key={a.id} className="hover:bg-slate-50/50 transition-colors">
                                                <td className="p-4">
                                                    <p className="font-bold text-slate-900">{a.nombre_activo}</p>
                                                    <p className="text-xs text-slate-500 font-mono">{a.cuenta_codigo} - {a.cuenta_nombre}</p>
                                                </td>
                                                <td className="p-4 text-slate-600 text-sm text-center">{a.fecha_adquisicion}</td>
                                                <td className="p-4 text-slate-900 font-black text-right">
                                                    {formatCurrency(a.monto_adquisicion)}
                                                </td>
                                                <td className="p-4 text-center">
                                                    <span className={`px-3 py-1 rounded-full text-[10px] font-black border ${getEstadoStyle(a.estado)} whitespace-nowrap`}>
                                                        {a.estado}
                                                    </span>
                                                </td>
                                                <td className="p-4 text-right">
                                                    {a.estado === 'PENDIENTE' ? (
                                                        <button
                                                            onClick={() => abrirModalActivacion(a, 'EXISTENTE')}
                                                            className="bg-emerald-600 text-white px-4 py-1.5 text-xs rounded-lg font-bold shadow hover:bg-emerald-700 transition-all whitespace-nowrap"
                                                        >
                                                            Activar Depreciación
                                                        </button>
                                                    ) : (
                                                        <div className="text-xs text-slate-500 flex flex-col items-end">
                                                            <span className="font-bold text-slate-700">{a.tipo_depreciacion}</span>
                                                            <span>({a.tipo_depreciacion === 'NORMAL' ? a.vida_util_normal : a.vida_util_acelerada} años)</span>
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {/* MODAL UNIFICADO DE ACTIVACIÓN / CONFIGURACIÓN RESPONSIVO */}
            {modalOpen && (
                <div className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in overflow-y-auto">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200 my-8">
                        <div className="bg-slate-900 px-5 py-4 md:px-6 md:py-5 flex justify-between items-center text-white">
                            <div>
                                <h2 className="text-lg md:text-xl font-bold">Ficha Técnica del Activo</h2>
                                <p className="text-[10px] md:text-xs text-slate-400 mt-1">Configuración de depreciación tributaria</p>
                            </div>
                            <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-white transition-colors p-2">
                                <i className="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <div className="p-5 md:p-8 space-y-4 md:space-y-5">
                            {modoActivacion === 'NUEVO' && (
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Nombre Comercial del Bien</label>
                                    <input 
                                        type="text" 
                                        className="w-full border-2 border-slate-200 rounded-xl p-3 text-sm md:text-base outline-none focus:border-blue-500 font-bold text-slate-800 transition-all"
                                        placeholder="Ej: Notebook Dell Latitude 5000"
                                        value={formActivacion.nombre_activo}
                                        onChange={e => setFormActivacion({...formActivacion, nombre_activo: e.target.value})}
                                    />
                                    <p className="text-[10px] text-slate-400 mt-1 ml-1">* Este nombre aparecerá en el libro de activos fijos.</p>
                                </div>
                            )}

                            <div>
                                <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Categoría SII (Tabla de Vida Útil)</label>
                                <select
                                    className="w-full border-2 border-slate-200 rounded-xl p-3 text-sm outline-none focus:border-blue-500 font-medium transition-all"
                                    value={formActivacion.categoria_sii_id}
                                    onChange={e => setFormActivacion({ ...formActivacion, categoria_sii_id: e.target.value })}
                                >
                                    <option value="">Seleccione categoría tributaria...</option>
                                    {categoriasSII?.map(c => (
                                        <option key={c.id} value={c.id}>{c.nombre}</option>
                                    ))}
                                </select>
                            </div>

                            {/* GRID RESPONSIVO PARA MÓVILES (1 columna en móvil, 2 en PC) */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Tipo Depreciación</label>
                                    <select
                                        className="w-full border-2 border-slate-200 rounded-xl p-3 text-sm outline-none focus:border-blue-500 font-medium transition-all"
                                        value={formActivacion.tipo_depreciacion}
                                        onChange={e => setFormActivacion({ ...formActivacion, tipo_depreciacion: e.target.value })}
                                    >
                                        <option value="NORMAL">Normal</option>
                                        <option value="ACELERADA">Acelerada</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Inicio de Uso (Activación)</label>
                                    <input
                                        type="date"
                                        className="w-full border-2 border-slate-200 rounded-xl p-3 text-sm outline-none focus:border-blue-500 font-medium transition-all"
                                        value={formActivacion.fecha_activacion}
                                        onChange={e => setFormActivacion({ ...formActivacion, fecha_activacion: e.target.value })}
                                    />
                                </div>
                            </div>

                            {vidaUtil && (
                                <div className="bg-blue-50 border border-blue-200 p-4 rounded-xl flex items-center gap-3 md:gap-4 mt-2">
                                    <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 flex-shrink-0">
                                        <i className="fas fa-chart-line text-lg"></i>
                                    </div>
                                    <div className="text-xs md:text-sm text-blue-900 leading-tight">
                                        El activo se depreciará contablemente en <b className="font-black text-blue-700">{vidaUtil} años</b> ({vidaUtil * 12} meses) 
                                        usando el método <b className="font-black text-blue-700">{formActivacion.tipo_depreciacion}</b>.
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-col-reverse md:flex-row justify-end gap-3 px-5 py-4 md:px-6 md:py-5 bg-slate-50 border-t border-slate-200">
                            <button onClick={() => setModalOpen(false)} className="w-full md:w-auto px-6 py-2.5 text-slate-600 border border-slate-300 md:border-transparent bg-white md:bg-transparent hover:bg-slate-200 rounded-lg text-sm font-bold transition-all text-center">
                                Cancelar
                            </button>
                            <button
                                onClick={handleActivar}
                                className="w-full md:w-auto px-8 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-bold shadow-lg hover:bg-emerald-700 hover:shadow-emerald-600/30 transition-all flex justify-center items-center"
                            >
                                <i className="fas fa-check mr-2"></i> Iniciar Depreciación
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionActivos;