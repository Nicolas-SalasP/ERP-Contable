import React, { useState, useEffect, useRef } from 'react';
import { api } from '../../../Configuracion/api';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import * as XLSX from "@e965/xlsx";
import { logger } from '../../../Configuracion/logger';
import { Eye, Download, ArrowLeft } from 'lucide-react';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';
import { useToast } from '../../../Contextos/ToastContext';
import ModalDetalleAsiento from '../Componentes/ModalDetalleAsiento';
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
    const { toast } = useToast();
    const [activeTab, setActiveTab] = useState('diario');
    const [asientos, setAsientos] = useState([]);
    const [planCuentas, setPlanCuentas] = useState([]);
    const [loading, setLoading] = useState(false);
    // Versiona las cargas del libro diario para descartar respuestas fuera de orden
    // (una respuesta lenta de un filtro viejo no debe pisar la del filtro actual).
    const peticionDiarioRef = useRef(0);
    const cuentaGuardada = localStorage.getItem('ultimaCuentaLibroDiario') || '';

    const [filtros, setFiltros] = useState({
        desde: new Date().toISOString().slice(0, 7) + '-01',
        hasta: new Date().toISOString().split('T')[0],
        cuenta: cuentaGuardada,
        auditoria: 1,
        search: '',
    });

    const [busquedaCuenta, setBusquedaCuenta] = useState(cuentaGuardada);
    const [sugerencias, setSugerencias] = useState([]);
    const [mostrarLista, setMostrarLista] = useState(false);
    const wrapperRef = useRef(null);
    const [contextMenu, setContextMenu] = useState({ visible: false, x: 0, y: 0, asientoId: null });
    const [asientoSeleccionado, setAsientoSeleccionado] = useState(null);
    const [detalleId, setDetalleId] = useState(null);

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
            logger.error("Error plan cuentas", error);
        }
    };

    const cargarLibroDiario = async () => {
        const diffTime = Math.abs(new Date(filtros.hasta) - new Date(filtros.desde));
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        if (diffDays > 366) {
            return toast('Rango excedido: consulta máximo 366 días a la vez.', 'warning');
        }

        setLoading(true);
        const reqId = ++peticionDiarioRef.current;
        try {
            const cuentaAEnviar = filtros.cuenta || (busquedaCuenta.match(/^\d+/) ? busquedaCuenta : '');
            const params = {
                desde: filtros.desde,
                hasta: filtros.hasta,
                cuenta: cuentaAEnviar,
                filtro: filtros.auditoria,
                search: filtros.search
            };
            const query = new URLSearchParams(params).toString();
            const res = await api.get(`/contabilidad/libro-diario?${query}`);

            // Descarta esta respuesta si ya se disparo una carga mas reciente.
            if (reqId !== peticionDiarioRef.current) return;

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
                        estado: mov.estado,
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
                                    estado: asiento.estado,
                                    numero_documento: asiento.numero_documento,
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
                toast(res.message || 'No se pudieron cargar los datos.', 'error');
            }
        } catch (error) {
            logger.error("Error cargando diario", error);
            if (reqId === peticionDiarioRef.current) setAsientos([]);
        } finally {
            // Solo la peticion mas reciente controla el estado de carga.
            if (reqId === peticionDiarioRef.current) setLoading(false);
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
            toast('No se pudo cargar el detalle del asiento.', 'error');
        } finally {
            setLoading(false);
        }
    };

    const exportarExcel = () => {
        if (asientos.length === 0) {
            toast('No hay movimientos para exportar en el rango seleccionado.', 'warning');
            return;
        }

        const datosExcel = asientos.map(fila => ({
            "Fecha": formatDate(fila.fecha),
            "Comprobante": fila.codigo_unico || fila.asiento_id,
            "Código Cuenta": fila.cuenta_codigo,
            "Nombre Cuenta": fila.cuenta_nombre,
            "Glosa": fila.glosa,
            "Estado": fila.estado,
            "Debe": parseFloat(fila.debe) || 0,
            "Haber": parseFloat(fila.haber) || 0
        }));

        const worksheet = XLSX.utils.json_to_sheet(datosExcel);
        worksheet['!cols'] = [{ wch: 12 }, { wch: 15 }, { wch: 15 }, { wch: 30 }, { wch: 40 }, { wch: 15 }, { wch: 15 }, { wch: 15 }];
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Libro Diario");

        const nombreCuenta = filtros.cuenta ? `_Cta_${filtros.cuenta}` : '_General';
        XLSX.writeFile(workbook, `Libro_Diario${nombreCuenta}_${filtros.desde}_${filtros.hasta}.xlsx`);
    };

    return (
        <>
        <div className="max-w-7xl mx-auto p-6 font-sans text-slate-800 dark:text-slate-200 relative">

            {contextMenu.visible && (
                <div
                    className="fixed bg-white dark:bg-slate-800 shadow-xl border border-slate-200 dark:border-slate-700 rounded-lg py-1 z-50 w-56 animate-fade-in"
                    style={{ top: contextMenu.y, left: contextMenu.x }}
                >
                    <button
                        onClick={abrirComprobante}
                        className="w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-slate-700 hover:text-blue-600 flex items-center gap-3 transition-colors"
                    >
                        <Eye size={16} strokeWidth={1.75} className="text-slate-400" />
                        Abrir Comprobante
                    </button>
                </div>
            )}

            <div className="flex justify-between items-center mb-6">
                <div className="flex items-center gap-3"><h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">Libros Contables</h1><AyudaModulo moduloId="libroMayor" size={26} /></div>
                <div className="flex bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-1">
                    <button onClick={() => setActiveTab('diario')} className={`px-4 py-1.5 text-sm font-bold rounded-md transition ${activeTab === 'diario' ? 'bg-slate-800 text-white' : 'hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400'}`}>Libro Diario / Mayor</button>
                    {activeTab === 'visor' && (
                        <button className="px-4 py-1.5 text-sm font-bold rounded-md bg-emerald-600 text-white flex items-center gap-2 animate-pulse">
                            <span className="w-2 h-2 bg-white rounded-full"></span>
                            Visor Comprobante
                        </button>
                    )}
                </div>
            </div>

            {activeTab === 'diario' && (
                <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm mb-6 flex flex-wrap gap-4 items-end z-20 relative">
                    <div>
                        <label className="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Desde</label>
                        <input type="date" className="border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none" value={filtros.desde} onChange={e => setFiltros({ ...filtros, desde: e.target.value })} />
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Hasta</label>
                        <input type="date" className="border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none" value={filtros.hasta} onChange={e => setFiltros({ ...filtros, hasta: e.target.value })} />
                    </div>

                    <div className="flex-1 min-w-full sm:min-w-[250px] relative" ref={wrapperRef}>
                        <label className="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Buscar Cuenta (Vacío para ver todo)</label>
                        <div className="relative">
                            <input
                                type="text"
                                placeholder="Escribe 'Caja' o '1101'..."
                                className="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 font-mono"
                                value={busquedaCuenta}
                                onChange={(e) => {
                                    setBusquedaCuenta(e.target.value);
                                    setMostrarLista(true);
                                    if (e.target.value === '') {
                                        setFiltros({ ...filtros, cuenta: '' });
                                        localStorage.removeItem('ultimaCuentaLibroDiario');
                                    }
                                }}
                                onFocus={() => setMostrarLista(true)}
                            />
                            {busquedaCuenta && (
                                <button
                                    onClick={() => {
                                        setBusquedaCuenta('');
                                        setFiltros({ ...filtros, cuenta: '' });
                                        localStorage.removeItem('ultimaCuentaLibroDiario');
                                    }}
                                    className="absolute right-2 top-2 text-slate-400 hover:text-slate-600"
                                >✕</button>
                            )}
                        </div>
                        {mostrarLista && sugerencias.length > 0 && (
                            <div className="absolute top-full left-0 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl mt-1 max-h-60 overflow-y-auto z-50">
                                <ul className="py-1 text-sm text-slate-700 dark:text-slate-300">
                                    {sugerencias.map(cta => (
                                        <li
                                            key={cta.id}
                                            onClick={() => {
                                                setBusquedaCuenta(cta.codigo);
                                                setFiltros({ ...filtros, cuenta: cta.codigo });
                                                localStorage.setItem('ultimaCuentaLibroDiario', cta.codigo);
                                                setMostrarLista(false);
                                            }}
                                            className="px-4 py-2 hover:bg-blue-50 dark:hover:bg-slate-700 cursor-pointer flex justify-between border-b border-slate-50 dark:border-slate-700"
                                        >
                                            <span className="font-medium">{cta.nombre}</span>
                                            <span className="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded">{cta.codigo}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                    <div className="flex-1 min-w-full sm:min-w-[180px]">
                        <label className="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Palabra en Glosa</label>
                        <input
                            type="text"
                            className="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none"
                            placeholder="Ej: Traspaso, Pago..."
                            value={filtros.search}
                            onChange={e => setFiltros({ ...filtros, search: e.target.value })}
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-blue-600 uppercase mb-1">Auditoría</label>
                        <select
                            className="border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/40 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none font-bold text-slate-700 dark:text-blue-300"
                            value={filtros.auditoria}
                            onChange={e => setFiltros({ ...filtros, auditoria: Number(e.target.value) })}
                        >
                            <option value="1">1 - Conciliado / Válidos</option>
                            <option value="0">0 - Historia Completa (Todo)</option>
                            <option value="2">2 - Anulados / Internos</option>
                        </select>
                    </div>

                    <div className="flex gap-2">
                        <button onClick={cargarLibroDiario} className="bg-blue-600 text-white px-5 py-2 rounded-lg font-bold hover:bg-blue-700 text-sm shadow-sm transition-all active:scale-95">Consultar</button>
                        <button onClick={exportarExcel} className="bg-emerald-600 text-white px-5 py-2 rounded-lg font-bold hover:bg-emerald-700 text-sm flex items-center gap-2 shadow-sm transition-all active:scale-95">
                            <Download size={16} strokeWidth={1.75} />
                            Excel
                        </button>
                    </div>
                </div>
            )}

            {activeTab === 'diario' && (
                <div className="bg-white dark:bg-slate-800 rounded-lg shadow border border-slate-200 dark:border-slate-700 overflow-hidden z-10 relative">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead className="sticky top-0 z-10 bg-slate-100 dark:bg-slate-900 text-[11px] uppercase text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th className="px-4 py-3 w-32 border-r border-slate-200 dark:border-slate-700">Comprobante</th>
                                    <th className="px-4 py-3 w-28 border-r border-slate-200 dark:border-slate-700 text-center">Fecha</th>
                                    <th className="px-4 py-3 w-64 border-r border-slate-200 dark:border-slate-700">Cuenta Contable</th>
                                    <th className="px-4 py-3 border-r border-slate-200 dark:border-slate-700">Descripción / Glosa</th>
                                    <th className="px-4 py-3 w-28 border-r border-slate-200 dark:border-slate-700 text-center">Ref. Doc</th>
                                    <th className="px-4 py-3 w-32 text-right border-r border-slate-200 dark:border-slate-700">Debe</th>
                                    <th className="px-4 py-3 w-32 text-right">Haber</th>
                                </tr>
                            </thead>
                            <tbody className="text-xs divide-y divide-slate-100 dark:divide-slate-700">
                                {loading ? <TablaSkeleton filas={8} columnas={7} /> :
                                    asientos.length === 0 ? <EstadoVacio mensaje="Sin movimientos en el período" detalle="Ajusta el rango de fechas o selecciona otra cuenta." /> : (
                                        asientos.map((row, idx) => {
                                            const esAnulado = row.estado === 'ANULADO' || row.estado === 'RECLASIFICADO';
                                            return (
                                                <tr
                                                    key={idx}
                                                    onClick={() => setDetalleId(row.asiento_id)}
                                                    onContextMenu={(e) => handleContextMenu(e, row.asiento_id)}
                                                    className={`transition-colors cursor-pointer ${esAnulado ? 'bg-red-50/60 hover:bg-red-100/80 dark:bg-red-900/20 dark:hover:bg-red-900/40 opacity-80' : 'hover:bg-blue-50 dark:hover:bg-slate-700/60'}`}
                                                    title={esAnulado ? 'Asiento Anulado/Interno — Click para ver detalle' : 'Click para ver detalle del comprobante'}
                                                >
                                                    <td className="px-4 py-2 font-mono text-blue-600 dark:text-blue-400 font-bold border-r border-slate-100 dark:border-slate-700">
                                                        {row.codigo_unico || row.asiento_id}
                                                        {esAnulado && <span className="ml-2 px-1.5 py-0.5 rounded text-[9px] bg-red-200 dark:bg-red-900/60 text-red-700 dark:text-red-400">R</span>}
                                                    </td>
                                                    <td className="px-4 py-2 text-center text-slate-500 dark:text-slate-400 border-r border-slate-100 dark:border-slate-700 whitespace-nowrap">{formatDate(row.fecha)}</td>
                                                    <td className="px-4 py-2 border-r border-slate-100 dark:border-slate-700">
                                                        <div className={`font-mono font-bold ${esAnulado ? 'text-red-700 dark:text-red-400' : 'text-slate-600 dark:text-slate-300'}`}>{row.cuenta_codigo}</div>
                                                        <div className={`truncate max-w-[120px] sm:max-w-[180px] md:max-w-[200px] ${esAnulado ? 'text-red-500 dark:text-red-400' : 'text-slate-400 dark:text-slate-500'}`}>{row.cuenta_nombre}</div>
                                                    </td>
                                                    <td className={`px-4 py-2 border-r border-slate-100 dark:border-slate-700 ${esAnulado ? 'text-red-800 dark:text-red-400 line-through decoration-red-300' : 'text-slate-700 dark:text-slate-300'}`}>{row.glosa}</td>
                                                    <td className="px-4 py-2 text-center text-slate-500 dark:text-slate-400 font-mono border-r border-slate-100 dark:border-slate-700">{row.numero_documento || '-'}</td>
                                                    <td className={`px-4 py-2 text-right font-mono ${esAnulado ? 'text-red-600 bg-red-100/50 dark:bg-red-900/30 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400 bg-emerald-50/30 dark:bg-emerald-900/20'}`}>{formatMoney(row.debe)}</td>
                                                    <td className={`px-4 py-2 text-right font-mono ${esAnulado ? 'text-red-600 bg-red-100/50 dark:bg-red-900/30 dark:text-red-400' : 'text-slate-600 dark:text-slate-300 bg-slate-50/30 dark:bg-slate-700/30'}`}>{formatMoney(row.haber)}</td>
                                                </tr>
                                            );
                                        })
                                    )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {activeTab === 'visor' && asientoSeleccionado && (
                <div className="space-y-6 animate-fade-in-up">
                    <button
                        onClick={() => setActiveTab('diario')}
                        className="bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-blue-600 px-4 py-2 rounded-lg shadow-sm font-medium flex items-center gap-2 transition-all active:scale-95"
                    >
                        <ArrowLeft size={16} strokeWidth={1.75} />
                        Volver al Libro Diario
                    </button>

                    <div className="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div className="bg-slate-50 dark:bg-slate-900 p-6 border-b border-slate-200 dark:border-slate-700">
                            <div className="flex justify-between items-start">
                                <div>
                                    <h2 className="text-2xl font-bold text-slate-800 dark:text-slate-200">Comprobante Contable</h2>
                                    <p className="text-slate-500 dark:text-slate-400 mt-1">N° Único: <span className="font-mono font-bold text-slate-700 dark:text-slate-300">{asientoSeleccionado.cabecera?.codigo_unico || asientoSeleccionado.cabecera?.numero_comprobante}</span></p>
                                </div>
                                <div className="text-right">
                                    <div className={`inline-block px-3 py-1 rounded-full text-xs font-bold uppercase mb-2 ${asientoSeleccionado.cabecera?.estado === 'ANULADO' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`}>
                                        {asientoSeleccionado.cabecera?.estado === 'ANULADO' ? 'ANULADO / REVERSO' : (asientoSeleccionado.cabecera?.tipo_asiento || asientoSeleccionado.cabecera?.tipo)}
                                    </div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">Fecha: {formatDate(asientoSeleccionado.cabecera?.fecha)}</p>
                                </div>
                            </div>
                            <div className="mt-4 bg-white dark:bg-slate-800 p-3 rounded border border-slate-200 dark:border-slate-700">
                                <span className="text-xs font-bold text-slate-400 uppercase block mb-1">Glosa / Descripción</span>
                                <p className="text-slate-700 dark:text-slate-300 italic">"{asientoSeleccionado.cabecera?.glosa}"</p>
                            </div>
                        </div>

                        <table className="w-full text-left">
                            <thead className="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">
                                <tr>
                                    <th className="px-6 py-4">Cuenta</th>
                                    <th className="px-6 py-4 text-right">Debe</th>
                                    <th className="px-6 py-4 text-right">Haber</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                                {asientoSeleccionado.detalles?.map((det, idx) => (
                                    <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-slate-700">
                                        <td className="px-6 py-3">
                                            <div className="font-bold font-mono text-slate-700 dark:text-slate-300">{det.cuenta_contable}</div>
                                            <div className="text-slate-500 dark:text-slate-400">{det.cuenta_nombre || det.cuenta?.nombre}</div>
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
                            <tfoot className="bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-700">
                                <tr>
                                    <td className="px-6 py-4 text-right font-bold text-slate-500 dark:text-slate-400 uppercase text-xs">Totales Iguales</td>
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

        <ModalDetalleAsiento
            isOpen={detalleId !== null}
            asientoId={detalleId}
            onClose={() => setDetalleId(null)}
        />
        </>
    );
};

export default LibroMayor;