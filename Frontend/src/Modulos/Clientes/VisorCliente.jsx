import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../../Configuracion/api';
import EstadoCarga from '../../Componentes/EstadoCarga';
import ModalDocumentosFactura from '../../Componentes/ModalDocumentosFactura';
import Swal from 'sweetalert2';
import { formatearMoneda } from '../../Utilidades/formato';

const formatCurrency = formatearMoneda;

const VisorCliente = () => {
    const { id } = useParams();
    const navigate = useNavigate();

    const [loading, setLoading] = useState(false);
    const [datos, setDatos] = useState(null);

    const [modalAbierto, setModalAbierto] = useState(false);
    const [listaClientes, setListaClientes] = useState([]);
    const [terminoBusqueda, setTerminoBusqueda] = useState('');
    const inputBusquedaRef = useRef(null);

    const [filtroTipo, setFiltroTipo] = useState('TODOS');
    const [filtroNumero, setFiltroNumero] = useState('');
    const [filtroEstado, setFiltroEstado] = useState('');

    useEffect(() => {
        if (id) {
            cargarFicha(id);
        } else {
            setDatos(null);
        }
    }, [id]);

    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.ctrlKey && e.key === '2') {
                e.preventDefault();
                abrirBuscador();
            }
            if (e.key === 'Escape') {
                setModalAbierto(false);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    useEffect(() => {
        if (modalAbierto && inputBusquedaRef.current) {
            inputBusquedaRef.current.focus();
            if (listaClientes.length === 0) cargarListaCompleta();
        }
    }, [modalAbierto]);

    const cargarListaCompleta = async () => {
        try {
            const res = await api.get('/clientes');
            if (res.success) setListaClientes(res.data);
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'No se pudo cargar la lista de clientes', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
        }
    };

    const cargarFicha = async (clienteId) => {
        setLoading(true);
        try {
            const res = await api.get(`/clientes/ficha/${clienteId}`);
            if (res.success && res.data) {
                setDatos(res.data);
            } else {
                throw new Error("Datos inválidos devueltos por la API.");
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se encontró la ficha del cliente.' });
            navigate('/clientes');
        } finally {
            setLoading(false);
        }
    };

    const abrirBuscador = () => { setTerminoBusqueda(''); setModalAbierto(true); };
    const seleccionarCliente = (clienteId) => { setModalAbierto(false); navigate(`/clientes/visor/${clienteId}`); };

    const [facturaDocumentos, setFacturaDocumentos] = useState(null);

    const clientesFiltrados = listaClientes.filter(c => {
        const b = terminoBusqueda.toLowerCase();
        return c.razon_social?.toLowerCase().includes(b) || (c.rut && c.rut.toLowerCase().includes(b));
    });

    const modalSpotlightJSX = modalAbierto && (
        <div className="fixed inset-0 bg-slate-900/80 z-[100] flex items-start justify-center pt-[10vh] p-4 animate-fade-in" onClick={() => setModalAbierto(false)}>
            <div className="bg-white dark:bg-slate-800 w-full max-w-2xl rounded-xl shadow-xl overflow-hidden flex flex-col max-h-[80vh] border border-slate-300 dark:border-slate-700" onClick={e => e.stopPropagation()}>
                <div className="flex items-center px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                    <i className="fas fa-search text-slate-500 text-xl mr-4"></i>
                    <input ref={inputBusquedaRef} type="text" className="flex-1 bg-transparent border-none outline-none text-lg font-bold text-slate-800 dark:text-slate-200 placeholder-slate-400" placeholder="Buscar por RUT o Nombre..." value={terminoBusqueda} onChange={(e) => setTerminoBusqueda(e.target.value)} />
                    <button onClick={() => setModalAbierto(false)} className="bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-600 text-xs font-bold px-3 py-1 rounded transition-colors">ESC</button>
                </div>
                <div className="overflow-y-auto p-2">
                    {clientesFiltrados.map(cli => (
                        <div key={cli.id} onClick={() => seleccionarCliente(cli.id)} className="flex items-center justify-between p-4 hover:bg-blue-50 dark:hover:bg-slate-700 cursor-pointer border-b border-slate-50 dark:border-slate-700 transition-colors">
                            <div className="flex items-center gap-4">
                                <div className="w-8 h-8 rounded bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-600"><i className="fas fa-user"></i></div>
                                <div>
                                    <p className="font-bold text-slate-800 dark:text-slate-200 text-sm">{cli.razon_social}</p>
                                    <p className="text-[10px] text-slate-500 dark:text-slate-400 font-mono">RUT: {cli.rut || 'N/A'}</p>
                                </div>
                            </div>
                            <i className="fas fa-chevron-right text-slate-300"></i>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );

    if (loading && !datos) {
        return (
            <EstadoCarga
                cargando={true}
                mensajeCargando="Cargando datos del cliente..."
                tamano="completo"
                color="emerald"
            />
        );
    }

    if (!datos) {
        return (
            <div className="max-w-7xl mx-auto p-4 md:p-8 font-sans h-full flex flex-col">
                {modalSpotlightJSX}
                <div className="flex-1 flex flex-col items-center justify-center text-center mt-20">
                    <div className="w-20 h-20 bg-slate-100 dark:bg-slate-800 text-slate-400 rounded-lg flex items-center justify-center text-3xl mb-6 border border-slate-200 dark:border-slate-700">
                        <i className="fas fa-address-card"></i>
                    </div>
                    <h1 className="text-3xl font-bold text-slate-800 dark:text-slate-200 mb-2">Visor del Cliente</h1>
                    <p className="text-slate-500 dark:text-slate-400 text-sm max-w-lg mb-8">
                        Consulta el historial de facturas, notas de crédito/débito y saldo por cobrar del cliente seleccionado.
                    </p>
                    <button onClick={abrirBuscador} className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded shadow-sm transition-colors flex items-center gap-2">
                        <i className="fas fa-search"></i> Seleccionar Cliente
                    </button>
                </div>
            </div>
        );
    }

    const cliente = datos?.cliente || {};
    const facturas = datos?.facturas || [];

    const historialCombinado = facturas.map(f => {
        const esNC = f.tipo_documento === 'NOTA_CREDITO';
        const esND = f.tipo_documento === 'NOTA_DEBITO';
        const prefijo = esNC ? 'NC' : esND ? 'ND' : 'Factura';
        return {
            ...f,
            _tipo: esNC ? 'NOTA_CREDITO' : esND ? 'NOTA_DEBITO' : 'FACTURA',
            _fechaOrden: new Date(f.fecha_emision),
            _documento: f.numero_factura ? `${prefijo} #${f.numero_factura}` : `${prefijo} S/N`,
            // NC reduce lo que el cliente debe (abono); Factura y ND lo aumentan (cargo).
            _cargo: esNC ? 0 : parseFloat(f.monto_bruto || 0),
            _abono: esNC ? parseFloat(f.monto_bruto || 0) : 0,
            _estado: f.estado,
            _cantidadDocumentos: f.documentos_adjuntos_count || 0
        };
    }).sort((a, b) => b._fechaOrden - a._fechaOrden);

    const facturasDeuda = facturas.filter(f => f.estado !== 'PAGADA' && f.estado !== 'ANULADA' && f.tipo_documento !== 'NOTA_CREDITO');
    const ncVigentes = facturas.filter(f => f.estado !== 'APLICADA' && f.estado !== 'ANULADA' && f.tipo_documento === 'NOTA_CREDITO');

    const totalDeuda = facturasDeuda.reduce((sum, f) => sum + parseFloat(f.monto_bruto), 0);
    const totalActivos = ncVigentes.reduce((sum, f) => sum + parseFloat(f.monto_bruto), 0);

    const saldoNeto = totalDeuda - totalActivos;
    const esDeudorCliente = saldoNeto > 0;
    const clienteAFavor = saldoNeto < 0;

    const historialFiltrado = historialCombinado.filter(item => {
        let pasaTipo = filtroTipo === 'TODOS' ? true : item._tipo === filtroTipo;
        let pasaNumero = filtroNumero ? item._documento.toLowerCase().includes(filtroNumero.toLowerCase()) : true;
        let pasaEstado = true;

        if (filtroEstado) {
            if (filtroEstado === 'VIGENTES') {
                pasaEstado =
                    ((item._tipo === 'FACTURA' || item._tipo === 'NOTA_DEBITO') && item._estado !== 'PAGADA' && item._estado !== 'ANULADA') ||
                    (item._tipo === 'NOTA_CREDITO' && item._estado !== 'APLICADA' && item._estado !== 'ANULADA');
            } else if (filtroEstado === 'CERRADOS') {
                pasaEstado =
                    ((item._tipo === 'FACTURA' || item._tipo === 'NOTA_DEBITO') && item._estado === 'PAGADA') ||
                    (item._tipo === 'NOTA_CREDITO' && item._estado === 'APLICADA');
            } else if (filtroEstado === 'ANULADOS') {
                pasaEstado = item._estado === 'ANULADA';
            }
        }
        return pasaTipo && pasaNumero && pasaEstado;
    });

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 font-sans text-slate-800 dark:text-slate-200 animate-fade-in pb-20">
            {modalSpotlightJSX}

            <div className="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8">
                <div>
                    <div className="flex items-center gap-2 mb-2">
                        <span className="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2 py-0.5 rounded uppercase border border-indigo-200">
                            Cuenta Corriente
                        </span>
                        <span className="text-slate-300 text-xs font-bold px-2">|</span>
                        <button onClick={() => navigate('/clientes')} className="text-slate-500 hover:text-indigo-600 font-bold text-xs flex items-center gap-1 transition-colors">
                            Ver Directorio
                        </button>
                    </div>
                    <h1 className="text-3xl font-black text-slate-900 dark:text-slate-100 tracking-tight">Ficha 360° del Cliente</h1>
                </div>

                <div className="flex flex-wrap gap-2">
                    <button onClick={abrirBuscador} className="bg-blue-600 text-white hover:bg-blue-700 font-bold py-2 px-4 rounded text-sm transition-colors flex items-center gap-2 shadow-sm">
                        <i className="fas fa-search"></i> Buscar Cliente
                    </button>
                </div>
            </div>

            <div className="bg-slate-900 rounded-2xl p-8 text-white shadow-xl mb-8 relative overflow-hidden border border-slate-800">
                <div className="relative z-10 flex flex-col lg:flex-row justify-between gap-10">
                    <div className="space-y-4 flex-1">
                        <div>
                            <h2 className="text-3xl md:text-4xl font-black tracking-tight mb-2">{cliente.razon_social}</h2>
                            <p className="text-slate-400 font-mono text-sm"><i className="fas fa-fingerprint mr-2"></i>RUT: {cliente.rut || 'Extranjero'}</p>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-slate-700">
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-1">Dirección</p>
                                <p className="text-sm font-medium text-slate-200">{cliente.direccion || 'No registrada'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-1">Contacto Principal</p>
                                <p className="text-sm font-medium text-slate-200">{cliente.contacto_email || cliente.email || 'Sin correo'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-1">Teléfono</p>
                                <p className="text-sm font-medium text-slate-200">{cliente.telefono || 'Sin teléfono'}</p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col justify-center gap-4 shrink-0 border-t border-slate-800 pt-4 lg:border-t-0 lg:border-l lg:min-w-[280px] lg:pt-0 lg:pl-8">
                        <div className="bg-slate-800 p-5 rounded-xl border border-slate-700 relative overflow-hidden">
                            <div className={`absolute top-0 left-0 w-1 h-full ${esDeudorCliente ? 'bg-emerald-500' : clienteAFavor ? 'bg-rose-500' : 'bg-slate-500'}`}></div>
                            <p className="text-[10px] text-slate-400 uppercase font-bold mb-1">Saldo Contable Actual</p>
                            <div className="flex items-baseline gap-2">
                                <p className={`text-3xl font-mono font-bold tracking-tight ${esDeudorCliente ? 'text-emerald-400' : clienteAFavor ? 'text-rose-400' : 'text-slate-300'}`}>
                                    {formatCurrency(Math.abs(saldoNeto))}
                                </p>
                                <span className="text-xs font-bold uppercase text-slate-500">
                                    {esDeudorCliente ? '(Por Cobrar)' : clienteAFavor ? '(A Favor del Cliente)' : ''}
                                </span>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4 text-sm px-1">
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-0.5">Deuda (Activo)</p>
                                <p className="font-mono font-bold text-slate-300">{formatCurrency(totalDeuda)}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-0.5">A Favor (Pasivo)</p>
                                <p className="font-mono font-bold text-slate-300">{formatCurrency(totalActivos)}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div className="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 p-4 md:p-6 flex justify-between items-center">
                    <div>
                        <h2 className="text-base font-bold text-slate-800 dark:text-slate-200">Cartola de Movimientos</h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400">Estado de cuenta detallado y cronológico.</p>
                    </div>
                </div>

                <div className="bg-white dark:bg-slate-800 p-4 border-b border-slate-100 dark:border-slate-700 flex flex-wrap gap-4 items-center">
                    <div className="flex-1 min-w-full sm:min-w-[200px]">
                        <div className="relative w-full">
                            <i className="fas fa-filter absolute left-3 top-3 text-slate-400"></i>
                            <input type="text" placeholder="Filtrar N° Doc..." value={filtroNumero} onChange={(e) => setFiltroNumero(e.target.value)} className="w-full pl-9 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded text-sm outline-none focus:border-blue-500 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300" />
                        </div>
                    </div>
                    <select value={filtroTipo} onChange={(e) => setFiltroTipo(e.target.value)} className="w-48 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded text-sm text-slate-700 dark:text-slate-300 outline-none focus:border-blue-500 bg-white dark:bg-slate-700">
                        <option value="TODOS">Todos los Tipos</option>
                        <option value="FACTURA">Solo Facturas</option>
                        <option value="NOTA_CREDITO">Solo Notas de Crédito</option>
                        <option value="NOTA_DEBITO">Solo Notas de Débito</option>
                    </select>
                    <select value={filtroEstado} onChange={(e) => setFiltroEstado(e.target.value)} className="w-48 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded text-sm text-slate-700 dark:text-slate-300 outline-none focus:border-blue-500 bg-white dark:bg-slate-700">
                        <option value="">Todos los Estados</option>
                        <option value="VIGENTES">Pendientes / Vigentes</option>
                        <option value="CERRADOS">Pagados / Aplicados</option>
                        <option value="ANULADOS">Anulados</option>
                    </select>
                </div>

                <div className="overflow-x-auto min-h-[300px]">
                    {historialCombinado.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 text-slate-400">
                            <i className="fas fa-folder-open text-3xl mb-2"></i>
                            <p className="font-bold text-sm">Cartola en blanco</p>
                        </div>
                    ) : (
                        <table className="w-full text-left text-sm whitespace-nowrap">
                            <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th className="px-6 py-3 font-bold text-slate-500 dark:text-slate-400 text-xs">Fecha</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 dark:text-slate-400 text-xs">Documento</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 dark:text-slate-400 text-xs text-right">Cargos (Por Cobrar)</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 dark:text-slate-400 text-xs text-right">Abonos (A Favor)</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 dark:text-slate-400 text-xs text-center">Estado</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 dark:text-slate-400 text-xs text-center">Documentos</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                {historialFiltrado.map((item, i) => (
                                    <tr key={`${item._tipo}-${item.id}-${i}`} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                        <td className="px-6 py-3 text-slate-600 dark:text-slate-400 font-mono text-xs">
                                            {item._fechaOrden.toLocaleDateString('es-CL')}
                                        </td>
                                        <td className="px-6 py-3">
                                            <div className="flex items-center gap-2">
                                                {item._tipo === 'FACTURA'
                                                    ? <span className="bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400 text-[10px] font-bold px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-600">FAC</span>
                                                    : item._tipo === 'NOTA_CREDITO'
                                                    ? <span className="bg-purple-50 text-purple-600 text-[10px] font-bold px-1.5 py-0.5 rounded border border-purple-200">NC</span>
                                                    : <span className="bg-amber-50 text-amber-600 text-[10px] font-bold px-1.5 py-0.5 rounded border border-amber-200">ND</span>
                                                }
                                                <span className="font-bold text-slate-800 dark:text-slate-200">
                                                    {item._documento}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                                            {item._cargo > 0 ? formatCurrency(item._cargo) : '-'}
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                                            {item._abono > 0 ? formatCurrency(item._abono) : '-'}
                                        </td>
                                        <td className="px-6 py-3 text-center">
                                            {item._estado === 'PAGADA' || item._estado === 'APLICADA' ? (
                                                <span className="text-slate-400 font-bold text-[10px] uppercase">Cerrado</span>
                                            ) : item._estado === 'ANULADA' ? (
                                                <span className="text-slate-300 font-bold text-[10px] uppercase line-through">Anulado</span>
                                            ) : (
                                                <span className="text-blue-600 font-bold text-[10px] uppercase">Vigente</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-3 text-center">
                                            <button
                                                type="button"
                                                onClick={() => setFacturaDocumentos(item)}
                                                className="text-blue-600 hover:text-blue-800 font-bold text-xs transition-colors"
                                            >
                                                Documentos{item._cantidadDocumentos > 0 ? ` (${item._cantidadDocumentos})` : ''}
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            {facturaDocumentos && (
                <ModalDocumentosFactura
                    facturaId={facturaDocumentos.id}
                    etiqueta={facturaDocumentos._documento}
                    onCerrar={() => setFacturaDocumentos(null)}
                    onCambio={() => cargarFicha(id)}
                />
            )}
        </div>
    );
};

export default VisorCliente;
