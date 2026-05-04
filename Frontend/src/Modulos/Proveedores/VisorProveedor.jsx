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

    const proveedoresFiltrados = listaProveedores.filter(p => {
        const b = terminoBusqueda.toLowerCase();
        return p.razon_social?.toLowerCase().includes(b) || (p.rut && p.rut.toLowerCase().includes(b)) || (p.codigo_interno && p.codigo_interno.toLowerCase().includes(b));
    });

    // --- MODALES (Spotlight y Anticipo) ---
    const modalSpotlightJSX = modalAbierto && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-start justify-center pt-[10vh] animate-fade-in p-4" onClick={() => setModalAbierto(false)}>
            <div className="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh] animate-slide-down" onClick={e => e.stopPropagation()}>
                <div className="flex items-center px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <i className="fas fa-search text-indigo-500 text-2xl mr-4"></i>
                    <input ref={inputBusquedaRef} type="text" className="flex-1 bg-transparent border-none outline-none text-2xl font-black text-slate-800 placeholder-slate-300" placeholder="Buscar por RUT, Nombre o Código..." value={terminoBusqueda} onChange={(e) => setTerminoBusqueda(e.target.value)} />
                    <button onClick={() => setModalAbierto(false)} className="bg-slate-200 text-slate-500 hover:bg-slate-300 text-xs font-bold px-3 py-1 rounded-lg">ESC</button>
                </div>
                <div className="overflow-y-auto p-2">
                    {proveedoresFiltrados.map(prov => (
                        <div key={prov.id} onClick={() => seleccionarProveedor(prov.id)} className="flex items-center justify-between p-4 hover:bg-indigo-50 cursor-pointer rounded-xl transition-colors group">
                            <div className="flex items-center gap-4">
                                <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-indigo-100 group-hover:text-indigo-600 transition-colors"><i className="fas fa-building"></i></div>
                                <div>
                                    <p className="font-black text-slate-800 group-hover:text-indigo-900">{prov.razon_social}</p>
                                    <p className="text-xs text-slate-500 font-mono font-medium">RUT: {prov.rut || 'N/A'} <span className="mx-2">•</span> COD: {prov.codigo_interno}</p>
                                </div>
                            </div>
                            <i className="fas fa-chevron-right text-slate-300 group-hover:text-indigo-400 transition-transform group-hover:translate-x-1"></i>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );

    const modalAnticipoJSX = modalAnticipoAbierto && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center animate-fade-in p-4" onClick={() => setModalAnticipoAbierto(false)}>
            <div className="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden animate-slide-down" onClick={e => e.stopPropagation()}>
                <div className="bg-emerald-600 px-6 py-4 flex justify-between items-center text-white">
                    <h3 className="font-black text-lg flex items-center gap-2"><i className="fas fa-arrow-up"></i> Registrar Anticipo / Pago a Cuenta</h3>
                    <button onClick={() => setModalAnticipoAbierto(false)} className="text-emerald-100 hover:text-white"><i className="fas fa-times text-xl"></i></button>
                </div>
                <div className="p-6 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Fecha Emisión</label>
                            <input type="date" value={formAnticipo.fecha} onChange={e => setFormAnticipo({ ...formAnticipo, fecha: e.target.value })} className="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl font-bold text-slate-700 outline-none focus:border-emerald-500" />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Monto ($)</label>
                            <input type="number" placeholder="Ej: 500000" value={formAnticipo.monto} onChange={e => setFormAnticipo({ ...formAnticipo, monto: e.target.value })} className="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl font-mono font-black text-slate-800 outline-none focus:border-emerald-500" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Motivo / Referencia</label>
                        <input type="text" placeholder="Ej: Comprobante N° 14502 / Factura Anticipo..." value={formAnticipo.referencia} onChange={e => setFormAnticipo({ ...formAnticipo, referencia: e.target.value })} className="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl font-medium text-slate-700 outline-none focus:border-emerald-500" />
                    </div>
                </div>
                <div className="bg-slate-50 p-4 border-t border-slate-100 flex justify-end gap-3">
                    <button onClick={() => setModalAnticipoAbierto(false)} className="px-6 py-2.5 text-slate-500 font-bold hover:bg-slate-200 rounded-xl transition-colors">Cancelar</button>
                    <button onClick={guardarAnticipo} className="bg-emerald-600 hover:bg-emerald-700 text-white font-black px-8 py-2.5 rounded-xl shadow-lg shadow-emerald-500/30 transition-transform hover:-translate-y-0.5">Guardar Registro</button>
                </div>
            </div>
        </div>
    );

    if (loading && !datos) {
        return (
            <div className="flex flex-col items-center justify-center h-[80vh] text-slate-400">
                <i className="fas fa-circle-notch fa-spin text-5xl mb-4 text-indigo-500"></i>
                <p className="font-black tracking-widest uppercase text-sm animate-pulse">Cargando Cartola...</p>
            </div>
        );
    }

    if (!datos) {
        return (
            <div className="max-w-7xl mx-auto p-4 md:p-8 font-sans h-full flex flex-col relative">
                {modalSpotlightJSX}
                <div className="flex-1 flex flex-col items-center justify-center text-center mt-20">
                    <div className="w-24 h-24 bg-indigo-100 text-indigo-600 rounded-3xl flex items-center justify-center text-4xl mb-6 shadow-inner shadow-indigo-200">
                        <i className="fas fa-satellite-dish"></i>
                    </div>
                    <h1 className="text-4xl md:text-5xl font-black text-slate-900 tracking-tight mb-4">Visor 360°</h1>
                    <p className="text-slate-500 text-lg font-medium max-w-xl mb-8">
                        Consulta todo el historial financiero, facturas, comprobantes, anticipos y datos de contacto de cualquier proveedor en un solo lugar.
                    </p>
                    <button onClick={abrirBuscador} className="bg-slate-900 hover:bg-black text-white font-black py-4 px-10 rounded-2xl shadow-xl shadow-slate-900/20 transition-all hover:-translate-y-1 flex items-center gap-3 text-lg group">
                        <i className="fas fa-search group-hover:scale-110 transition-transform"></i> BUSCAR PROVEEDOR
                    </button>
                    <p className="mt-6 text-sm font-bold text-slate-400 flex items-center gap-2">
                        <i className="fas fa-keyboard"></i> Atajo rápido: <kbd className="bg-slate-100 border border-slate-200 px-2 py-1 rounded text-slate-600 font-mono">Ctrl + 2</kbd>
                    </p>
                </div>
            </div>
        );
    }

    // Desestructuración segura
    const proveedor = datos?.proveedor || {};
    const facturas = datos?.facturas || [];
    const anticipos = datos?.anticipos || [];

    // --- LÓGICA DE CARTOLA CONTABLE ESTRICTA CORREGIDA ---
    const historialCombinado = [
        ...facturas.map(f => {
            const isNotaCredito = f.tipo_documento === 'NOTA_CREDITO';
            return {
                ...f,
                _tipo: isNotaCredito ? 'NOTA_CREDITO' : 'FACTURA',
                _fechaOrden: new Date(f.fecha_emision),
                _documento: f.numero_factura ? `${isNotaCredito ? 'NC' : 'Factura'} #${f.numero_factura}` : `${isNotaCredito ? 'NC' : 'Factura'} S/N`,
                _cargo: isNotaCredito ? 0 : parseFloat(f.monto_bruto || 0), // Solo es cargo (deuda) si NO es Nota de Crédito
                _abono: isNotaCredito ? parseFloat(f.monto_bruto || 0) : 0, // Si ES Nota de Crédito, se va directo a Abonos (Plata a favor)
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
            _abono: parseFloat(a.monto || 0), // Dinero a favor
            _estado: a.estado,
            _archivo: a.archivo_pdf
        }))
    ].sort((a, b) => b._fechaOrden - a._fechaOrden);

    // Sumamos Facturas reales (Que no sean Notas de Crédito)
    const totalDeuda = facturas.filter(f => f.estado !== 'PAGADA' && f.estado !== 'ANULADA' && f.tipo_documento !== 'NOTA_CREDITO').reduce((sum, f) => sum + parseFloat(f.monto_bruto), 0);
    
    // Sumamos Notas de Crédito que aún no se han aplicado/usado
    const ncVigentes = facturas.filter(f => f.estado !== 'APLICADA' && f.estado !== 'ANULADA' && f.tipo_documento === 'NOTA_CREDITO').reduce((sum, f) => sum + parseFloat(f.monto_bruto), 0);
    
    // Sumamos Anticipos vigentes
    const anticiposVigentes = anticipos.filter(a => a.estado === 'VIGENTE' || a.estado === 'PENDIENTE').reduce((sum, a) => sum + parseFloat(a.saldo_disponible || a.monto), 0);

    // Cálculo de saldo contable total
    const totalActivos = anticiposVigentes + ncVigentes;
    const saldoNeto = totalDeuda - totalActivos;
    const esAcreedor = saldoNeto > 0; // Le debemos dinero al proveedor
    const esDeudor = saldoNeto < 0;   // El proveedor nos debe mercadería/dinero (Tenemos saldo a favor)

    const historialFiltrado = historialCombinado.filter(item => {
        let pasaTipo = filtroTipo === 'TODOS' ? true : item._tipo === filtroTipo;
        let pasaNumero = filtroNumero ? item._documento.toLowerCase().includes(filtroNumero.toLowerCase()) : true;
        let pasaEstado = true;

        if (filtroEstado) {
            if (filtroEstado === 'VIGENTES') {
                pasaEstado = 
                    (item._tipo === 'FACTURA' && item._estado !== 'PAGADA' && item._estado !== 'ANULADA') ||
                    (item._tipo === 'NOTA_CREDITO' && item._estado !== 'APLICADA' && item._estado !== 'ANULADA') ||
                    (item._tipo === 'ANTICIPO' && (item._estado === 'VIGENTE' || item._estado === 'PENDIENTE'));
            } else if (filtroEstado === 'CERRADOS') {
                pasaEstado = item._estado === 'PAGADA' || item._estado === 'APLICADO' || item._estado === 'APLICADA';
            } else if (filtroEstado === 'ANULADOS') {
                pasaEstado = item._estado === 'ANULADA';
            }
        }
        return pasaTipo && pasaNumero && pasaEstado;
    });

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 font-sans text-slate-800 animate-fade-in pb-20">
            {modalSpotlightJSX}
            {modalAnticipoJSX}

            <div className="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8">
                <div>
                    <div className="flex items-center gap-2 mb-2">
                        <span className="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-indigo-200">
                            Cuenta Corriente
                        </span>
                        <span className="text-slate-400 text-xs font-bold px-2">|</span>
                        <button onClick={() => navigate('/proveedores')} className="text-slate-500 hover:text-indigo-600 font-bold text-xs flex items-center gap-1 transition-colors">
                            Ver Directorio
                        </button>
                    </div>
                    <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Ficha 360° del Proveedor</h1>
                </div>

                <div className="flex gap-3">
                    <button onClick={() => setModalAnticipoAbierto(true)} className="bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-200 font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <i className="fas fa-plus-circle"></i> Nuevo Anticipo
                    </button>
                    <button onClick={abrirBuscador} className="bg-white border border-slate-200 hover:border-indigo-500 text-slate-600 hover:text-indigo-600 font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <i className="fas fa-search"></i> Buscar Proveedor
                    </button>
                </div>
            </div>

            {/* TARJETA OSCURA CON DATOS Y SALDO CONTABLE ESTRICTO */}
            <div className="bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 rounded-3xl p-8 text-white shadow-2xl shadow-indigo-900/20 mb-8 relative overflow-hidden border border-slate-700">
                <i className="fas fa-globe absolute -right-10 -top-10 text-[250px] text-white opacity-5 pointer-events-none"></i>
                <div className="relative z-10 flex flex-col lg:flex-row justify-between gap-10">
                    <div className="space-y-5 flex-1">
                        <div>
                            <span className="bg-white/10 text-indigo-200 border border-white/20 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest mb-3 inline-block backdrop-blur-sm">
                                CÓDIGO INTERNO: {proveedor.codigo_interno}
                            </span>
                            <h2 className="text-4xl md:text-5xl font-black tracking-tight leading-none mb-2">{proveedor.razon_social}</h2>
                            <p className="text-indigo-300 font-mono text-lg font-medium"><i className="fas fa-fingerprint mr-2"></i>ID Fiscal: {proveedor.rut || 'Extranjero'}</p>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-white/10">
                            <div>
                                <p className="text-[10px] text-slate-400 uppercase tracking-widest font-black mb-1">Dirección Fiscal</p>
                                <p className="text-sm font-medium text-white flex items-start gap-2"><i className="fas fa-map-marker-alt text-indigo-400 mt-1"></i>{proveedor.direccion || 'No registrada'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-slate-400 uppercase tracking-widest font-black mb-1">Contacto Principal</p>
                                <p className="text-sm font-medium text-white flex items-start gap-2"><i className="fas fa-envelope text-indigo-400 mt-1"></i>{proveedor.email_contacto || 'Sin correo'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-slate-400 uppercase tracking-widest font-black mb-1">Teléfono</p>
                                <p className="text-sm font-medium text-white flex items-start gap-2"><i className="fas fa-phone-alt text-indigo-400 mt-1"></i>{proveedor.telefono || 'Sin teléfono'}</p>
                            </div>
                        </div>
                    </div>

                    {/* RESUMEN CONTABLE */}
                    <div className="flex flex-col justify-center gap-4 lg:min-w-[280px] shrink-0">
                        <div className="bg-slate-950/50 p-5 rounded-2xl border border-white/10 backdrop-blur-md relative overflow-hidden">
                            <div className={`absolute top-0 left-0 w-1 h-full ${esAcreedor ? 'bg-rose-500' : esDeudor ? 'bg-emerald-500' : 'bg-slate-500'}`}></div>
                            <p className="text-[10px] text-slate-400 uppercase tracking-widest font-black mb-1">Saldo Contable Actual</p>
                            <div className="flex items-baseline gap-2">
                                <p className={`text-3xl font-mono font-black tracking-tight ${esAcreedor ? 'text-rose-400' : esDeudor ? 'text-emerald-400' : 'text-slate-300'}`}>
                                    {formatCurrency(Math.abs(saldoNeto))}
                                </p>
                                {esAcreedor && <span className="text-xs font-bold text-rose-300 uppercase">(Acreedor)</span>}
                                {esDeudor && <span className="text-xs font-bold text-emerald-300 uppercase">(Deudor)</span>}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-2 text-right px-2">
                            <div>
                                <p className="text-[10px] text-slate-400 uppercase tracking-widest font-black mb-0.5">Pasivo Total (Facturas)</p>
                                <p className="text-sm font-mono font-bold text-slate-200">{formatCurrency(totalDeuda)}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-emerald-300 uppercase tracking-widest font-black mb-0.5">Activo (Anticipos + NC)</p>
                                <p className="text-sm font-mono font-bold text-emerald-200">{formatCurrency(totalActivos)}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* TABLA UNIFICADA (CUENTA CORRIENTE) */}
            <div className="bg-white rounded-3xl border border-slate-200 shadow-lg shadow-slate-200/50 overflow-hidden">
                <div className="bg-slate-50 border-b border-slate-200 p-4 md:p-6 flex justify-between items-center">
                    <div>
                        <h2 className="text-lg font-black text-slate-800"><i className="fas fa-list-ul text-indigo-500 mr-2"></i> Cartola de Movimientos</h2>
                        <p className="text-xs font-bold text-slate-400">Estado de cuenta detallado y cronológico.</p>
                    </div>
                </div>

                <div className="bg-white p-4 border-b border-slate-100 flex flex-wrap gap-4 items-center">
                    <div className="flex-1 min-w-[200px]">
                        <div className="relative w-full">
                            <i className="fas fa-filter absolute left-4 top-3.5 text-slate-400"></i>
                            <input type="text" placeholder="Filtrar N° Doc o Referencia..." value={filtroNumero} onChange={(e) => setFiltroNumero(e.target.value)} className="w-full pl-11 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
                        </div>
                    </div>
                    <select value={filtroTipo} onChange={(e) => setFiltroTipo(e.target.value)} className="w-48 px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 outline-none focus:border-indigo-500">
                        <option value="TODOS">Todos los Tipos</option>
                        <option value="FACTURA">Solo Facturas</option>
                        <option value="NOTA_CREDITO">Solo Notas de Crédito</option>
                        <option value="ANTICIPO">Solo Anticipos</option>
                    </select>
                    <select value={filtroEstado} onChange={(e) => setFiltroEstado(e.target.value)} className="w-48 px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 outline-none focus:border-indigo-500">
                        <option value="">Todos los Estados</option>
                        <option value="VIGENTES">Pendientes / Vigentes</option>
                        <option value="CERRADOS">Pagados / Aplicados</option>
                        <option value="ANULADOS">Anulados</option>
                    </select>
                </div>

                <div className="overflow-x-auto min-h-[300px]">
                    {historialCombinado.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 text-slate-400">
                            <div className="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4 border border-slate-100"><i className="fas fa-folder-open text-3xl opacity-50"></i></div>
                            <p className="font-bold text-slate-500 text-lg">Cartola en blanco</p>
                            <p className="text-sm font-medium mt-1">No hay movimientos contables registrados.</p>
                        </div>
                    ) : (
                        <table className="w-full text-left text-sm whitespace-nowrap">
                            <thead className="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase">Fecha</th>
                                    <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase">Movimiento</th>
                                    <th className="px-6 py-4 font-black text-rose-500 text-[10px] uppercase text-right">Cargos (Deuda)</th>
                                    <th className="px-6 py-4 font-black text-emerald-500 text-[10px] uppercase text-right">Abonos (A Favor)</th>
                                    <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase text-center">Estado Contable</th>
                                    <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase text-center">Documento Respaldo</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {historialFiltrado.map((item, i) => (
                                    <tr key={`${item._tipo}-${item.id}-${i}`} className="hover:bg-slate-50 transition-colors group">
                                        <td className="px-6 py-4 font-bold text-slate-500">
                                            {item._fechaOrden.toLocaleDateString('es-CL')}
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                {item._tipo === 'FACTURA'
                                                    ? <span className="bg-indigo-100 text-indigo-600 text-[10px] font-black px-2 py-0.5 rounded border border-indigo-200">FAC</span>
                                                    : item._tipo === 'NOTA_CREDITO'
                                                    ? <span className="bg-purple-100 text-purple-600 text-[10px] font-black px-2 py-0.5 rounded border border-purple-200">NC</span>
                                                    : <span className="bg-emerald-100 text-emerald-600 text-[10px] font-black px-2 py-0.5 rounded border border-emerald-200">ANT</span>
                                                }
                                                <span className={`font-bold ${item._tipo === 'NOTA_CREDITO' ? 'text-purple-800' : 'text-slate-800'}`}>
                                                    {item._documento}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono font-medium text-rose-600">
                                            {item._cargo > 0 ? formatCurrency(item._cargo) : '-'}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono font-black text-emerald-600 text-base">
                                            {item._abono > 0 ? formatCurrency(item._abono) : '-'}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            {item._estado === 'PAGADA' || item._estado === 'APLICADO' || item._estado === 'APLICADA' ? (
                                                <span className="text-slate-400 font-bold text-xs"><i className="fas fa-check-double text-emerald-500"></i> Cerrado</span>
                                            ) : item._estado === 'ANULADA' ? (
                                                <span className="text-slate-300 font-bold text-xs line-through">Anulado</span>
                                            ) : (
                                                <span className={`${item._tipo === 'NOTA_CREDITO' ? 'text-purple-500' : 'text-amber-500'} font-bold text-xs`}>Vigente</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <div className="flex justify-center gap-2">
                                                {item._archivo ? (
                                                    <a
                                                        href={`${import.meta.env.VITE_API_URL.replace('/api', '')}/${item._archivo}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-100 hover:border-rose-300 px-3 py-1.5 rounded-lg font-bold text-xs transition-colors shadow-sm flex items-center gap-2 w-fit mx-auto"
                                                        title="Ver Respaldo PDF"
                                                    >
                                                        <i className="fas fa-file-pdf"></i> Ver Anexo
                                                    </a>
                                                ) : (
                                                    <label
                                                        className="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 border border-indigo-100 hover:border-indigo-300 px-3 py-1.5 rounded-lg font-bold text-xs cursor-pointer transition-colors shadow-sm flex items-center gap-2 w-fit mx-auto"
                                                        title={item._tipo === 'FACTURA' ? "Subir Factura" : item._tipo === 'NOTA_CREDITO' ? "Subir Nota de Crédito" : "Subir Comprobante"}
                                                    >
                                                        <i className="fas fa-upload text-sm"></i> Adjuntar
                                                        <input
                                                            type="file"
                                                            accept="application/pdf"
                                                            className="hidden"
                                                            onChange={(e) => (item._tipo === 'FACTURA' || item._tipo === 'NOTA_CREDITO') ? subirPdfFactura(item.id, e) : subirPdfAnticipo(item.id, e)}
                                                        />
                                                    </label>
                                                )}
                                            </div>
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