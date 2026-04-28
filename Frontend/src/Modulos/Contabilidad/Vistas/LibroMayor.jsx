import React, { useState, useEffect, useRef } from 'react';
import { api } from '../../../Configuracion/api';
import * as XLSX from "@e965/xlsx";
import ModalGenerico from '../../../Componentes/ModalGenerico';

const formatMoney = (amount) => {
    if (!amount || parseFloat(amount) === 0) return '';
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
        const soloFecha = dateString.split('T')[0];
        const [year, month, day] = soloFecha.split('-');
        return `${day}-${month}-${year}`;
    } catch (error) {
        return dateString;
    }
};

const LibroMayor = () => {
    const [activeTab, setActiveTab] = useState('diario');
    const [asientos, setAsientos] = useState([]);
    const [planCuentas, setPlanCuentas] = useState([]);
    const [loading, setLoading] = useState(false);
    const cuentaGuardada = localStorage.getItem('ultimaCuentaLibroDiario') || '';

    // Estado filtros
    const [filtros, setFiltros] = useState({
        desde: new Date().toISOString().slice(0, 7) + '-01',
        hasta: new Date().toISOString().split('T')[0],
        cuenta: cuentaGuardada
    });

    // Estados buscador inteligente
    const [busquedaCuenta, setBusquedaCuenta] = useState(cuentaGuardada);
    const [sugerencias, setSugerencias] = useState([]);
    const [mostrarLista, setMostrarLista] = useState(false);
    const wrapperRef = useRef(null);

    // Estados Menú Contextual y Visor
    const [contextMenu, setContextMenu] = useState({ visible: false, x: 0, y: 0, asientoId: null });
    const [asientoSeleccionado, setAsientoSeleccionado] = useState(null);

    // Estado para Notificaciones (Modales)
    const [notificacion, setNotificacion] = useState({
        show: false,
        title: '',
        message: '',
        type: 'info'
    });

    useEffect(() => {
        cargarPlanCuentas();

        function handleClickOutside(event) {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                setMostrarLista(false);
            }
            setContextMenu({ ...contextMenu, visible: false });
        }
        document.addEventListener("click", handleClickOutside);
        return () => document.removeEventListener("click", handleClickOutside);
    }, []);

    useEffect(() => {
        if (activeTab === 'diario') cargarLibroDiario();
    }, [activeTab]);

    useEffect(() => {
        if (busquedaCuenta.trim() === '') {
            setSugerencias([]);
            return;
        }
        const termino = busquedaCuenta.toLowerCase();
        const filtradas = planCuentas.filter(cta =>
            cta.codigo.toString().includes(termino) ||
            cta.nombre.toLowerCase().includes(termino)
        );
        setSugerencias(filtradas.slice(0, 10));
    }, [busquedaCuenta, planCuentas]);

    const cargarPlanCuentas = async () => {
        try {
            const res = await api.get('/contabilidad/plan-cuentas');
            if (res.success) setPlanCuentas(res.data);
        } catch (error) {
            console.error("Error plan cuentas", error);
        }
    };

    const cargarLibroDiario = async () => {
        setLoading(true);
        try {
            const cuentaAEnviar = filtros.cuenta || (busquedaCuenta.match(/^\d+/) ? busquedaCuenta : '');
            const params = { desde: filtros.desde, hasta: filtros.hasta, cuenta: cuentaAEnviar };
            const query = new URLSearchParams(params).toString();
            const res = await api.get(`/contabilidad/libro-diario?${query}`);

            if (res.success) {
                let datosAplanados = [];

                if (res.data && res.data.movimientos) {
                    const ctaSplit = (res.data.cuenta || '').split(' - ');
                    const ctaCodigo = ctaSplit[0] || cuentaAEnviar;
                    const ctaNombre = ctaSplit.slice(1).join(' - ') || '';

                    datosAplanados = res.data.movimientos.map(mov => ({
                        asiento_id: mov.comprobante,
                        codigo_unico: mov.comprobante,
                        fecha: mov.fecha,
                        cuenta_codigo: ctaCodigo,
                        cuenta_nombre: ctaNombre,
                        glosa: mov.glosa,
                        debe: mov.debe,
                        haber: mov.haber
                    }));
                }
                else if (Array.isArray(res.data)) {
                    res.data.forEach(asiento => {
                        if (asiento.detalles) {
                            asiento.detalles.forEach(det => {
                                datosAplanados.push({
                                    asiento_id: asiento.id,
                                    codigo_unico: asiento.numero_comprobante || asiento.id,
                                    fecha: asiento.fecha,
                                    cuenta_codigo: det.cuenta_contable || det.cuenta?.codigo,
                                    cuenta_nombre: det.cuenta?.nombre || '',
                                    glosa: asiento.glosa,
                                    debe: det.debe,
                                    haber: det.haber
                                });
                            });
                        } else {
                            datosAplanados.push(asiento);
                        }
                    });
                }

                setAsientos(datosAplanados);
            } else {
                setAsientos([]);
                setNotificacion({
                    show: true,
                    title: 'Error',
                    message: res.message || 'No se pudieron cargar los datos',
                    type: 'danger'
                });
            }
        } catch (error) {
            console.error("Error cargando diario", error);
            setAsientos([]);
        } finally {
            setLoading(false);
        }
    };

    const handleContextMenu = (e, asientoId) => {
        e.preventDefault();
        setContextMenu({
            visible: true,
            x: e.clientX,
            y: e.clientY,
            asientoId: asientoId
        });
    };

    const abrirComprobante = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/contabilidad/asientos/${contextMenu.asientoId}`);
            if (res.success) {
                setAsientoSeleccionado(res.data);
                setActiveTab('visor');
            }
        } catch (error) {
            setNotificacion({
                show: true,
                title: 'Error',
                message: 'No se pudo cargar el detalle del asiento.',
                type: 'danger'
            });
        } finally {
            setLoading(false);
        }
    };

    const exportarExcel = () => {
        if (asientos.length === 0) {
            setNotificacion({
                show: true,
                title: 'Sin datos',
                message: 'No hay movimientos para exportar en el rango seleccionado.',
                type: 'warning'
            });
            return;
        }

        const datosExcel = asientos.map(fila => ({
            "Fecha": formatDate(fila.fecha),
            "Comprobante": fila.codigo_unico || fila.asiento_id,
            "Código Cuenta": fila.cuenta_codigo,
            "Nombre Cuenta": fila.cuenta_nombre,
            "Glosa": fila.glosa,
            "Debe": parseFloat(fila.debe) || 0,
            "Haber": parseFloat(fila.haber) || 0
        }));

        const worksheet = XLSX.utils.json_to_sheet(datosExcel);
        worksheet['!cols'] = [{ wch: 12 }, { wch: 15 }, { wch: 15 }, { wch: 30 }, { wch: 40 }, { wch: 15 }, { wch: 15 }];
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Libro Diario");

        const nombreCuenta = filtros.cuenta ? `_Cta_${filtros.cuenta}` : '_General';
        XLSX.writeFile(workbook, `Libro_Diario${nombreCuenta}_${filtros.desde}_${filtros.hasta}.xlsx`);
    };

    return (
        <div className="max-w-7xl mx-auto p-6 font-sans text-slate-800 relative">

            {/* COMPONENTE MODAL GENÉRICO */}
            <ModalGenerico
                isOpen={notificacion.show}
                onClose={() => setNotificacion({ ...notificacion, show: false })}
                title={notificacion.title}
                message={notificacion.message}
                type={notificacion.type}
            />

            {/* MENÚ CONTEXTUAL FLOTANTE */}
            {contextMenu.visible && (
                <div
                    className="fixed bg-white shadow-xl border border-slate-200 rounded-lg py-1 z-50 w-56 animate-fade-in"
                    style={{ top: contextMenu.y, left: contextMenu.x }}
                >
                    <button
                        onClick={abrirComprobante}
                        className="w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-3 transition-colors"
                    >
                        <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        Abrir Comprobante
                    </button>
                </div>
            )}

            {/* ENCABEZADO */}
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-3xl font-bold text-slate-900">Libros Contables</h1>
                <div className="flex bg-white rounded-lg shadow-sm border p-1">
                    <button onClick={() => setActiveTab('diario')} className={`px-4 py-1.5 text-sm font-bold rounded-md transition ${activeTab === 'diario' ? 'bg-slate-800 text-white' : 'hover:bg-slate-50 text-slate-600'}`}>Libro Diario / Mayor</button>
                    {activeTab === 'visor' && (
                        <button className="px-4 py-1.5 text-sm font-bold rounded-md bg-emerald-600 text-white flex items-center gap-2 animate-pulse">
                            <span className="w-2 h-2 bg-white rounded-full"></span>
                            Visor Comprobante
                        </button>
                    )}
                </div>
            </div>

            {/* FILTROS (Solo visible en Libro Diario) */}
            {activeTab === 'diario' && (
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 flex flex-wrap gap-4 items-end z-20 relative">
                    <div>
                        <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Desde</label>
                        <input type="date" className="border border-slate-300 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none" value={filtros.desde} onChange={e => setFiltros({ ...filtros, desde: e.target.value })} />
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Hasta</label>
                        <input type="date" className="border border-slate-300 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none" value={filtros.hasta} onChange={e => setFiltros({ ...filtros, hasta: e.target.value })} />
                    </div>

                    <div className="flex-1 min-w-[300px] relative" ref={wrapperRef}>
                        <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Buscar Cuenta (Déjalo vacío para ver todo)</label>
                        <div className="relative">
                            <input
                                type="text"
                                placeholder="Escribe 'Caja' o '1101'..."
                                className="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 font-mono"
                                value={busquedaCuenta}
                                onChange={(e) => {
                                    setBusquedaCuenta(e.target.value);
                                    setMostrarLista(true);
                                    if (e.target.value === '') {
                                        setFiltros({ ...filtros, cuenta: '' });
                                        localStorage.removeItem('ultimaCuentaLibroDiario'); // Limpia memoria
                                    }
                                }}
                                onFocus={() => setMostrarLista(true)}
                            />
                            {busquedaCuenta && (
                                <button
                                    onClick={() => {
                                        setBusquedaCuenta('');
                                        setFiltros({ ...filtros, cuenta: '' });
                                        localStorage.removeItem('ultimaCuentaLibroDiario'); // Limpia memoria
                                    }}
                                    className="absolute right-2 top-2 text-slate-400 hover:text-slate-600"
                                >
                                    ✕
                                </button>
                            )}
                        </div>
                        {mostrarLista && sugerencias.length > 0 && (
                            <div className="absolute top-full left-0 w-full bg-white border border-slate-200 rounded-lg shadow-xl mt-1 max-h-60 overflow-y-auto z-50">
                                <ul className="py-1 text-sm text-slate-700">
                                    {sugerencias.map(cta => (
                                        <li
                                            key={cta.id}
                                            onClick={() => {
                                                setBusquedaCuenta(cta.codigo);
                                                setFiltros({ ...filtros, cuenta: cta.codigo });
                                                localStorage.setItem('ultimaCuentaLibroDiario', cta.codigo); // <-- GUARDA EN MEMORIA
                                                setMostrarLista(false);
                                            }}
                                            className="px-4 py-2 hover:bg-blue-50 cursor-pointer flex justify-between border-b border-slate-50"
                                        >
                                            <span className="font-medium">{cta.nombre}</span>
                                            <span className="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded">{cta.codigo}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>

                    <div className="flex gap-2">
                        <button onClick={cargarLibroDiario} className="bg-blue-600 text-white px-5 py-2 rounded-lg font-bold hover:bg-blue-700 text-sm shadow-sm transition-all active:scale-95">Consultar</button>
                        <button onClick={exportarExcel} className="bg-emerald-600 text-white px-5 py-2 rounded-lg font-bold hover:bg-emerald-700 text-sm flex items-center gap-2 shadow-sm transition-all active:scale-95">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            Excel
                        </button>
                    </div>
                </div>
            )}

            {/* TABLA DE MOVIMIENTOS */}
            {activeTab === 'diario' && (
                <div className="bg-white rounded-lg shadow border border-slate-200 overflow-hidden z-10 relative">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead className="bg-slate-100 text-[11px] uppercase text-slate-500 font-bold border-b border-slate-200">
                                <tr>
                                    <th className="px-4 py-3 w-32 border-r border-slate-200">Comprobante</th>
                                    <th className="px-4 py-3 w-28 border-r border-slate-200 text-center">Fecha</th>
                                    <th className="px-4 py-3 w-64 border-r border-slate-200">Cuenta Contable</th>
                                    <th className="px-4 py-3 border-r border-slate-200">Descripción / Glosa</th>
                                    <th className="px-4 py-3 w-32 text-right border-r border-slate-200">Debe</th>
                                    <th className="px-4 py-3 w-32 text-right">Haber</th>
                                </tr>
                            </thead>
                            <tbody className="text-xs divide-y divide-slate-100">
                                {loading ? <tr><td colSpan="6" className="p-8 text-center text-slate-400">Cargando...</td></tr> :
                                    asientos.length === 0 ? <tr><td colSpan="6" className="p-8 text-center text-slate-400">No hay movimientos.</td></tr> : (
                                        asientos.map((row, idx) => (
                                            <tr
                                                key={idx}
                                                onContextMenu={(e) => handleContextMenu(e, row.asiento_id)}
                                                className="hover:bg-blue-50 transition-colors cursor-context-menu"
                                                title="Click derecho para opciones"
                                            >
                                                <td className="px-4 py-2 font-mono text-blue-600 font-bold border-r border-slate-100">{row.codigo_unico || row.asiento_id}</td>
                                                <td className="px-4 py-2 text-center text-slate-500 border-r border-slate-100 whitespace-nowrap">{formatDate(row.fecha)}</td>
                                                <td className="px-4 py-2 border-r border-slate-100">
                                                    <div className="font-mono text-slate-600 font-bold">{row.cuenta_codigo}</div>
                                                    <div className="text-slate-400 truncate max-w-[200px]">{row.cuenta_nombre}</div>
                                                </td>
                                                <td className="px-4 py-2 text-slate-700 border-r border-slate-100">{row.glosa}</td>
                                                <td className="px-4 py-2 text-right font-mono text-emerald-600 bg-emerald-50/30">{formatMoney(row.debe)}</td>
                                                <td className="px-4 py-2 text-right font-mono text-slate-600 bg-slate-50/30">{formatMoney(row.haber)}</td>
                                            </tr>
                                        ))
                                    )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* VISTA 3: VISOR DE COMPROBANTE */}
            {activeTab === 'visor' && asientoSeleccionado && (
                <div className="space-y-6 animate-fade-in-up">
                    <button
                        onClick={() => setActiveTab('diario')}
                        className="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 hover:text-blue-600 px-4 py-2 rounded-lg shadow-sm font-medium flex items-center gap-2 transition-all active:scale-95"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Volver al Libro Diario
                    </button>

                    <div className="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
                        <div className="bg-slate-50 p-6 border-b border-slate-200">
                            <div className="flex justify-between items-start">
                                <div>
                                    <h2 className="text-2xl font-bold text-slate-800">Comprobante Contable</h2>
                                    <p className="text-slate-500 mt-1">N° Único: <span className="font-mono font-bold text-slate-700">{asientoSeleccionado.cabecera?.codigo_unico || asientoSeleccionado.cabecera?.numero_comprobante}</span></p>
                                </div>
                                <div className="text-right">
                                    <div className="inline-block px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700 uppercase mb-2">
                                        {asientoSeleccionado.cabecera?.tipo_asiento || asientoSeleccionado.cabecera?.tipo}
                                    </div>
                                    <p className="text-sm text-slate-500">Fecha: {formatDate(asientoSeleccionado.cabecera?.fecha)}</p>
                                </div>
                            </div>
                            <div className="mt-4 bg-white p-3 rounded border border-slate-200">
                                <span className="text-xs font-bold text-slate-400 uppercase block mb-1">Glosa / Descripción</span>
                                <p className="text-slate-700 italic">"{asientoSeleccionado.cabecera?.glosa}"</p>
                            </div>
                        </div>

                        <table className="w-full text-left">
                            <thead className="bg-white border-b border-slate-200 text-xs font-bold text-slate-500 uppercase">
                                <tr>
                                    <th className="px-6 py-4">Cuenta</th>
                                    <th className="px-6 py-4 text-right">Debe</th>
                                    <th className="px-6 py-4 text-right">Haber</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-sm">
                                {asientoSeleccionado.detalles?.map((det, idx) => (
                                    <tr key={idx} className="hover:bg-slate-50">
                                        <td className="px-6 py-3">
                                            <div className="font-bold font-mono text-slate-700">{det.cuenta_contable}</div>
                                            <div className="text-slate-500">{det.cuenta_nombre || det.cuenta?.nombre}</div>
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono text-emerald-600 font-medium">
                                            {parseFloat(det.debe) > 0 ? formatMoney(det.debe) : '-'}
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono text-slate-600 font-medium">
                                            {parseFloat(det.haber) > 0 ? formatMoney(det.haber) : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-slate-50 border-t border-slate-200">
                                <tr>
                                    <td className="px-6 py-4 text-right font-bold text-slate-500 uppercase text-xs">Totales Iguales</td>
                                    <td className="px-6 py-4 text-right font-bold font-mono text-emerald-700">
                                        {formatMoney(asientoSeleccionado.detalles?.reduce((acc, d) => acc + parseFloat(d.debe), 0))}
                                    </td>
                                    <td className="px-6 py-4 text-right font-bold font-mono text-slate-700">
                                        {formatMoney(asientoSeleccionado.detalles?.reduce((acc, d) => acc + parseFloat(d.haber), 0))}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );
};

export default LibroMayor;