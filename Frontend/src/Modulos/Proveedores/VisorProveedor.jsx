import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const VisorProveedor = () => {
    const { id } = useParams();
    const navigate = useNavigate();

    const [loading, setLoading] = useState(false);
    const [datos, setDatos] = useState(null);

    // --- MODALES Y FILTROS ---
    const [modalAbierto, setModalAbierto] = useState(false);
    const [listaProveedores, setListaProveedores] = useState([]);
    const [terminoBusqueda, setTerminoBusqueda] = useState('');
    const inputBusquedaRef = useRef(null);

    const [filtroTipo, setFiltroTipo] = useState('TODOS');
    const [filtroNumero, setFiltroNumero] = useState('');
    const [filtroEstado, setFiltroEstado] = useState('');

    const [modalAnticipoAbierto, setModalAnticipoAbierto] = useState(false);
    const [formAnticipo, setFormAnticipo] = useState({ fecha: new Date().toISOString().split('T')[0], monto: '', referencia: '' });

    // --- ESTADOS PARA EL CRUCE DE DOCUMENTOS ---
    const [modalCruceAbierto, setModalCruceAbierto] = useState(false);
    const [facturasCruceSel, setFacturasCruceSel] = useState([]);
    const [aFavorCruceSel, setAFavorCruceSel] = useState([]); 

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
                setModalAnticipoAbierto(false);
                setModalCruceAbierto(false);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    useEffect(() => {
        if (modalAbierto && inputBusquedaRef.current) {
            inputBusquedaRef.current.focus();
            if (listaProveedores.length === 0) cargarListaCompleta();
        }
    }, [modalAbierto]);

    const cargarListaCompleta = async () => {
        try {
            const res = await api.get('/proveedores');
            if (res.success) setListaProveedores(res.data);
        } catch (error) { }
    };

    const cargarFicha = async (proveedorId) => {
        setLoading(true);
        try {
            const res = await api.get(`/proveedores/ficha/${proveedorId}`);
            if (res.success && res.data) {
                setDatos(res.data);
            } else {
                throw new Error("Datos inválidos devueltos por la API.");
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se encontró la ficha del proveedor.' });
            navigate('/proveedores');
        } finally {
            setLoading(false);
        }
    };

    const abrirBuscador = () => { setTerminoBusqueda(''); setModalAbierto(true); };
    const seleccionarProveedor = (proveedorId) => { setModalAbierto(false); navigate(`/proveedores/visor/${proveedorId}`); };

    const subirPdfFactura = async (facturaId, e) => {
        const file = e.target.files[0];
        if (!file) return;
        if (file.type !== 'application/pdf') return Swal.fire({ icon: 'error', title: 'Formato inválido', text: 'Solo se permiten archivos PDF.' });

        const formData = new FormData();
        formData.append('pdf', file);

        try {
            Swal.fire({ title: 'Subiendo documento...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const res = await api.post(`/facturas/${facturaId}/pdf`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
            if (res.success) {
                Swal.fire({ icon: 'success', title: '¡Listo!', text: 'PDF adjuntado correctamente.', timer: 2000 });
                cargarFicha(id);
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.mensaje || 'Error al subir el archivo.' });
        }
    };

    const subirPdfAnticipo = async (anticipoId, e) => {
        const file = e.target.files[0];
        if (!file) return;
        if (file.type !== 'application/pdf') return Swal.fire({ icon: 'error', title: 'Formato inválido', text: 'Solo se permiten archivos PDF.' });

        const formData = new FormData();
        formData.append('pdf', file);

        try {
            Swal.fire({ title: 'Subiendo comprobante...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const res = await api.post(`/proveedores/anticipos/${anticipoId}/pdf`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
            if (res.success) {
                Swal.fire({ icon: 'success', title: '¡Listo!', text: 'Documento de anticipo adjuntado correctamente.', timer: 2000 });
                cargarFicha(id);
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Error al subir el archivo.' });
        }
    };

    const guardarAnticipo = async () => {
        if (!formAnticipo.monto) return Swal.fire('Faltan Datos', 'El monto es obligatorio.', 'warning');
        try {
            Swal.fire({ title: 'Procesando...', didOpen: () => Swal.showLoading() });
            const res = await api.post('/proveedores/anticipos', { ...formAnticipo, proveedor_id: id });

            if (res.success) {
                Swal.fire('¡Solicitud Creada!', 'El anticipo ha sido registrado en la cuenta corriente.', 'success');
                setModalAnticipoAbierto(false);
                setFormAnticipo({ fecha: new Date().toISOString().split('T')[0], monto: '', referencia: '' });
                cargarFicha(id);
            }
        } catch (error) {
            Swal.fire('Error', error.response?.data?.mensaje || 'Error al guardar.', 'error');
        }
    };

    // --- FUNCIONES DE CRUCE ---
    const abrirModalCruce = () => {
        setFacturasCruceSel([]);
        setAFavorCruceSel([]);
        setModalCruceAbierto(true);
    };

    const toggleSeleccionCruce = (item, tipo, isAFavor) => {
        if (isAFavor) {
            const existe = aFavorCruceSel.find(x => x.id === item.id && x.tipo === tipo);
            if (existe) setAFavorCruceSel(aFavorCruceSel.filter(x => !(x.id === item.id && x.tipo === tipo)));
            else setAFavorCruceSel([...aFavorCruceSel, { ...item, tipo }]);
        } else {
            const existe = facturasCruceSel.find(x => x.id === item.id);
            if (existe) setFacturasCruceSel(facturasCruceSel.filter(x => x.id !== item.id));
            else setFacturasCruceSel([...facturasCruceSel, item]);
        }
    };

    const ejecutarCruceDocumentos = async () => {
        if (facturasCruceSel.length === 0 || aFavorCruceSel.length === 0) {
            return Swal.fire('Selección Incompleta', 'Debes seleccionar al menos una deuda y un saldo a favor para poder compensarlos.', 'warning');
        }

        try {
            Swal.fire({ title: 'Contabilizando Cruce...', text: 'Generando comprobante de traspaso...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            const payload = {
                facturas_ids: facturasCruceSel.map(f => f.id),
                notas_credito_ids: aFavorCruceSel.filter(x => x.tipo === 'NOTA_CREDITO').map(x => x.id),
                anticipos_ids: aFavorCruceSel.filter(x => x.tipo === 'ANTICIPO').map(x => x.id),
            };

            const res = await api.post(`/proveedores/${id}/cruzar-documentos`, payload);
            
            if (res.success) {
                Swal.fire('¡Cruce Exitoso!', 'Los documentos han sido compensados contablemente.', 'success');
                setModalCruceAbierto(false);
                cargarFicha(id);
            }
        } catch (error) {
            Swal.fire('Error al Cruzar', error.response?.data?.message || 'Error al procesar la compensación en el servidor.', 'error');
        }
    };

    const proveedoresFiltrados = listaProveedores.filter(p => {
        const b = terminoBusqueda.toLowerCase();
        return p.razon_social?.toLowerCase().includes(b) || (p.rut && p.rut.toLowerCase().includes(b)) || (p.codigo_interno && p.codigo_interno.toLowerCase().includes(b));
    });

    // --- MODALES (Spotlight y Anticipo) ---
    const modalSpotlightJSX = modalAbierto && (
        <div className="fixed inset-0 bg-slate-900/80 z-[100] flex items-start justify-center pt-[10vh] p-4 animate-fade-in" onClick={() => setModalAbierto(false)}>
            <div className="bg-white w-full max-w-2xl rounded-xl shadow-xl overflow-hidden flex flex-col max-h-[80vh] border border-slate-300" onClick={e => e.stopPropagation()}>
                <div className="flex items-center px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <i className="fas fa-search text-slate-500 text-xl mr-4"></i>
                    <input ref={inputBusquedaRef} type="text" className="flex-1 bg-transparent border-none outline-none text-lg font-bold text-slate-800 placeholder-slate-400" placeholder="Buscar por RUT, Nombre o Código..." value={terminoBusqueda} onChange={(e) => setTerminoBusqueda(e.target.value)} />
                    <button onClick={() => setModalAbierto(false)} className="bg-slate-200 text-slate-600 hover:bg-slate-300 text-xs font-bold px-3 py-1 rounded transition-colors">ESC</button>
                </div>
                <div className="overflow-y-auto p-2">
                    {proveedoresFiltrados.map(prov => (
                        <div key={prov.id} onClick={() => seleccionarProveedor(prov.id)} className="flex items-center justify-between p-4 hover:bg-blue-50 cursor-pointer border-b border-slate-50 transition-colors">
                            <div className="flex items-center gap-4">
                                <div className="w-8 h-8 rounded bg-slate-100 flex items-center justify-center text-slate-500 border border-slate-200"><i className="fas fa-building"></i></div>
                                <div>
                                    <p className="font-bold text-slate-800 text-sm">{prov.razon_social}</p>
                                    <p className="text-[10px] text-slate-500 font-mono">RUT: {prov.rut || 'N/A'} <span className="mx-2">•</span> COD: {prov.codigo_interno}</p>
                                </div>
                            </div>
                            <i className="fas fa-chevron-right text-slate-300"></i>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );

    // MODAL DE ANTICIPO MEJORADO Y FORMAL
    const modalAnticipoJSX = modalAnticipoAbierto && (
        <div className="fixed inset-0 bg-slate-900/80 z-[100] flex items-center justify-center p-4 animate-fade-in" onClick={() => setModalAnticipoAbierto(false)}>
            <div className="bg-white w-full max-w-lg rounded-xl shadow-xl overflow-hidden border border-slate-300 flex flex-col" onClick={e => e.stopPropagation()}>
                
                <div className="bg-slate-900 px-6 py-4 flex justify-between items-center text-white shrink-0 border-b border-slate-800">
                    <div className="flex items-center gap-3">
                        <i className="fas fa-hand-holding-usd text-slate-400"></i>
                        <div>
                            <h2 className="text-base font-bold">Registrar Anticipo Manual</h2>
                            <p className="text-xs text-slate-400 uppercase">Solicitud de Pago a Cuenta</p>
                        </div>
                    </div>
                    <button onClick={() => setModalAnticipoAbierto(false)} className="text-slate-400 hover:text-white transition-colors">
                        <i className="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div className="p-6 space-y-5 bg-slate-50">
                    <div className="bg-blue-50 border border-blue-200 p-3 rounded text-xs text-blue-700 font-medium flex gap-2">
                        <i className="fas fa-info-circle mt-0.5"></i>
                        <p>Esta solicitud quedará en estado <b>PENDIENTE</b>. Para que el saldo figure a favor y pueda ser cruzado, deberá concretar el pago desde la Mesa de Conciliación en Tesorería.</p>
                    </div>

                    <div className="grid grid-cols-2 gap-5">
                        <div>
                            <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Fecha Emisión</label>
                            <input type="date" value={formAnticipo.fecha} onChange={e => setFormAnticipo({ ...formAnticipo, fecha: e.target.value })} className="w-full bg-white border border-slate-300 p-2.5 rounded font-medium text-slate-800 outline-none focus:border-blue-500 transition-colors" />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Monto Solicitado ($)</label>
                            <input type="number" placeholder="Ej: 500000" value={formAnticipo.monto} onChange={e => setFormAnticipo({ ...formAnticipo, monto: e.target.value })} className="w-full bg-white border border-slate-300 p-2.5 rounded font-mono font-bold text-slate-800 outline-none focus:border-blue-500 transition-colors" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Motivo / Referencia Operativa</label>
                        <input type="text" placeholder="Ej: Pago adelantado OC-8821" value={formAnticipo.referencia} onChange={e => setFormAnticipo({ ...formAnticipo, referencia: e.target.value })} className="w-full bg-white border border-slate-300 p-2.5 rounded font-medium text-slate-800 outline-none focus:border-blue-500 transition-colors" />
                    </div>
                </div>

                <div className="bg-white border-t border-slate-200 p-4 shrink-0 flex justify-end gap-3">
                    <button onClick={() => setModalAnticipoAbierto(false)} className="px-5 py-2 text-slate-600 border border-slate-300 font-bold hover:bg-slate-100 rounded transition-colors text-sm">Cancelar</button>
                    <button onClick={guardarAnticipo} className="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2 rounded transition-colors text-sm shadow-sm">Guardar Registro</button>
                </div>
            </div>
        </div>
    );

    if (loading && !datos) {
        return (
            <div className="flex flex-col items-center justify-center h-[80vh] text-slate-500">
                <i className="fas fa-circle-notch fa-spin text-4xl mb-4"></i>
                <p className="font-bold tracking-wide uppercase text-xs">Cargando Datos...</p>
            </div>
        );
    }

    if (!datos) {
        return (
            <div className="max-w-7xl mx-auto p-4 md:p-8 font-sans h-full flex flex-col">
                {modalSpotlightJSX}
                <div className="flex-1 flex flex-col items-center justify-center text-center mt-20">
                    <div className="w-20 h-20 bg-slate-100 text-slate-400 rounded-lg flex items-center justify-center text-3xl mb-6 border border-slate-200">
                        <i className="fas fa-address-book"></i>
                    </div>
                    <h1 className="text-3xl font-bold text-slate-800 mb-2">Visor del Proveedor</h1>
                    <p className="text-slate-500 text-sm max-w-lg mb-8">
                        Consulta el historial financiero, facturas, anticipos y datos de contacto del proveedor seleccionado.
                    </p>
                    <button onClick={abrirBuscador} className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded shadow-sm transition-colors flex items-center gap-2">
                        <i className="fas fa-search"></i> Seleccionar Proveedor
                    </button>
                </div>
            </div>
        );
    }

    const proveedor = datos?.proveedor || {};
    const facturas = datos?.facturas || [];
    const anticipos = datos?.anticipos || [];

    const historialCombinado = [
        ...facturas.map(f => {
            const isNotaCredito = f.tipo_documento === 'NOTA_CREDITO';
            return {
                ...f,
                _tipo: isNotaCredito ? 'NOTA_CREDITO' : 'FACTURA',
                _fechaOrden: new Date(f.fecha_emision),
                _documento: f.numero_factura ? `${isNotaCredito ? 'NC' : 'Factura'} #${f.numero_factura}` : `${isNotaCredito ? 'NC' : 'Factura'} S/N`,
                _cargo: isNotaCredito ? 0 : parseFloat(f.monto_bruto || 0), 
                _abono: isNotaCredito ? parseFloat(f.monto_bruto || 0) : 0, 
                _estado: f.estado,
                _archivo: f.archivo_pdf
            };
        }),
        ...anticipos.map(a => ({
            ...a,
            _tipo: 'ANTICIPO',
            _fechaOrden: new Date(a.fecha || a.created_at),
            _documento: a.referencia ? `Anticipo: ${a.referencia}` : 'Anticipo S/R',
            _cargo: 0,
            _abono: parseFloat(a.monto || 0), 
            _estado: a.estado,
            _archivo: a.archivo_pdf
        }))
    ].sort((a, b) => b._fechaOrden - a._fechaOrden);

    const facturasDeuda = facturas.filter(f => f.estado !== 'PAGADA' && f.estado !== 'ANULADA' && f.tipo_documento !== 'NOTA_CREDITO');
    const ncVigentes = facturas.filter(f => f.estado !== 'APLICADA' && f.estado !== 'ANULADA' && f.tipo_documento === 'NOTA_CREDITO');
    
    // Activos = Aquellos que no se han consumido (Aplicado) ni cancelado (Anulado)
    const anticiposVigentes = anticipos.filter(a => a.estado !== 'APLICADO' && a.estado !== 'ANULADO');

    const totalDeuda = facturasDeuda.reduce((sum, f) => sum + parseFloat(f.monto_bruto), 0);
    const totalActivos = ncVigentes.reduce((sum, f) => sum + parseFloat(f.monto_bruto), 0) + anticiposVigentes.reduce((sum, a) => sum + parseFloat(a.saldo_disponible || a.monto), 0);
    
    const saldoNeto = totalDeuda - totalActivos;
    const esAcreedor = saldoNeto > 0; 
    const esDeudor = saldoNeto < 0;   

    const historialFiltrado = historialCombinado.filter(item => {
        let pasaTipo = filtroTipo === 'TODOS' ? true : item._tipo === filtroTipo;
        let pasaNumero = filtroNumero ? item._documento.toLowerCase().includes(filtroNumero.toLowerCase()) : true;
        let pasaEstado = true;

        if (filtroEstado) {
            if (filtroEstado === 'VIGENTES') {
                pasaEstado = 
                    (item._tipo === 'FACTURA' && item._estado !== 'PAGADA' && item._estado !== 'ANULADA') ||
                    (item._tipo === 'NOTA_CREDITO' && item._estado !== 'APLICADA' && item._estado !== 'ANULADA') ||
                    (item._tipo === 'ANTICIPO' && item._estado !== 'APLICADO' && item._estado !== 'ANULADO');
            } else if (filtroEstado === 'CERRADOS') {
                pasaEstado = 
                    (item._tipo === 'FACTURA' && item._estado === 'PAGADA') || 
                    (item._tipo === 'NOTA_CREDITO' && item._estado === 'APLICADA') || 
                    (item._tipo === 'ANTICIPO' && item._estado === 'APLICADO');
            } else if (filtroEstado === 'ANULADOS') {
                pasaEstado = item._estado === 'ANULADA' || item._estado === 'ANULADO';
            }
        }
        return pasaTipo && pasaNumero && pasaEstado;
    });

    // --- CÁLCULOS DEL MODAL DE CRUCE ---
    const totalSelCargos = facturasCruceSel.reduce((sum, f) => sum + parseFloat(f.monto_bruto), 0);
    const totalSelAbonos = aFavorCruceSel.reduce((sum, f) => sum + parseFloat(f.monto_bruto || f.monto), 0);
    const difCruce = totalSelCargos - totalSelAbonos;

    const modalCruceJSX = modalCruceAbierto && (
        <div className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[100] flex items-center justify-center p-4 animate-fade-in" onClick={() => setModalCruceAbierto(false)}>
            <div className="bg-white w-full max-w-5xl rounded-xl shadow-xl overflow-hidden flex flex-col max-h-[95vh] border border-slate-300 animate-slide-down" onClick={e => e.stopPropagation()}>
                
                {/* CABECERA */}
                <div className="bg-slate-900 px-6 py-4 flex justify-between items-center text-white shrink-0 border-b border-slate-800 relative overflow-hidden">
                    <div className="absolute top-0 right-0 -mt-8 -mr-8 w-32 h-32 bg-blue-500 opacity-20 rounded-full blur-3xl pointer-events-none"></div>
                    <div className="flex items-center gap-4 relative z-10">
                        <div className="w-10 h-10 rounded bg-blue-500/20 text-blue-400 flex items-center justify-center text-xl border border-blue-500/30">
                            <i className="fas fa-random"></i>
                        </div>
                        <div>
                            <h2 className="text-lg font-bold flex items-center gap-2">
                                Compensación de Partidas (Clearing)
                            </h2>
                            <p className="text-xs text-indigo-200 uppercase mt-0.5">
                                Cruce de Facturas vs Notas de Crédito / Anticipos
                            </p>
                        </div>
                    </div>
                    <button onClick={() => setModalCruceAbierto(false)} className="text-slate-400 hover:text-white bg-slate-800/50 hover:bg-slate-800 w-8 h-8 rounded flex items-center justify-center transition-colors relative z-10 border border-slate-700">
                        <i className="fas fa-times"></i>
                    </button>
                </div>

                {/* CUERPO A DOS COLUMNAS */}
                <div className="flex flex-col lg:flex-row flex-1 overflow-hidden">
                    
                    {/* COLUMNA IZQUIERDA: DEUDAS (FACTURAS) */}
                    <div className="flex-1 flex flex-col border-r border-slate-200 bg-white">
                        <div className="bg-slate-50 px-5 py-3 border-b border-slate-200 flex justify-between items-center shrink-0">
                            <h3 className="font-bold text-slate-800 text-sm flex items-center gap-2 uppercase tracking-wide">
                                <i className="fas fa-file-invoice text-rose-500"></i> Deudas Vigentes
                            </h3>
                            <span className="bg-slate-200 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">{facturasDeuda.length} docs</span>
                        </div>
                        <div className="flex-1 overflow-y-auto p-4 custom-scrollbar bg-white">
                            {facturasDeuda.length === 0 ? (
                                <p className="text-center text-slate-400 text-sm py-10 italic">No hay facturas pendientes.</p>
                            ) : (
                                <div className="space-y-2">
                                    {facturasDeuda.map(fac => {
                                        const seleccionado = facturasCruceSel.some(x => x.id === fac.id);
                                        return (
                                            <div 
                                                key={fac.id} 
                                                onClick={() => toggleSeleccionCruce(fac, 'FACTURA', false)}
                                                className={`p-3 rounded border cursor-pointer transition-all flex items-center gap-3 ${seleccionado ? 'bg-blue-50 border-blue-400 shadow-sm' : 'bg-white border-slate-200 hover:border-blue-300'}`}
                                            >
                                                <div className={`w-5 h-5 rounded-sm flex items-center justify-center border transition-colors ${seleccionado ? 'bg-blue-600 border-blue-600' : 'bg-slate-100 border-slate-300'}`}>
                                                    {seleccionado && <i className="fas fa-check text-white text-[10px]"></i>}
                                                </div>
                                                <div className="flex-1">
                                                    <p className="font-bold text-slate-800 text-sm leading-none">Fac #{fac.numero_factura}</p>
                                                    <p className="text-xs text-slate-500 font-mono mt-1">{new Date(fac.fecha_emision).toLocaleDateString()}</p>
                                                </div>
                                                <p className="font-bold text-slate-800 text-base">{formatCurrency(fac.monto_bruto)}</p>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* COLUMNA DERECHA: A FAVOR (NC Y ANTICIPOS) */}
                    <div className="flex-1 flex flex-col bg-white">
                        <div className="bg-slate-50 px-5 py-3 border-b border-slate-200 flex justify-between items-center shrink-0">
                            <h3 className="font-bold text-slate-800 text-sm flex items-center gap-2 uppercase tracking-wide">
                                <i className="fas fa-piggy-bank text-emerald-500"></i> Saldos a Favor
                            </h3>
                            <span className="bg-slate-200 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">{ncVigentes.length + anticiposVigentes.length} docs</span>
                        </div>
                        <div className="flex-1 overflow-y-auto p-4 custom-scrollbar bg-white">
                            {(ncVigentes.length === 0 && anticiposVigentes.length === 0) ? (
                                <p className="text-center text-slate-400 text-sm py-10 italic">No hay notas de crédito ni anticipos disponibles.</p>
                            ) : (
                                <div className="space-y-2">
                                    {/* Mostrar Notas de Crédito */}
                                    {ncVigentes.map(nc => {
                                        const seleccionado = aFavorCruceSel.some(x => x.id === nc.id && x.tipo === 'NOTA_CREDITO');
                                        return (
                                            <div 
                                                key={`nc-${nc.id}`} 
                                                onClick={() => toggleSeleccionCruce(nc, 'NOTA_CREDITO', true)}
                                                className={`p-3 rounded border cursor-pointer transition-all flex items-center gap-3 ${seleccionado ? 'bg-blue-50 border-blue-400 shadow-sm' : 'bg-white border-slate-200 hover:border-blue-300'}`}
                                            >
                                                <div className={`w-5 h-5 rounded-sm flex items-center justify-center border transition-colors ${seleccionado ? 'bg-blue-600 border-blue-600' : 'bg-slate-100 border-slate-300'}`}>
                                                    {seleccionado && <i className="fas fa-check text-white text-[10px]"></i>}
                                                </div>
                                                <div className="flex-1">
                                                    <p className="font-bold text-slate-800 text-sm leading-none flex items-center gap-2">
                                                        <span className="bg-purple-100 text-purple-700 border border-purple-200 px-1 py-0.5 rounded text-[10px] uppercase">NC</span>
                                                        #{nc.numero_factura}
                                                    </p>
                                                    <p className="text-xs text-slate-500 font-mono mt-1">{new Date(nc.fecha_emision).toLocaleDateString()}</p>
                                                </div>
                                                <p className="font-bold text-slate-800 text-base">{formatCurrency(nc.monto_bruto)}</p>
                                            </div>
                                        );
                                    })}
                                    {/* Mostrar Anticipos */}
                                    {anticiposVigentes.map(ant => {
                                        const seleccionado = aFavorCruceSel.some(x => x.id === ant.id && x.tipo === 'ANTICIPO');
                                        return (
                                            <div 
                                                key={`ant-${ant.id}`} 
                                                onClick={() => toggleSeleccionCruce(ant, 'ANTICIPO', true)}
                                                className={`p-3 rounded border cursor-pointer transition-all flex items-center gap-3 ${seleccionado ? 'bg-blue-50 border-blue-400 shadow-sm' : 'bg-white border-slate-200 hover:border-blue-300'}`}
                                            >
                                                <div className={`w-5 h-5 rounded-sm flex items-center justify-center border transition-colors ${seleccionado ? 'bg-blue-600 border-blue-600' : 'bg-slate-100 border-slate-300'}`}>
                                                    {seleccionado && <i className="fas fa-check text-white text-[10px]"></i>}
                                                </div>
                                                <div className="flex-1">
                                                    <p className="font-bold text-slate-800 text-sm leading-none flex items-center gap-2">
                                                        <span className="bg-emerald-100 text-emerald-700 border border-emerald-200 px-1 py-0.5 rounded text-[10px] uppercase">ANT</span>
                                                        {ant.referencia || 'S/N'}
                                                    </p>
                                                    <p className="text-xs text-slate-500 font-mono mt-1">{new Date(ant.fecha || ant.created_at).toLocaleDateString()}</p>
                                                </div>
                                                <p className="font-bold text-slate-800 text-base">{formatCurrency(ant.monto)}</p>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* FOOTER: RESUMEN MATEMÁTICO Y BOTÓN */}
                <div className="bg-slate-50 border-t border-slate-200 p-4 shrink-0 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div className="flex items-center gap-4 w-full md:w-auto">
                        <div>
                            <p className="text-[10px] font-bold text-slate-500 uppercase mb-0.5">Total Deudas</p>
                            <p className="text-lg font-bold text-slate-800">{formatCurrency(totalSelCargos)}</p>
                        </div>
                        <div className="text-slate-300 text-xl font-light">-</div>
                        <div>
                            <p className="text-[10px] font-bold text-slate-500 uppercase mb-0.5">Total a Favor</p>
                            <p className="text-lg font-bold text-slate-800">{formatCurrency(totalSelAbonos)}</p>
                        </div>
                        <div className="text-slate-300 text-xl font-light">=</div>
                        <div className="bg-white px-3 py-1 rounded border border-slate-200">
                            <p className="text-[10px] font-bold text-slate-500 uppercase mb-0.5">Diferencia</p>
                            <p className={`text-lg font-bold ${difCruce === 0 ? 'text-slate-500' : 'text-slate-800'}`}>
                                {formatCurrency(Math.abs(difCruce))} {difCruce > 0 && <span className="text-[10px] uppercase ml-1">Por Pagar</span>}
                            </p>
                        </div>
                    </div>
                    <button 
                        onClick={ejecutarCruceDocumentos}
                        disabled={facturasCruceSel.length === 0 || aFavorCruceSel.length === 0}
                        className="w-full md:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white font-bold rounded shadow-sm transition-colors flex items-center justify-center gap-2 text-sm"
                    >
                        Ejecutar Compensación
                    </button>
                </div>
            </div>
        </div>
    );

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 font-sans text-slate-800 animate-fade-in pb-20">
            {modalSpotlightJSX}
            {modalAnticipoJSX}
            {modalCruceJSX}

            <div className="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8">
                <div>
                    <div className="flex items-center gap-2 mb-2">
                        <span className="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2 py-0.5 rounded uppercase border border-indigo-200">
                            Cuenta Corriente
                        </span>
                        <span className="text-slate-300 text-xs font-bold px-2">|</span>
                        <button onClick={() => navigate('/proveedores')} className="text-slate-500 hover:text-indigo-600 font-bold text-xs flex items-center gap-1 transition-colors">
                            Ver Directorio
                        </button>
                    </div>
                    <h1 className="text-3xl font-black text-slate-900 tracking-tight">Ficha 360° del Proveedor</h1>
                </div>

                <div className="flex flex-wrap gap-2">
                    <button onClick={abrirModalCruce} className="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold py-2 px-4 rounded text-sm transition-colors flex items-center gap-2">
                        <i className="fas fa-random"></i> Cruzar Documentos
                    </button>
                    <button onClick={() => setModalAnticipoAbierto(true)} className="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold py-2 px-4 rounded text-sm transition-colors flex items-center gap-2">
                        <i className="fas fa-plus-circle"></i> Nuevo Anticipo
                    </button>
                    <button onClick={abrirBuscador} className="bg-blue-600 text-white hover:bg-blue-700 font-bold py-2 px-4 rounded text-sm transition-colors flex items-center gap-2 shadow-sm">
                        <i className="fas fa-search"></i> Buscar Proveedor
                    </button>
                </div>
            </div>

            {/* TARJETA OSCURA PREMIUM CON COLOR PLANO */}
            {/* FIX: Se eliminaron gradientes (bg-gradient), blurs y efectos de transparencia. Color sólido y plano bg-slate-900. */}
            <div className="bg-slate-900 rounded-2xl p-8 text-white shadow-xl mb-8 relative overflow-hidden border border-slate-800">
                
                <div className="relative z-10 flex flex-col lg:flex-row justify-between gap-10">
                    <div className="space-y-4 flex-1">
                        <div>
                            {/* FIX: Badge sólido sin transparencias */}
                            <span className="bg-slate-800 text-indigo-200 border border-slate-700 text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-3 inline-block">
                                CÓDIGO INTERNO: {proveedor.codigo_interno}
                            </span>
                            <h2 className="text-3xl md:text-4xl font-black tracking-tight mb-2">{proveedor.razon_social}</h2>
                            <p className="text-slate-400 font-mono text-sm"><i className="fas fa-fingerprint mr-2"></i>RUT: {proveedor.rut || 'Extranjero'}</p>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-slate-700">
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-1">Dirección Fiscal</p>
                                <p className="text-sm font-medium text-slate-200">{proveedor.direccion || 'No registrada'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-1">Contacto Principal</p>
                                <p className="text-sm font-medium text-slate-200">{proveedor.email_contacto || 'Sin correo'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-1">Teléfono</p>
                                <p className="text-sm font-medium text-slate-200">{proveedor.telefono || 'Sin teléfono'}</p>
                            </div>
                        </div>
                    </div>

                    {/* RESUMEN CONTABLE CON COLOR PLANO */}
                    <div className="flex flex-col justify-center gap-4 lg:min-w-[280px] shrink-0 border-l border-slate-800 lg:pl-8">
                        {/* FIX: Se eliminó el backdrop-blur y bg-slate-900/50. Ahora es un bg-slate-800 sólido. */}
                        <div className="bg-slate-800 p-5 rounded-xl border border-slate-700 relative overflow-hidden">
                            <div className={`absolute top-0 left-0 w-1 h-full ${esAcreedor ? 'bg-rose-500' : esDeudor ? 'bg-emerald-500' : 'bg-slate-500'}`}></div>
                            <p className="text-[10px] text-slate-400 uppercase font-bold mb-1">Saldo Contable Actual</p>
                            <div className="flex items-baseline gap-2">
                                <p className={`text-3xl font-mono font-bold tracking-tight ${esAcreedor ? 'text-rose-400' : esDeudor ? 'text-emerald-400' : 'text-slate-300'}`}>
                                    {formatCurrency(Math.abs(saldoNeto))}
                                </p>
                                <span className="text-xs font-bold uppercase text-slate-500">
                                    {esAcreedor ? '(A Pagar)' : esDeudor ? '(A Favor)' : ''}
                                </span>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4 text-sm px-1">
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-0.5">Deuda (Pasivo)</p>
                                <p className="font-mono font-bold text-slate-300">{formatCurrency(totalDeuda)}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-slate-500 uppercase font-bold mb-0.5">A Favor (Activo)</p>
                                <p className="font-mono font-bold text-slate-300">{formatCurrency(totalActivos)}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* TABLA UNIFICADA (CUENTA CORRIENTE) */}
            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div className="bg-slate-50 border-b border-slate-200 p-4 md:p-6 flex justify-between items-center">
                    <div>
                        <h2 className="text-base font-bold text-slate-800">Cartola de Movimientos</h2>
                        <p className="text-xs text-slate-500">Estado de cuenta detallado y cronológico.</p>
                    </div>
                </div>

                <div className="bg-white p-4 border-b border-slate-100 flex flex-wrap gap-4 items-center">
                    <div className="flex-1 min-w-[200px]">
                        <div className="relative w-full">
                            <i className="fas fa-filter absolute left-3 top-3 text-slate-400"></i>
                            <input type="text" placeholder="Filtrar N° Doc o Referencia..." value={filtroNumero} onChange={(e) => setFiltroNumero(e.target.value)} className="w-full pl-9 pr-3 py-2 border border-slate-300 rounded text-sm outline-none focus:border-blue-500" />
                        </div>
                    </div>
                    <select value={filtroTipo} onChange={(e) => setFiltroTipo(e.target.value)} className="w-48 px-3 py-2 border border-slate-300 rounded text-sm text-slate-700 outline-none focus:border-blue-500">
                        <option value="TODOS">Todos los Tipos</option>
                        <option value="FACTURA">Solo Facturas</option>
                        <option value="NOTA_CREDITO">Solo Notas de Crédito</option>
                        <option value="ANTICIPO">Solo Anticipos</option>
                    </select>
                    <select value={filtroEstado} onChange={(e) => setFiltroEstado(e.target.value)} className="w-48 px-3 py-2 border border-slate-300 rounded text-sm text-slate-700 outline-none focus:border-blue-500">
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
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="px-6 py-3 font-bold text-slate-500 text-xs">Fecha</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 text-xs">Documento</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 text-xs text-right">Cargos (Deuda)</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 text-xs text-right">Abonos (A Favor)</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 text-xs text-center">Estado</th>
                                    <th className="px-6 py-3 font-bold text-slate-500 text-xs text-center">Adjunto</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {historialFiltrado.map((item, i) => (
                                    <tr key={`${item._tipo}-${item.id}-${i}`} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-6 py-3 text-slate-600 font-mono text-xs">
                                            {item._fechaOrden.toLocaleDateString('es-CL')}
                                        </td>
                                        <td className="px-6 py-3">
                                            <div className="flex items-center gap-2">
                                                {item._tipo === 'FACTURA'
                                                    ? <span className="bg-slate-100 text-slate-600 text-[10px] font-bold px-1.5 py-0.5 rounded border border-slate-200">FAC</span>
                                                    : item._tipo === 'NOTA_CREDITO'
                                                    ? <span className="bg-purple-50 text-purple-600 text-[10px] font-bold px-1.5 py-0.5 rounded border border-purple-200">NC</span>
                                                    : <span className="bg-emerald-50 text-emerald-600 text-[10px] font-bold px-1.5 py-0.5 rounded border border-emerald-200">ANT</span>
                                                }
                                                <span className="font-bold text-slate-800">
                                                    {item._documento}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono text-slate-700">
                                            {item._cargo > 0 ? formatCurrency(item._cargo) : '-'}
                                        </td>
                                        <td className="px-6 py-3 text-right font-mono text-slate-700">
                                            {item._abono > 0 ? formatCurrency(item._abono) : '-'}
                                        </td>
                                        <td className="px-6 py-3 text-center">
                                            {item._estado === 'PAGADA' || item._estado === 'APLICADO' || item._estado === 'APLICADA' ? (
                                                <span className="text-slate-400 font-bold text-[10px] uppercase">Cerrado</span>
                                            ) : item._estado === 'ANULADA' ? (
                                                <span className="text-slate-300 font-bold text-[10px] uppercase line-through">Anulado</span>
                                            ) : (
                                                <span className="text-blue-600 font-bold text-[10px] uppercase">Vigente</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-3 text-center">
                                            {item._archivo ? (
                                                <a
                                                    href={`${import.meta.env.VITE_API_URL.replace('/api', '')}/${item._archivo}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-slate-500 hover:text-blue-600 font-bold text-xs transition-colors"
                                                >
                                                    Ver Doc
                                                </a>
                                            ) : (
                                                <label className="text-blue-500 hover:text-blue-700 font-bold text-xs cursor-pointer transition-colors">
                                                    Subir PDF
                                                    <input
                                                        type="file"
                                                        accept="application/pdf"
                                                        className="hidden"
                                                        onChange={(e) => (item._tipo === 'FACTURA' || item._tipo === 'NOTA_CREDITO') ? subirPdfFactura(item.id, e) : subirPdfAnticipo(item.id, e)}
                                                    />
                                                </label>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </div>
    );
};

export default VisorProveedor;