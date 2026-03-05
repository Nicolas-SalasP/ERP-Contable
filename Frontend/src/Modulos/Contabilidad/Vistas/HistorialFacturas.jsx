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
                    <i className="fas fa-search"></i>
                </div>
            </div>

            {abierto && (
                <div className="absolute z-50 w-full mt-1 bg-white border border-slate-200 shadow-2xl max-h-56 overflow-y-auto rounded-lg rounded-tl-none animate-fade-in">
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
        <div className="fixed inset-0 bg-slate-900 bg-opacity-80 flex items-center justify-center z-50 p-4 backdrop-blur-sm animate-fade-in">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh] border border-slate-200">
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
                            <div className="flex-1 overflow-auto">
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
                    <button onClick={onClose} className="px-6 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium transition-colors shadow-lg text-sm uppercase tracking-wide w-full md:w-auto">Cerrar</button>
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
            if (res.success) setAsientoData(res.data);
            else { alert('No se pudo cargar la información del asiento.'); setModalOpen(false); }
        } catch (error) {
            alert('Error al obtener el asiento contable.');
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
        <div className="max-w-7xl mx-auto font-sans text-slate-800 pb-10">
            <ModalAsiento isOpen={modalOpen} onClose={() => setModalOpen(false)} data={asientoData} loading={loadingAsiento} />

            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        {vistaActual === 3 ? 'Workbench de Reclasificación' : 'Historial de Compras'}
                    </h1>
                    <p className="text-slate-500 text-sm mt-1">
                        {vistaActual === 3 ? `Ajuste intra-asiento Fac. ${facturaActiva?.numero_factura}` : 'Cuenta Corriente y Auditoría Contable'}
                    </p>
                </div>

                {vistaActual !== 3 && (
                    <div className="flex items-center bg-white p-1.5 rounded-lg border border-slate-200 shadow-sm">
                        <button
                            onClick={() => setVistaActual(1)}
                            className={`px-4 py-2 rounded-md text-sm font-bold transition-all ${vistaActual === 1 ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100'}`}
                        >
                            <i className="fas fa-list mr-2"></i> Detallada
                        </button>
                        <button
                            onClick={() => setVistaActual(2)}
                            className={`px-4 py-2 rounded-md text-sm font-bold transition-all ${vistaActual === 2 ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100'}`}
                        >
                            <i className="fas fa-table mr-2"></i> Contable
                        </button>
                    </div>
                )}
            </div>

            {vistaActual !== 3 ? (
                <>
                    <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 mb-8" ref={searchRef}>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div className="relative md:col-span-2">
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Proveedor</label>
                                <input
                                    type="text"
                                    className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700"
                                    placeholder="RUT o Razón Social..."
                                    value={busqueda}
                                    onChange={handleBusquedaChange}
                                    onFocus={() => { if (busqueda) setMostrarSugerencias(true); }}
                                    onKeyDown={(e) => e.key === 'Enter' && ejecutarBusqueda(true)}
                                />
                                {mostrarSugerencias && sugerencias.length > 0 && (
                                    <div className="absolute top-full left-0 w-full bg-white border border-slate-200 mt-2 rounded-xl shadow-2xl max-h-60 overflow-y-auto z-50">
                                        {sugerencias.map(p => (
                                            <div key={p.id} onClick={() => seleccionarProveedor(p)} className="p-4 hover:bg-blue-50 cursor-pointer border-b last:border-0 border-slate-100 transition-colors group">
                                                <p className="font-bold text-slate-800 group-hover:text-blue-700">{p.razon_social}</p>
                                                <div className="flex justify-between items-center mt-1">
                                                    <span className="text-sm text-slate-500 font-mono">{p.rut}</span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">N° Documento</label>
                                <input
                                    type="text"
                                    className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700"
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
                                        className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700"
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
                                    className="px-6 py-3 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 shadow-lg disabled:opacity-70 transition-all flex items-center justify-center mb-[1px]"
                                >
                                    {loading ? <i className="fas fa-circle-notch fa-spin"></i> : 'Filtrar'}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-visible min-h-[400px] flex flex-col justify-between">
                        <div>
                            {!searched && facturas.length === 0 ? (
                                <div className="flex flex-col items-center justify-center h-[300px] text-slate-400">
                                    <i className="fas fa-search text-5xl mb-4 opacity-20"></i>
                                    <p className="text-lg font-medium">Usa los filtros para buscar facturas.</p>
                                </div>
                            ) : facturas.length === 0 ? (
                                <div className="flex flex-col items-center justify-center h-[300px] text-slate-400">
                                    <i className="fas fa-box-open text-5xl mb-4 opacity-20"></i>
                                    <p className="text-lg font-medium">No se encontraron documentos.</p>
                                </div>
                            ) : (
                                <div className="overflow-visible">
                                    <table className="min-w-full divide-y divide-slate-100">
                                        <thead className="bg-slate-50">
                                            {vistaActual === 1 ? (
                                                <tr>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Fecha</th>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Proveedor</th>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Documento</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Monto Bruto</th>
                                                    <th className="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Estado</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Acciones</th>
                                                </tr>
                                            ) : (
                                                <tr>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Asiento N°</th>
                                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Documento</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Neto</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">IVA</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Total</th>
                                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Acciones</th>
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
                                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">Pendiente</span>
                                                                ) : fac.estado === 'PAGADA' ? (
                                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">Pagada</span>
                                                                ) : fac.estado === 'ANULADA' ? (
                                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200">Anulada</span>
                                                                ) : (
                                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-800 border border-gray-200">{fac.estado}</span>
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
                                                            <div className="absolute right-8 top-10 mt-1 w-52 bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden z-50 animate-fade-in text-left">
                                                                <ul className="text-sm font-medium text-slate-700 py-1">
                                                                    {fac.estado === 'REGISTRADA' && (
                                                                        <li>
                                                                            <button
                                                                                onClick={() => abrirModalPago(fac)}
                                                                                className="w-full text-left px-4 py-2.5 hover:bg-emerald-50 hover:text-emerald-700 transition-colors flex items-center gap-3 font-bold text-emerald-600 border-b border-slate-100"
                                                                            >
                                                                                <i className="fas fa-money-bill-wave w-4 text-center"></i> Pagar Factura
                                                                            </button>
                                                                        </li>
                                                                    )}

                                                                    <li>
                                                                        <button
                                                                            onClick={() => verAsientoContable(fac.id)}
                                                                            className="w-full text-left px-4 py-2.5 hover:bg-slate-50 hover:text-blue-600 transition-colors flex items-center gap-3"
                                                                        >
                                                                            <i className="fas fa-book w-4 text-center"></i> Ver Asiento
                                                                        </button>
                                                                    </li>
                                                                    {fac.estado !== 'ANULADA' && (
                                                                        <li>
                                                                            <button
                                                                                onClick={() => iniciarCambio(fac)}
                                                                                className="w-full text-left px-4 py-2.5 hover:bg-slate-50 hover:text-amber-600 transition-colors flex items-center gap-3"
                                                                            >
                                                                                <i className="fas fa-exchange-alt w-4 text-center"></i> Reclasificar
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
                                                                            <i className="fas fa-history w-4 text-center"></i> Ver Auditoría
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
                            )}
                        </div>

                        {pagination.totalPages > 1 && (
                            <div className="bg-slate-50 px-6 py-3 border-t border-slate-200 flex items-center justify-between">
                                <button
                                    disabled={pagination.page === 1}
                                    onClick={() => setPagination(prev => ({ ...prev, page: prev.page - 1 }))}
                                    className="px-3 py-1 bg-white border border-slate-300 rounded text-sm disabled:opacity-50"
                                >
                                    Anterior
                                </button>
                                <span className="text-sm text-slate-600">Página {pagination.page} de {pagination.totalPages}</span>
                                <button
                                    disabled={pagination.page === pagination.totalPages}
                                    onClick={() => setPagination(prev => ({ ...prev, page: prev.page + 1 }))}
                                    className="px-3 py-1 bg-white border border-slate-300 rounded text-sm disabled:opacity-50"
                                >
                                    Siguiente
                                </button>
                            </div>
                        )}
                    </div>
                </>
            ) : (
                <div className="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-visible animate-fade-in flex flex-col min-h-[600px]">
                    <div className="bg-slate-50 p-6 md:p-8 border-b border-slate-200 rounded-t-2xl">
                        <div className="flex justify-between items-start mb-6">
                            <div>
                                <h2 className="text-xl font-black text-slate-800">Asiento Contable N° {facturaActiva?.codigo_asiento}</h2>
                                <p className="text-sm text-slate-500 font-medium">Factura N° {facturaActiva?.numero_factura} - {facturaActiva?.nombre_proveedor}</p>
                            </div>
                            <button onClick={() => setVistaActual(1)} className="text-slate-400 hover:text-red-500 transition-colors px-4 py-2 bg-white rounded-lg border border-slate-200 shadow-sm font-bold text-xs uppercase tracking-wide">
                                <i className="fas fa-times mr-1"></i> Cancelar
                            </button>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Fecha del Ajuste (Contable)</label>
                                <input
                                    type="date"
                                    className="w-full border border-slate-300 rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 font-medium text-slate-700 transition-all"
                                    value={formCambio.fechaContableCambio}
                                    min={facturaActiva?.fecha_emision}
                                    onChange={e => setFormCambio({ ...formCambio, fechaContableCambio: e.target.value })}
                                />
                                <span className="text-[10px] text-slate-400 mt-1 block">El reverso y el nuevo cargo quedarán registrados en esta fecha dentro del mismo asiento.</span>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Glosa de Auditoría</label>
                                <input
                                    type="text"
                                    className="w-full border border-slate-300 rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 font-medium text-slate-700 transition-all"
                                    value={formCambio.nuevaGlosa}
                                    onChange={e => setFormCambio({ ...formCambio, nuevaGlosa: e.target.value })}
                                    placeholder="Motivo del cambio..."
                                />
                                <span className="text-[10px] text-slate-400 mt-1 block">Justificación obligatoria que quedará guardada en el historial.</span>
                            </div>
                        </div>
                    </div>

                    <div className="p-6 md:p-8 flex-1 overflow-visible bg-white">
                        <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Líneas del Asiento Original</h3>

                        {loadingReclasificacion ? (
                            <div className="text-center p-10 text-slate-400"><i className="fas fa-circle-notch fa-spin text-2xl mb-2"></i><p>Cargando detalles del asiento...</p></div>
                        ) : (
                            <div className="border border-slate-200 rounded-xl overflow-visible shadow-sm">
                                <table className="w-full text-left overflow-visible">
                                    <thead className="bg-slate-900 text-white text-xs uppercase tracking-wider font-bold">
                                        <tr>
                                            <th className="p-4 w-1/3">Cuenta Original</th>
                                            <th className="p-4 text-right w-32">Debe</th>
                                            <th className="p-4 text-right w-32 border-r border-slate-700">Haber</th>
                                            <th className="p-4 bg-slate-800">Nueva Imputación (Buscador)</th>
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

                                                    <td className="p-4 overflow-visible">
                                                        {isBloqueada ? (
                                                            <div
                                                                onClick={intentarCambioProhibido}
                                                                className="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase cursor-pointer hover:text-red-500 transition-colors bg-slate-100 border border-slate-200 w-fit px-3 py-2 rounded-lg"
                                                            >
                                                                <i className="fas fa-lock"></i> Cuenta Restringida
                                                            </div>
                                                        ) : (
                                                            <BuscadorCuentas
                                                                cuentas={cuentasPlan}
                                                                valor={formCambio.nuevaCuenta}
                                                                onChange={(val) => setFormCambio({ ...formCambio, nuevaCuenta: val })}
                                                            />
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div className="bg-slate-50 p-6 border-t border-slate-200 flex justify-end gap-4 rounded-b-2xl">
                        <button
                            onClick={ejecutarReclasificacion}
                            disabled={!formCambio.nuevaCuenta}
                            className="px-10 py-3.5 bg-emerald-600 text-white rounded-xl font-black shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 hover:shadow-emerald-600/40 disabled:opacity-50 disabled:shadow-none transition-all flex items-center gap-2"
                        >
                            <i className="fas fa-save text-lg"></i> Confirmar y Generar Partida Doble
                        </button>
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