import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';
import ModalPagoFactura from '../Componentes/ModalPagoFactura';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-CL', { timeZone: 'UTC' });
};

const BuscadorCuentas = ({ cuentas, valor, onChange }) => {
    const [busqueda, setBusqueda] = useState('');
    const [abierto, setAbierto] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setAbierto(false);
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (valor && !abierto) {
            const cuenta = cuentas.find(c => c.codigo === valor);
            if (cuenta) setBusqueda(`${cuenta.codigo} - ${cuenta.nombre}`);
        }
    }, [valor, cuentas, abierto]);

    const filtradas = cuentas.filter(c =>
        c.codigo.includes(busqueda) ||
        c.nombre.toLowerCase().includes(busqueda.toLowerCase())
    );

    return (
        <div className="relative w-full" ref={ref}>
            <div className="relative">
                <input
                    type="text"
                    className={`w-full border p-2.5 rounded-lg text-sm outline-none transition-all font-bold pr-8 ${valor ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-blue-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-slate-700'}`}
                    placeholder="Escriba código o nombre de la cuenta..."
                    value={busqueda}
                    onChange={(e) => {
                        setBusqueda(e.target.value);
                        setAbierto(true);
                        if (valor) onChange('');
                    }}
                    onFocus={() => {
                        setAbierto(true);
                        setBusqueda('');
                    }}
                />
                <div className="absolute right-3 top-3 text-slate-400 pointer-events-none">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            {abierto && (
                <div className="absolute z-50 w-full mt-1 bg-white border border-slate-200 shadow-2xl max-h-56 overflow-y-auto rounded-lg rounded-tl-none animate-fade-in custom-scrollbar">
                    {filtradas.length > 0 ? filtradas.map(c => (
                        <div
                            key={c.codigo}
                            className="px-4 py-3 hover:bg-blue-50 cursor-pointer text-sm border-b border-slate-50 last:border-0 transition-colors flex flex-col"
                            onClick={() => {
                                onChange(c.codigo);
                                setBusqueda(`${c.codigo} - ${c.nombre}`);
                                setAbierto(false);
                            }}
                        >
                            <span className="font-mono font-bold text-blue-600">{c.codigo}</span>
                            <span className="text-slate-700 font-medium">{c.nombre}</span>
                        </div>
                    )) : (
                        <div className="px-4 py-3 text-slate-400 text-sm italic text-center">
                            No se encontraron cuentas
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

const ModalAsiento = ({ isOpen, onClose, data, loading }) => {
    if (!isOpen) return null;
    return (
        <div className="fixed inset-0 bg-slate-900/80 flex items-center justify-center z-50 p-4 backdrop-blur-sm animate-fade-in">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh] border border-slate-200">
                <div className="bg-slate-900 text-white px-4 md:px-8 py-5 flex justify-between items-center">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">Contabilidad</span>
                            {loading && <span className="text-xs text-blue-300 animate-pulse"><i className="fas fa-circle-notch fa-spin mr-1"></i> Cargando...</span>}
                        </div>
                        <h3 className="text-lg md:text-xl font-bold mt-1">
                            {loading ? 'Cargando Asiento...' : `Asiento Contable N° ${data?.cabecera?.numero_asiento || '---'}`}
                        </h3>
                    </div>
                    <button onClick={onClose} className="text-slate-400 hover:text-white transition-colors p-2 rounded-full hover:bg-slate-800">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                {loading ? (
                    <div className="p-16 text-center text-slate-400"><p>Recuperando información contable...</p></div>
                ) : (
                    data && (
                        <>
                            <div className="bg-slate-50 px-4 md:px-8 py-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div className="flex-1">
                                    <p className="text-xs text-slate-500 font-bold uppercase tracking-wide">Glosa / Descripción</p>
                                    <p className="text-slate-800 italic font-medium mt-1 text-sm md:text-base">"{data.cabecera.glosa}"</p>
                                </div>
                                <div className="text-left md:text-right w-full md:w-auto">
                                    <p className="text-xs text-slate-500 font-bold uppercase tracking-wide">Fecha Contable</p>
                                    <p className="text-slate-800 font-mono font-bold mt-1">{formatDate(data.cabecera.fecha_contable)}</p>
                                </div>
                            </div>
                            <div className="flex-1 overflow-auto custom-scrollbar">
                                <table className="min-w-full divide-y divide-slate-100">
                                    <thead className="bg-white sticky top-0 z-10 shadow-sm">
                                        <tr>
                                            <th className="px-4 md:px-8 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Cuenta Contable</th>
                                            <th className="px-4 md:px-8 py-3 text-right text-xs font-bold text-emerald-600 uppercase tracking-wider w-32 md:w-40 whitespace-nowrap">Debe</th>
                                            <th className="px-4 md:px-8 py-3 text-right text-xs font-bold text-red-600 uppercase tracking-wider w-32 md:w-40 whitespace-nowrap">Haber</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-slate-50 text-sm">
                                        {data.detalles.map((det, idx) => (
                                            <tr key={idx} className="hover:bg-blue-50/50 transition-colors">
                                                <td className="px-4 md:px-8 py-4">
                                                    <div className="font-bold text-slate-800">{det.nombre_cuenta}</div>
                                                    <div className="text-xs text-slate-500 font-mono mt-1 bg-slate-100 px-2 py-0.5 rounded inline-block border border-slate-200">{det.cuenta_contable}</div>
                                                </td>
                                                <td className="px-4 md:px-8 py-4 text-right font-mono font-medium text-emerald-700 bg-emerald-50/10 border-l border-white whitespace-nowrap">
                                                    {parseFloat(det.debe) > 0 ? formatCurrency(det.debe) : '-'}
                                                </td>
                                                <td className="px-4 md:px-8 py-4 text-right font-mono font-medium text-red-700 bg-red-50/10 border-l border-white whitespace-nowrap">
                                                    {parseFloat(det.haber) > 0 ? formatCurrency(det.haber) : '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="bg-slate-50 font-bold text-slate-800 border-t border-slate-200">
                                        <tr>
                                            <td className="px-4 md:px-8 py-4 text-right uppercase text-xs tracking-wider text-slate-500">Totales</td>
                                            <td className="px-4 md:px-8 py-4 text-right text-emerald-700 bg-emerald-100/50 whitespace-nowrap">
                                                {formatCurrency(data.detalles.reduce((acc, i) => acc + parseFloat(i.debe), 0))}
                                            </td>
                                            <td className="px-4 md:px-8 py-4 text-right text-red-700 bg-red-100/50 whitespace-nowrap">
                                                {formatCurrency(data.detalles.reduce((acc, i) => acc + parseFloat(i.haber), 0))}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </>
                    )
                )}
                <div className="p-4 bg-gray-50 border-t border-slate-200 flex justify-end">
                    <button onClick={onClose} className="px-6 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-900 font-bold transition-colors shadow-sm text-sm w-full md:w-auto">Cerrar Detalle</button>
                </div>
            </div>
        </div>
    );
};

const HistorialFacturas = () => {
    const navigate = useNavigate();
    const [modalPagoOpen, setModalPagoOpen] = useState(false);
    const [facturaSeleccionada, setFacturaSeleccionada] = useState(null);

    const abrirModalPago = (factura) => {
        setMenuAbiertoId(null);
        setFacturaSeleccionada(factura);
        setModalPagoOpen(true);
    };

    const [busqueda, setBusqueda] = useState('');
    const [filtroNumero, setFiltroNumero] = useState('');
    const [filtroEstado, setFiltroEstado] = useState('');
    const [facturas, setFacturas] = useState([]);
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);
    const [pagination, setPagination] = useState({ page: 1, limit: 10, total: 0, totalPages: 0 });
    const [listaProveedores, setListaProveedores] = useState([]);
    const [sugerencias, setSugerencias] = useState([]);
    const [mostrarSugerencias, setMostrarSugerencias] = useState(false);
    const searchRef = useRef(null);

    const [modalOpen, setModalOpen] = useState(false);
    const [asientoData, setAsientoData] = useState(null);
    const [loadingAsiento, setLoadingAsiento] = useState(false);

    const [menuAbiertoId, setMenuAbiertoId] = useState(null);
    const [vistaActual, setVistaActual] = useState(1);

    const [facturaActiva, setFacturaActiva] = useState(null);
    const [asientoReclasificacion, setAsientoReclasificacion] = useState(null);
    const [loadingReclasificacion, setLoadingReclasificacion] = useState(false);

    const [formCambio, setFormCambio] = useState({
        fechaContableCambio: new Date().toISOString().split('T')[0],
        nuevaGlosa: '',
        nuevaCuenta: ''
    });

    const [cuentasPlan, setCuentasPlan] = useState([]);

    useEffect(() => {
        api.get('/proveedores')
            .then(res => { if (res.success) setListaProveedores(res.data); })
            .catch(err => console.error("Error", err));
            
        api.get('/contabilidad/plan-cuentas')
            .then(res => {
                if (res.success && res.data) {
                    const cuentasImputables = res.data.filter(c => c.imputable == 1 || c.imputable === true);
                    if (cuentasImputables.length > 0) {
                        setCuentasPlan(cuentasImputables);
                    }
                }
            }).catch(err => console.log("Usando cuentas fallback", err));

        const handleClickOutside = (event) => {
            if (searchRef.current && !searchRef.current.contains(event.target)) setMostrarSugerencias(false);
            if (!event.target.closest('.menu-acciones-container')) setMenuAbiertoId(null);
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    useEffect(() => {
        if (vistaActual !== 3) ejecutarBusqueda();
    }, [pagination.page, vistaActual]);

    const handleBusquedaChange = (e) => {
        const termino = e.target.value;
        setBusqueda(termino);
        if (termino.length > 0) {
            const matches = listaProveedores.filter(p =>
                p.razon_social.toLowerCase().includes(termino.toLowerCase()) ||
                (p.rut && p.rut.toLowerCase().includes(termino.toLowerCase())) ||
                p.codigo_interno.toString().includes(termino)
            );
            setSugerencias(matches);
            setMostrarSugerencias(true);
        } else {
            setSugerencias([]);
            setMostrarSugerencias(false);
        }
    };

    const ejecutarBusqueda = async (resetPage = false) => {
        setLoading(true);
        setSearched(true);
        setMostrarSugerencias(false);

        if (resetPage) setPagination(prev => ({ ...prev, page: 1 }));

        const params = new URLSearchParams();
        if (busqueda) params.append('search', busqueda);
        if (filtroNumero) params.append('num', filtroNumero);
        if (filtroEstado) params.append('estado', filtroEstado);
        params.append('page', resetPage ? 1 : pagination.page);
        params.append('limit', pagination.limit);

        try {
            const res = await api.get(`/facturas/historial?${params.toString()}`);
            if (res.success) {
                setFacturas(res.data);
                setPagination(prev => ({ ...prev, total: res.pagination.total, totalPages: res.pagination.totalPages }));
            } else {
                setFacturas([]);
            }
        } catch (error) { console.error(error); }
        finally { setLoading(false); }
    };

    const seleccionarProveedor = (prov) => {
        setBusqueda(prov.razon_social);
        setMostrarSugerencias(false);
    };

    const verAsientoContable = async (facturaId) => {
        setMenuAbiertoId(null);
        setModalOpen(true);
        setLoadingAsiento(true);
        setAsientoData(null);
        try {
            const res = await api.get(`/facturas/${facturaId}/asiento`);
            if (res.success) {
                setAsientoData(res.data);
            } else { 
                Swal.fire('Asiento no encontrado', 'Esta factura aún no ha sido procesada contablemente.', 'info'); 
                setModalOpen(false); 
            }
        } catch (error) {
            Swal.fire('Error', 'Hubo un problema al obtener el asiento contable.', 'error');
            setModalOpen(false);
        } finally {
            setLoadingAsiento(false);
        }
    };

    const iniciarCambio = async (factura) => {
        setMenuAbiertoId(null);
        setFacturaActiva(factura);
        setFormCambio({
            fechaContableCambio: new Date().toISOString().split('T')[0],
            nuevaGlosa: `Ajuste Imputación Fac. N° ${factura.numero_factura}`,
            nuevaCuenta: ''
        });

        setVistaActual(3);
        setLoadingReclasificacion(true);
        setAsientoReclasificacion(null);

        try {
            const res = await api.get(`/facturas/${factura.id}/asiento`);
            if (res.success) setAsientoReclasificacion(res.data);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar el asiento para reclasificar.',
                customClass: { confirmButton: 'bg-slate-900 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-slate-800' },
                buttonsStyling: false
            });
            setVistaActual(1);
        } finally {
            setLoadingReclasificacion(false);
        }
    };

    const intentarCambioProhibido = () => {
        Swal.fire({
            icon: 'error',
            title: 'Línea Bloqueada',
            text: 'Cuentas de IVA y Proveedores (Pasivos/Impuestos) son intocables por integridad del sistema.',
            customClass: { confirmButton: 'bg-slate-900 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-slate-800' },
            buttonsStyling: false
        });
    };

    const ejecutarReclasificacion = async () => {
        if (!formCambio.nuevaCuenta) {
            return Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'Seleccione una cuenta contable de destino.',
                customClass: { confirmButton: 'bg-amber-500 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-amber-600' },
                buttonsStyling: false
            });
        }

        if (formCambio.fechaContableCambio < facturaActiva.fecha_emision) {
            return Swal.fire({
                icon: 'error',
                title: 'Fecha Inválida',
                text: `La fecha contable no puede ser anterior a la emisión (${formatDate(facturaActiva.fecha_emision)}).`,
                customClass: { confirmButton: 'bg-slate-900 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-slate-800' },
                buttonsStyling: false
            });
        }

        Swal.fire({
            title: '¿Confirmar Ajuste?',
            text: "Se registrará una reversa y un nuevo cargo dentro de este mismo asiento.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplicar ajuste',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            customClass: {
                confirmButton: 'bg-emerald-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-emerald-700 ml-3',
                cancelButton: 'bg-slate-100 text-slate-700 border border-slate-300 px-6 py-2.5 rounded-lg font-bold hover:bg-slate-200'
            },
            buttonsStyling: false
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    await api.post(`/facturas/${facturaActiva.id}/reclasificar`, formCambio);
                    Swal.fire({
                        icon: 'success',
                        title: 'Completado',
                        text: 'La factura ha sido reclasificada.',
                        customClass: { confirmButton: 'bg-emerald-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-emerald-700' },
                        buttonsStyling: false
                    });
                    setVistaActual(1);
                } catch (error) {
                    let msg = error.response?.data?.mensaje || 'Hubo un error al actualizar el asiento.';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: msg,
                        customClass: { confirmButton: 'bg-slate-900 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-slate-800' },
                        buttonsStyling: false
                    });
                }
            }
        });
    };

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 font-sans text-slate-800 pb-10">
            <ModalAsiento isOpen={modalOpen} onClose={() => setModalOpen(false)} data={asientoData} loading={loadingAsiento} />

            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 className="text-2xl md:text-3xl font-bold text-slate-900">
                        {vistaActual === 3 ? 'Workbench de Reclasificación' : 'Historial de Compras'}
                    </h1>
                    <p className="text-slate-500 text-sm mt-1">
                        {vistaActual === 3 ? `Ajuste intra-asiento Fac. ${facturaActiva?.numero_factura}` : 'Cuenta Corriente y Auditoría Contable'}
                    </p>
                </div>

                {vistaActual !== 3 && (
                    <div className="flex items-center w-full md:w-auto bg-white p-1.5 rounded-lg border border-slate-200 shadow-sm">
                        <button
                            onClick={() => setVistaActual(1)}
                            className={`flex-1 md:flex-none px-4 py-2 rounded-md text-sm font-bold transition-all ${vistaActual === 1 ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100'}`}
                        >
                            <i className="fas fa-list mr-2"></i> Detallada
                        </button>
                        <button
                            onClick={() => setVistaActual(2)}
                            className={`flex-1 md:flex-none px-4 py-2 rounded-md text-sm font-bold transition-all ${vistaActual === 2 ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100'}`}
                        >
                            <i className="fas fa-table mr-2"></i> Contable
                        </button>
                    </div>
                )}
            </div>

            {vistaActual !== 3 ? (
                <>
                    <div className="bg-white p-5 rounded-xl shadow-sm border border-slate-200 mb-8" ref={searchRef}>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                            <div className="relative sm:col-span-2">
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Proveedor</label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <input
                                        type="text"
                                        className="w-full !pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700 text-sm"
                                        placeholder="RUT o Razón Social..."
                                        value={busqueda}
                                        onChange={handleBusquedaChange}
                                        onFocus={() => { if (busqueda) setMostrarSugerencias(true); }}
                                        onKeyDown={(e) => e.key === 'Enter' && ejecutarBusqueda(true)}
                                    />
                                </div>
                                
                                {mostrarSugerencias && sugerencias.length > 0 && (
                                    <div className="absolute top-full left-0 w-full bg-white border border-slate-200 mt-2 rounded-xl shadow-2xl max-h-60 overflow-y-auto z-50 custom-scrollbar">
                                        {sugerencias.map(p => (
                                            <div key={p.id} onClick={() => seleccionarProveedor(p)} className="p-3 hover:bg-blue-50 cursor-pointer border-b last:border-0 border-slate-100 transition-colors group">
                                                <p className="font-bold text-slate-800 text-sm group-hover:text-blue-700">{p.razon_social}</p>
                                                <span className="text-xs text-slate-500 font-mono">{p.rut}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">N° Documento</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700 text-sm"
                                    placeholder="Ej: 12345"
                                    value={filtroNumero}
                                    onChange={(e) => setFiltroNumero(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && ejecutarBusqueda(true)}
                                />
                            </div>

                            <div className="flex gap-2">
                                <div className="flex-1">
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Estado</label>
                                    <select
                                        className="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700 text-sm"
                                        value={filtroEstado}
                                        onChange={(e) => setFiltroEstado(e.target.value)}
                                    >
                                        <option value="">Todos</option>
                                        <option value="REGISTRADA">Pendientes</option>
                                        <option value="PAGADA">Pagadas</option>
                                        <option value="ANULADA">Anuladas</option>
                                    </select>
                                </div>
                                <button
                                    onClick={() => ejecutarBusqueda(true)}
                                    disabled={loading}
                                    className="px-4 py-2.5 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 shadow-sm disabled:opacity-70 transition-all flex items-center justify-center mt-6 h-[42px]"
                                >
                                    {loading ? <i className="fas fa-circle-notch fa-spin"></i> : <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-visible flex flex-col">
                        {!searched && facturas.length === 0 ? (
                            <div className="flex flex-col items-center justify-center h-64 text-slate-400">
                                <svg className="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <p className="text-base font-medium">Usa los filtros para buscar facturas.</p>
                            </div>
                        ) : facturas.length === 0 ? (
                            <div className="flex flex-col items-center justify-center h-64 text-slate-400">
                                <svg className="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                                <p className="text-base font-medium">No se encontraron documentos.</p>
                            </div>
                        ) : (
                            <>
                                {/* VERSIÓN MÓVIL (Tarjetas con Grid y Corrección de NaN) */}
                                <div className="grid grid-cols-1 gap-4 p-4 md:hidden">
                                    {facturas.map((fac) => (
                                        <div key={fac.id} className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm relative">
                                            <div className={`absolute top-0 left-0 w-1.5 h-full rounded-l-xl ${fac.estado === 'PAGADA' ? 'bg-emerald-500' : fac.estado === 'ANULADA' ? 'bg-red-400' : 'bg-amber-400'}`}></div>
                                            
                                            <div className="flex justify-between items-start mb-2 pl-2">
                                                <div>
                                                    <div className="text-xs font-bold text-slate-500 font-mono mb-0.5">
                                                        {vistaActual === 2 && fac.codigo_asiento ? `Asiento: ${fac.codigo_asiento}` : `Fac: ${fac.numero_factura}`}
                                                    </div>
                                                    <h3 className="font-bold text-slate-800 leading-tight">{fac.nombre_proveedor}</h3>
                                                </div>
                                                <span className={`inline-flex px-2 py-1 text-[10px] font-bold rounded uppercase border ${
                                                    fac.estado === 'PAGADA' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                                                    fac.estado === 'ANULADA' ? 'bg-red-50 text-red-700 border-red-200' : 
                                                    'bg-amber-50 text-amber-700 border-amber-200'
                                                }`}>
                                                    {fac.estado === 'REGISTRADA' ? 'Pendiente' : fac.estado}
                                                </span>
                                            </div>
                                            
                                            <div className="pl-2 mb-4">
                                                <div className="text-sm text-slate-600 flex items-center gap-2 mb-2">
                                                    <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> 
                                                    {formatDate(fac.fecha_emision)}
                                                </div>
                                                
                                                {/* CORRECCIÓN DE NaN: Vista detallada muestra total, Vista contable muestra desglose */}
                                                {vistaActual === 1 ? (
                                                    <div className="text-xl font-black text-slate-800 mt-1">
                                                        {formatCurrency(fac.monto_bruto)}
                                                    </div>
                                                ) : (
                                                    <div className="flex justify-between items-center bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                                        <div className="text-left">
                                                            <span className="block text-[10px] font-bold text-slate-400 uppercase">Neto</span>
                                                            <span className="text-xs font-mono font-medium text-slate-600">{formatCurrency(fac.monto_neto)}</span>
                                                        </div>
                                                        <div className="text-center border-l border-r border-slate-200 px-3">
                                                            <span className="block text-[10px] font-bold text-slate-400 uppercase">IVA</span>
                                                            <span className="text-xs font-mono font-medium text-slate-600">{formatCurrency(fac.monto_iva)}</span>
                                                        </div>
                                                        <div className="text-right">
                                                            <span className="block text-[10px] font-bold text-slate-400 uppercase">Total</span>
                                                            <span className="text-sm font-mono font-bold text-slate-800">{formatCurrency(fac.monto_bruto)}</span>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>

                                            {/* BOTONERA MOVIL MEJORADA (CSS GRID) */}
                                            <div className="flex flex-col gap-2 pt-3 border-t border-slate-100 pl-2">
                                                {fac.estado === 'REGISTRADA' && (
                                                    <button onClick={() => abrirModalPago(fac)} className="w-full bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-xs py-2.5 rounded-lg transition-colors border border-emerald-100 flex items-center justify-center gap-2">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> 
                                                        Pagar Factura
                                                    </button>
                                                )}
                                                <div className="grid grid-cols-2 gap-2">
                                                    <button onClick={() => verAsientoContable(fac.id)} className="bg-slate-50 text-slate-600 hover:bg-slate-100 font-bold text-xs py-2.5 rounded-lg transition-colors border border-slate-200 flex items-center justify-center gap-1.5">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                                        Asiento
                                                    </button>
                                                    {fac.estado !== 'ANULADA' && (
                                                        <button onClick={() => iniciarCambio(fac)} className="bg-amber-50 text-amber-700 hover:bg-amber-100 font-bold text-xs py-2.5 rounded-lg transition-colors border border-amber-200 flex items-center justify-center gap-1.5">
                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                                            Reclasificar
                                                        </button>
                                                    )}
                                                    <button onClick={() => navigate(`/facturas/${fac.id}/auditoria`)} className={`bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold text-xs py-2.5 rounded-lg transition-colors border border-blue-200 flex items-center justify-center gap-1.5 ${fac.estado === 'ANULADA' ? 'col-span-1' : 'col-span-2'}`} title="Auditoría">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        Ver Auditoría
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* VERSIÓN ESCRITORIO (Tabla) */}
                                <div className="hidden md:block overflow-visible pb-10">
                                    <table className="min-w-full divide-y divide-slate-100">
                                        <thead className="bg-slate-50 border-b border-slate-200">
                                            {vistaActual === 1 ? (
                                                <tr>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider first:rounded-tl-xl">Fecha</th>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Proveedor</th>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Documento</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Monto Bruto</th>
                                                    <th className="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Estado</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider last:rounded-tr-xl">Acciones</th>
                                                </tr>
                                            ) : (
                                                <tr>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider first:rounded-tl-xl">Asiento N°</th>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Documento</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Neto</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">IVA</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Total</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider last:rounded-tr-xl">Acciones</th>
                                                </tr>
                                            )}
                                        </thead>
                                        <tbody className="bg-white divide-y divide-slate-50 text-sm">
                                            {facturas.map((fac) => (
                                                <tr key={fac.id} className="hover:bg-blue-50/30 transition-colors group">

                                                    {vistaActual === 1 ? (
                                                        <>
                                                            <td className="px-6 py-4 whitespace-nowrap text-slate-600 font-mono text-xs">{formatDate(fac.fecha_emision)}</td>
                                                            <td className="px-6 py-4">
                                                                <div className="font-bold text-slate-800">{fac.nombre_proveedor}</div>
                                                                <div className="text-xs text-slate-400 mt-0.5">{fac.rut_proveedor}</div>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <span className="font-mono text-slate-600 bg-slate-100 px-2 py-1 rounded text-xs border border-slate-200 font-bold">N° {fac.numero_factura}</span>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-right font-bold text-slate-800 text-base">
                                                                {formatCurrency(fac.monto_bruto)}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-center">
                                                                {fac.estado === 'REGISTRADA' ? (
                                                                    <span className="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] uppercase font-bold bg-amber-50 text-amber-700 border border-amber-200">Pendiente</span>
                                                                ) : fac.estado === 'PAGADA' ? (
                                                                    <span className="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] uppercase font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Pagada</span>
                                                                ) : fac.estado === 'ANULADA' ? (
                                                                    <span className="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] uppercase font-bold bg-red-50 text-red-700 border border-red-200">Anulada</span>
                                                                ) : (
                                                                    <span className="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] uppercase font-bold bg-gray-50 text-gray-700 border border-gray-200">{fac.estado}</span>
                                                                )}
                                                            </td>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <td className="px-6 py-4 whitespace-nowrap text-center">
                                                                {fac.codigo_asiento ? (
                                                                    <span className="text-blue-600 font-mono font-bold text-xs">{fac.codigo_asiento}</span>
                                                                ) : (
                                                                    <span className="text-slate-300 text-xs">-</span>
                                                                )}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap font-bold text-slate-700">
                                                                Fac. {fac.numero_factura}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-right text-slate-600">
                                                                {formatCurrency(fac.monto_neto)}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-right text-slate-600">
                                                                {formatCurrency(fac.monto_iva)}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-right font-bold text-slate-900 text-base">
                                                                {formatCurrency(fac.monto_bruto)}
                                                            </td>
                                                        </>
                                                    )}

                                                    <td className="px-6 py-4 whitespace-nowrap text-right relative menu-acciones-container">
                                                        <button
                                                            onClick={() => setMenuAbiertoId(menuAbiertoId === fac.id ? null : fac.id)}
                                                            className="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-full transition-all focus:outline-none"
                                                            title="Opciones"
                                                        >
                                                            <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" opacity=".3" /><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z" /></svg>
                                                        </button>

                                                        {menuAbiertoId === fac.id && (
                                                            <div className="absolute right-8 top-full mt-1 w-52 bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden z-[99] animate-fade-in text-left">
                                                                <ul className="text-sm font-medium text-slate-700 py-1">
                                                                    {fac.estado === 'REGISTRADA' && (
                                                                        <li>
                                                                            <button
                                                                                onClick={() => abrirModalPago(fac)}
                                                                                className="w-full text-left px-4 py-2.5 hover:bg-emerald-50 hover:text-emerald-700 transition-colors flex items-center gap-3 font-bold text-emerald-600 border-b border-slate-100"
                                                                            >
                                                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                                Pagar Factura
                                                                            </button>
                                                                        </li>
                                                                    )}

                                                                    <li>
                                                                        <button
                                                                            onClick={() => verAsientoContable(fac.id)}
                                                                            className="w-full text-left px-4 py-2.5 hover:bg-slate-50 hover:text-blue-600 transition-colors flex items-center gap-3"
                                                                        >
                                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                                                            Ver Asiento
                                                                        </button>
                                                                    </li>
                                                                    {fac.estado !== 'ANULADA' && (
                                                                        <li>
                                                                            <button
                                                                                onClick={() => iniciarCambio(fac)}
                                                                                className="w-full text-left px-4 py-2.5 hover:bg-slate-50 hover:text-amber-600 transition-colors flex items-center gap-3"
                                                                            >
                                                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                                                                Reclasificar
                                                                            </button>
                                                                        </li>
                                                                    )}
                                                                    <li>
                                                                        <button
                                                                            onClick={() => {
                                                                                setMenuAbiertoId(null);
                                                                                navigate(`/facturas/${fac.id}/auditoria`);
                                                                            }}
                                                                            className="w-full text-left px-4 py-2.5 hover:bg-slate-50 hover:text-emerald-600 transition-colors flex items-center gap-3 border-t border-slate-100"
                                                                        >
                                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                            Ver Auditoría
                                                                        </button>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                        {pagination.totalPages > 1 && (
                            <div className="bg-slate-50 px-6 py-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4 mt-auto rounded-b-xl">
                                <button
                                    disabled={pagination.page === 1}
                                    onClick={() => setPagination(prev => ({ ...prev, page: prev.page - 1 }))}
                                    className="w-full sm:w-auto px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100 disabled:opacity-50 transition-colors shadow-sm flex items-center justify-center gap-2"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"></path></svg> Anterior
                                </button>
                                <span className="text-sm font-medium text-slate-500">Página <span className="font-bold text-slate-800">{pagination.page}</span> de {pagination.totalPages}</span>
                                <button
                                    disabled={pagination.page === pagination.totalPages}
                                    onClick={() => setPagination(prev => ({ ...prev, page: prev.page + 1 }))}
                                    className="w-full sm:w-auto px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100 disabled:opacity-50 transition-colors shadow-sm flex items-center justify-center gap-2"
                                >
                                    Siguiente <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            </div>
                        )}
                    </div>
                </>
            ) : (
                /* VISTA 3: RECLASIFICACIÓN */
                <div className="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-visible animate-fade-in flex flex-col">
                    <div className="bg-slate-50 p-4 md:p-8 border-b border-slate-200 rounded-t-2xl">
                        <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                            <div>
                                <h2 className="text-xl font-black text-slate-800">Asiento Contable N° {facturaActiva?.codigo_asiento}</h2>
                                <p className="text-sm text-slate-500 font-medium">Factura N° {facturaActiva?.numero_factura} - {facturaActiva?.nombre_proveedor}</p>
                            </div>
                            <button onClick={() => setVistaActual(1)} className="w-full md:w-auto text-slate-500 hover:text-red-500 transition-colors px-4 py-2.5 bg-white rounded-lg border border-slate-200 shadow-sm font-bold text-xs uppercase tracking-wide flex items-center justify-center gap-1.5">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg> Cancelar
                            </button>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 bg-white p-4 md:p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Fecha del Ajuste</label>
                                <input
                                    type="date"
                                    className="w-full border border-slate-300 rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 font-medium text-slate-700 transition-all text-sm"
                                    value={formCambio.fechaContableCambio}
                                    min={facturaActiva?.fecha_emision}
                                    onChange={e => setFormCambio({ ...formCambio, fechaContableCambio: e.target.value })}
                                />
                                <span className="text-[10px] text-slate-400 mt-1.5 block leading-tight">El reverso y el cargo quedarán en esta fecha.</span>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Glosa de Auditoría</label>
                                <input
                                    type="text"
                                    className="w-full border border-slate-300 rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 font-medium text-slate-700 transition-all text-sm"
                                    value={formCambio.nuevaGlosa}
                                    onChange={e => setFormCambio({ ...formCambio, nuevaGlosa: e.target.value })}
                                    placeholder="Motivo del cambio..."
                                />
                                <span className="text-[10px] text-slate-400 mt-1.5 block leading-tight">Justificación obligatoria para el historial.</span>
                            </div>
                        </div>
                    </div>

                    <div className="p-4 md:p-8 flex-1 bg-white">
                        <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Líneas del Asiento Original</h3>

                        {loadingReclasificacion ? (
                            <div className="text-center p-10 text-slate-400">
                                <svg className="animate-spin w-8 h-8 mx-auto mb-3 text-blue-500" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <p>Cargando detalles...</p>
                            </div>
                        ) : (
                            <div className="border border-slate-200 rounded-xl overflow-visible shadow-sm">
                                {/* Tabla Desktop, Tarjetas Móvil para Reclasificación */}
                                <div className="hidden md:block overflow-visible pb-24">
                                    <table className="w-full text-left">
                                        <thead className="bg-slate-900 text-white text-xs uppercase tracking-wider font-bold">
                                            <tr>
                                                <th className="p-4 w-1/3 first:rounded-tl-xl">Cuenta Original</th>
                                                <th className="p-4 text-right w-32">Debe</th>
                                                <th className="p-4 text-right w-32 border-r border-slate-700">Haber</th>
                                                <th className="p-4 bg-slate-800 last:rounded-tr-xl">Nueva Imputación (Buscador)</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 text-sm font-medium">
                                            {asientoReclasificacion?.detalles?.map((linea, index) => {
                                                const isBloqueada = linea.cuenta_contable === '110001' || linea.cuenta_contable === '210101' || parseFloat(linea.haber) > 0;
                                                return (
                                                    <tr key={index} className={isBloqueada ? 'bg-slate-50 opacity-80' : 'bg-white hover:bg-blue-50/20'}>
                                                        <td className="p-4">
                                                            <div className="text-slate-800 font-bold">{linea.nombre_cuenta}</div>
                                                            <div className="text-xs text-slate-500 font-mono mt-0.5 bg-white border border-slate-200 px-2 py-0.5 rounded w-max">{linea.cuenta_contable}</div>
                                                        </td>
                                                        <td className="p-4 text-right font-mono text-emerald-600">{parseFloat(linea.debe) > 0 ? formatCurrency(linea.debe) : '-'}</td>
                                                        <td className="p-4 text-right font-mono text-red-600 border-r border-slate-100">{parseFloat(linea.haber) > 0 ? formatCurrency(linea.haber) : '-'}</td>
                                                        <td className="p-4">
                                                            {isBloqueada ? (
                                                                <div onClick={intentarCambioProhibido} className="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase cursor-pointer hover:text-red-500 transition-colors bg-slate-100 border border-slate-200 w-fit px-3 py-2 rounded-lg">
                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg> Cuenta Restringida
                                                                </div>
                                                            ) : (
                                                                <BuscadorCuentas cuentas={cuentasPlan} valor={formCambio.nuevaCuenta} onChange={(val) => setFormCambio({ ...formCambio, nuevaCuenta: val })} />
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Tarjetas para Reclasificación en Móvil */}
                                <div className="md:hidden flex flex-col divide-y divide-slate-100 pb-10">
                                    {asientoReclasificacion?.detalles?.map((linea, index) => {
                                        const isBloqueada = linea.cuenta_contable === '110001' || linea.cuenta_contable === '210101' || parseFloat(linea.haber) > 0;
                                        return (
                                            <div key={index} className={`p-4 flex flex-col gap-3 ${isBloqueada ? 'bg-slate-50' : 'bg-white'}`}>
                                                <div className="flex justify-between items-start">
                                                    <div>
                                                        <div className="text-slate-800 font-bold text-sm">{linea.nombre_cuenta}</div>
                                                        <div className="text-xs text-slate-500 font-mono mt-1">{linea.cuenta_contable}</div>
                                                    </div>
                                                    <div className="text-right">
                                                        {parseFloat(linea.debe) > 0 && <div className="text-emerald-600 font-mono font-bold text-sm">+{formatCurrency(linea.debe)}</div>}
                                                        {parseFloat(linea.haber) > 0 && <div className="text-red-600 font-mono font-bold text-sm">-{formatCurrency(linea.haber)}</div>}
                                                    </div>
                                                </div>
                                                <div className="pt-2 border-t border-slate-100 overflow-visible relative">
                                                    <p className="text-[10px] font-bold text-slate-400 uppercase mb-1.5">Mover a cuenta:</p>
                                                    {isBloqueada ? (
                                                        <div onClick={intentarCambioProhibido} className="flex items-center justify-center gap-2 text-slate-400 text-xs font-bold uppercase cursor-pointer bg-slate-100 border border-slate-200 w-full py-2.5 rounded-lg">
                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg> Restringida
                                                        </div>
                                                    ) : (
                                                        <BuscadorCuentas cuentas={cuentasPlan} valor={formCambio.nuevaCuenta} onChange={(val) => setFormCambio({ ...formCambio, nuevaCuenta: val })} />
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="bg-slate-50 p-4 md:p-6 border-t border-slate-200 mt-auto rounded-b-2xl">
                        <button
                            onClick={ejecutarReclasificacion}
                            disabled={!formCambio.nuevaCuenta}
                            className="w-full md:w-auto md:float-right px-6 md:px-10 py-3.5 bg-emerald-600 text-white rounded-xl font-black shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 hover:shadow-emerald-600/40 disabled:opacity-50 disabled:shadow-none transition-all flex items-center justify-center gap-2"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg> Confirmar Ajuste
                        </button>
                        <div className="clear-both"></div>
                    </div>
                </div>
            )}

            <ModalPagoFactura
                isOpen={modalPagoOpen}
                onClose={() => setModalPagoOpen(false)}
                factura={facturaSeleccionada}
                onPagoExitoso={() => ejecutarBusqueda(false)}
            />

        </div>
    );
};

export default HistorialFacturas;