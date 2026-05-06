import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const VisorProyectoActivo = ({ proyectoId, onVolver, onNotificar }) => {
    const [proyecto, setProyecto] = useState(null);
    const [loading, setLoading] = useState(true);

    // FIX: Guardamos los tres grupos de cuentas por separado tal como vienen del Backend
    const [cuentasActivo, setCuentasActivo] = useState([]);
    const [cuentasDepreciacion, setCuentasDepreciacion] = useState([]);
    const [cuentasGasto, setCuentasGasto] = useState([]);

    const [modalFacturasAbierto, setModalFacturasAbierto] = useState(false);
    const [facturasDisponibles, setFacturasDisponibles] = useState([]);
    const [facturaSeleccionadaId, setFacturaSeleccionadaId] = useState('');
    const [montoImputar, setMontoImputar] = useState('');

    const [modalActivacionAbierto, setModalActivacionAbierto] = useState(false);
    const [editandoCuenta, setEditandoCuenta] = useState(false);

    // 1. CARGA DE DATOS
    const cargarProyecto = async () => {
        setLoading(true);
        try {
            // Cargamos el análisis y los parámetros contables en paralelo
            const [resProj, resParams] = await Promise.all([
                api.get(`/activos/proyectos/${proyectoId}/analisis`),
                api.get('/activos/parametros')
            ]);

            if (resProj.success) setProyecto(resProj.data);

            // FIX: Asignamos cada arreglo de la respuesta a su estado correspondiente
            if (resParams.success) {
                setCuentasActivo(resParams.data.cuentas_activo || []);
                setCuentasDepreciacion(resParams.data.cuentas_depreciacion || []);
                setCuentasGasto(resParams.data.cuentas_gasto || []);
            }

        } catch (error) {
            onNotificar('error', 'No se pudo cargar la información del proyecto.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarProyecto();
    }, [proyectoId]);

    // 2. FUNCIONES DE GESTIÓN
    const handleCambiarCuenta = async (nuevaCuentaId, campo) => {
        try {
            // Preparamos el payload dinámico según el selector que se modificó
            const payload = {};
            payload[campo] = nuevaCuentaId;

            const res = await api.put(`/activos/proyectos/${proyectoId}`, payload);

            if (res.success) {
                setProyecto({ ...proyecto, [campo]: nuevaCuentaId ? parseInt(nuevaCuentaId) : null });
                onNotificar('success', 'Cuenta contable actualizada correctamente.');
            }
        } catch (error) {
            alert("Error al actualizar la cuenta: " + (error.response?.data?.message || error.message));
        }
    };

    const abrirModalImputar = async () => {
        try {
            const res = await api.get('/activos/proyectos/facturas-disponibles');
            if (res.success) {
                setFacturasDisponibles(res.data);
                if (res.data.length === 0) {
                    onNotificar('error', 'No hay facturas contables disponibles.');
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
        if (e) e.preventDefault();
        if (!facturaSeleccionadaId || !montoImputar) return;

        try {
            const res = await api.post(`/activos/proyectos/${proyectoId}/facturas`, {
                factura_id: facturaSeleccionadaId,
                monto: montoImputar
            });

            if (res.success) {
                setModalFacturasAbierto(false);
                setFacturaSeleccionadaId('');
                setMontoImputar('');
                cargarProyecto();
                onNotificar('success', 'Factura imputada correctamente.');
            }
        } catch (error) {
            const msg = error.response?.data?.message || "Error al imputar factura.";
            alert("⚠️ " + msg);
        }
    };

    const handleAbrirModalActivacion = () => {
        setModalActivacionAbierto(true);
    };

    const confirmarActivacion = async () => {
        try {
            const res = await api.put(`/activos/proyectos/${proyectoId}/activar`);
            if (res.success) {
                onNotificar('success', 'Proyecto Activado y Capitalizado Exitosamente.');
                setModalActivacionAbierto(false);
                onVolver(); // Volvemos a la lista principal
            }
        } catch (error) {
            const msg = error.response?.data?.message || 'Error al activar el proyecto.';
            alert("❌ " + msg);
        }
    };

    if (loading || !proyecto) return <div className="text-center py-12"><i className="fas fa-spinner fa-spin text-3xl text-slate-400"></i></div>;

    const valorOriginal = Number(proyecto.valor_total_original) || 0;
    const depreciacion = Number(proyecto.depreciacion_acumulada) || 0;
    const valorLibro = valorOriginal - depreciacion;

    // FIX: Constantes para evaluar las 3 cuentas usando sus arreglos específicos
    const cuentaAsignada = cuentasActivo.find(t => t.id === proyecto.tipo_activo_id);
    const cuentaDepreAsignada = cuentasDepreciacion.find(t => t.id === proyecto.cuenta_depreciacion_id);
    const cuentaGastoAsignada = cuentasGasto.find(t => t.id === proyecto.cuenta_gasto_id);
    const configuracionCompleta = proyecto.tipo_activo_id && proyecto.cuenta_depreciacion_id && proyecto.cuenta_gasto_id;

    return (
        <div className="space-y-6 animate-fade-in pb-10">
            {/* CABECERA */}
            <div className="flex items-center gap-4 border-b border-slate-200 pb-4">
                <button onClick={onVolver} className="h-10 w-10 bg-slate-200 hover:bg-slate-300 rounded-full flex items-center justify-center transition-colors text-slate-600">
                    <i className="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h2 className="text-2xl font-black text-slate-800 tracking-tight">{proyecto.nombre}</h2>
                    <span className={`px-3 py-1 rounded-full text-xs font-bold inline-block mt-1 ${proyecto.estado === 'EN_CONSTRUCCION' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>
                        {proyecto.estado?.replace('_', ' ')}
                    </span>
                </div>
            </div>

            {/* DASHBOARD Y CONFIGURACIÓN */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-indigo-500">
                    <p className="text-xs font-bold text-slate-500 uppercase">Inversión Neta Acumulada</p>
                    <h3 className="text-2xl font-black text-slate-800 mt-1">{formatCurrency(valorOriginal)}</h3>
                </div>

                {/* BLOQUE DE LAS 3 CUENTAS */}
                <div className={`lg:col-span-2 p-5 rounded-xl border shadow-sm flex flex-col justify-center ${!configuracionCompleta ? 'bg-rose-50 border-rose-200 border-l-4 border-l-rose-500' : 'bg-white border-slate-200 border-l-4 border-l-slate-400'}`}>
                    <div className="flex justify-between items-start">
                        <div className="w-full mr-4">
                            <p className="text-xs font-bold text-slate-500 uppercase mb-2">Clasificación Contable</p>
                            {editandoCuenta ? (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2 animate-fade-in">
                                    <div>
                                        <p className="text-[10px] font-bold text-slate-500 uppercase">1. Cuenta de Activo (Bien)</p>
                                        <select
                                            className="w-full mt-1 p-2 border rounded text-xs font-bold text-slate-700 bg-white"
                                            value={proyecto.tipo_activo_id || ''}
                                            onChange={(e) => handleCambiarCuenta(e.target.value, 'tipo_activo_id')}
                                        >
                                            <option value="">Seleccionar...</option>
                                            {/* FIX: Mapeamos cuentasActivo */}
                                            {cuentasActivo.map(t => <option key={t.id} value={t.id}>{t.codigo} - {t.nombre}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-bold text-slate-500 uppercase">2. Cuenta Deprec. Acumulada</p>
                                        <select
                                            className="w-full mt-1 p-2 border rounded text-xs font-bold text-slate-700 bg-white"
                                            value={proyecto.cuenta_depreciacion_id || ''}
                                            onChange={(e) => handleCambiarCuenta(e.target.value, 'cuenta_depreciacion_id')}
                                        >
                                            <option value="">Seleccionar...</option>
                                            {/* FIX: Mapeamos cuentasDepreciacion */}
                                            {cuentasDepreciacion.map(t => <option key={t.id} value={t.id}>{t.codigo} - {t.nombre}</option>)}
                                        </select>
                                    </div>
                                    <div className="md:col-span-2">
                                        <p className="text-[10px] font-bold text-slate-500 uppercase">3. Cuenta Gasto (Pérdida por Depreciación)</p>
                                        <select
                                            className="w-full mt-1 p-2 border rounded text-xs font-bold text-slate-700 bg-white"
                                            value={proyecto.cuenta_gasto_id || ''}
                                            onChange={(e) => handleCambiarCuenta(e.target.value, 'cuenta_gasto_id')}
                                        >
                                            <option value="">Seleccionar...</option>
                                            {/* FIX: Mapeamos cuentasGasto */}
                                            {cuentasGasto.map(t => <option key={t.id} value={t.id}>{t.codigo} - {t.nombre}</option>)}
                                        </select>
                                    </div>
                                </div>
                            ) : (
                                <div className="mt-2 space-y-1">
                                    <p className="text-sm font-bold text-slate-700 flex items-center">
                                        <i className="fas fa-cube w-5 text-slate-400"></i> Activo: {cuentaAsignada ? `${cuentaAsignada.codigo} - ${cuentaAsignada.nombre}` : <span className="text-rose-500 ml-1">Falta asignar</span>}
                                    </p>
                                    <p className="text-sm font-bold text-slate-700 flex items-center">
                                        <i className="fas fa-chart-line w-5 text-slate-400"></i> Deprec: {cuentaDepreAsignada ? `${cuentaDepreAsignada.codigo} - ${cuentaDepreAsignada.nombre}` : <span className="text-rose-500 ml-1">Falta asignar</span>}
                                    </p>
                                    <p className="text-sm font-bold text-slate-700 flex items-center">
                                        <i className="fas fa-receipt w-5 text-slate-400"></i> Gasto: {cuentaGastoAsignada ? `${cuentaGastoAsignada.codigo} - ${cuentaGastoAsignada.nombre}` : <span className="text-rose-500 ml-1">Falta asignar</span>}
                                    </p>
                                </div>
                            )}
                        </div>
                        <button
                            onClick={() => setEditandoCuenta(!editandoCuenta)}
                            className="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1.5 rounded-lg font-bold transition-all border border-slate-200 whitespace-nowrap mt-2"
                        >
                            {editandoCuenta ? 'Cerrar Edición' : 'Editar Cuentas'}
                        </button>
                    </div>
                    {!configuracionCompleta && !editandoCuenta && (
                        <p className="text-[10px] text-rose-500 font-bold mt-3 uppercase tracking-tighter">
                            ⚠️ Debes asignar las 3 cuentas contables para poder capitalizar este proyecto.
                        </p>
                    )}
                </div>
            </div>

            {/* ACCIONES DE FASE */}
            {proyecto.estado === 'EN_CONSTRUCCION' && (
                <div className="bg-indigo-50 border border-indigo-100 p-5 rounded-xl flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h4 className="font-bold text-indigo-900">Gestión de Costos</h4>
                        <p className="text-sm text-indigo-700">Añada facturas al costo. Una vez completado, capitalice el activo.</p>
                    </div>
                    <div className="flex gap-3 w-full md:w-auto">
                        <button onClick={abrirModalImputar} className="flex-1 md:flex-none px-4 py-2 bg-white text-indigo-700 font-bold border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors shadow-sm">
                            <i className="fas fa-file-invoice mr-2"></i>Imputar Factura
                        </button>
                        <button
                            onClick={handleAbrirModalActivacion}
                            disabled={!configuracionCompleta || valorOriginal === 0}
                            className="flex-1 md:flex-none px-4 py-2 bg-indigo-600 disabled:bg-slate-300 text-white font-bold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm"
                        >
                            <i className="fas fa-check-circle mr-2"></i>Capitalizar Activo
                        </button>
                    </div>
                </div>
            )}

            {/* TABLA DE COSTOS */}
            <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div className="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h3 className="font-bold text-slate-700 text-sm uppercase tracking-wider">Detalle de Inversión (Netos)</h3>
                </div>
                <table className="w-full text-left border-collapse">
                    <thead className="bg-white border-b border-slate-200 text-[10px] uppercase text-slate-400">
                        <tr>
                            <th className="px-6 py-3">N° Documento</th>
                            <th className="px-6 py-3">Proveedor</th>
                            <th className="px-6 py-3 text-right">Monto Neto</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 text-sm">
                        {proyecto.facturas && proyecto.facturas.length > 0 ? (
                            proyecto.facturas.map((f, i) => (
                                <tr key={i} className="hover:bg-slate-50 transition-colors">
                                    <td className="px-6 py-4 font-bold text-indigo-600">{f.numero}</td>
                                    <td className="px-6 py-4 text-slate-600">{f.proveedor}</td>
                                    <td className="px-6 py-4 font-black text-slate-800 text-right">{formatCurrency(f.monto)}</td>
                                </tr>
                            ))
                        ) : (
                            <tr><td colSpan="3" className="px-6 py-10 text-center text-slate-400 italic">No hay facturas vinculadas.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* MODALES */}
            {modalFacturasAbierto && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in-up border border-slate-200">
                        <div className="p-6 border-b flex justify-between items-center">
                            <h3 className="text-xl font-black text-slate-800">Vincular Factura Neto</h3>
                            <button onClick={() => setModalFacturasAbierto(false)} className="text-slate-400 hover:text-rose-500 transition-colors"><i className="fas fa-times"></i></button>
                        </div>
                        <div className="p-6 bg-slate-50">
                            <form onSubmit={handleImputarFactura} className="space-y-5">
                                <div>
                                    <label className="block text-xs font-black text-slate-500 uppercase mb-2">Factura Disponible</label>
                                    <select required value={facturaSeleccionadaId} onChange={handleSelectFactura} className="w-full p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700 bg-white">
                                        <option value="">Seleccione...</option>
                                        {facturasDisponibles.map(f => (
                                            <option key={f.factura_id} value={f.factura_id}>Doc. {f.numero_factura} - {formatCurrency(f.monto)}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-black text-slate-500 uppercase mb-2">Monto a Capitalizar</label>
                                    <input type="number" required value={montoImputar} onChange={(e) => setMontoImputar(e.target.value)} className="w-full p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-black text-xl text-indigo-700" />
                                    <p className="text-[10px] text-slate-400 mt-2">* El sistema rechazará montos distintos al Neto Real de la factura.</p>
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <button type="button" onClick={() => setModalFacturasAbierto(false)} className="w-1/2 py-3 bg-white border border-slate-300 text-slate-600 font-bold rounded-xl hover:bg-slate-100 transition-all">Cancelar</button>
                                    <button type="submit" className="w-1/2 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Imputar Costo</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {modalActivacionAbierto && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-fade-in-up border border-slate-200">
                        <div className="p-8 text-center">
                            <div className="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 shadow-inner">
                                <i className="fas fa-check-circle"></i>
                            </div>
                            <h3 className="text-2xl font-black text-slate-800 mb-2">¿Confirmar Capitalización?</h3>
                            <p className="text-slate-500 text-sm mb-8 leading-relaxed">
                                El activo se registrará con un valor de <b>{formatCurrency(valorOriginal)}</b> usando la cuenta <b>{cuentaAsignada?.nombre}</b>. Esta acción es irreversible.
                            </p>
                            <div className="flex gap-4">
                                <button onClick={() => setModalActivacionAbierto(false)} className="w-1/2 py-3 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-all uppercase text-xs tracking-widest">Cancelar</button>
                                <button onClick={confirmarActivacion} className="w-1/2 py-3 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 shadow-lg shadow-emerald-200 transition-all uppercase text-xs tracking-widest">Confirmar</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default VisorProyectoActivo;