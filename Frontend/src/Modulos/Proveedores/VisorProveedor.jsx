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
    const [tabActiva, setTabActiva] = useState('facturas');
    
    const [modalAbierto, setModalAbierto] = useState(false);
    const [listaProveedores, setListaProveedores] = useState([]);
    const [terminoBusqueda, setTerminoBusqueda] = useState('');
    const inputBusquedaRef = useRef(null);

    const [filtroNumero, setFiltroNumero] = useState('');
    const [filtroEstado, setFiltroEstado] = useState('');
    const [filtroFechaInicio, setFiltroFechaInicio] = useState('');
    const [filtroFechaFin, setFiltroFechaFin] = useState('');

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
        } catch (error) {}
    };

    const cargarFicha = async (proveedorId) => {
        setLoading(true);
        try {
            const res = await api.get(`/proveedores/ficha/${proveedorId}`);
            if (res.success) setDatos(res.data);
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se encontró la ficha del proveedor.' });
            navigate('/proveedores/visor');
        } finally {
            setLoading(false);
        }
    };

    const abrirBuscador = () => { setTerminoBusqueda(''); setModalAbierto(true); };
    const seleccionarProveedor = (proveedorId) => { setModalAbierto(false); navigate(`/proveedores/visor/${proveedorId}`); };

    const subirPdfFactura = async (facturaId, e) => {
        const file = e.target.files[0];
        if (!file) return;

        if (file.type !== 'application/pdf') {
            return Swal.fire({ icon: 'error', title: 'Formato inválido', text: 'Solo se permiten archivos PDF.' });
        }

        const formData = new FormData();
        formData.append('pdf', file);

        try {
            Swal.fire({ title: 'Subiendo documento...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const res = await api.post(`/facturas/${facturaId}/pdf`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (res.success) {
                Swal.fire({ icon: 'success', title: '¡Listo!', text: 'PDF adjuntado correctamente.', timer: 2000 });
                cargarFicha(id);
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.mensaje || 'Error al subir el archivo.' });
        }
    };

    const guardarAnticipo = async () => {
        if (!formAnticipo.monto) {
            return Swal.fire('Faltan Datos', 'El monto es obligatorio.', 'warning');
        }
        try {
            Swal.fire({ title: 'Procesando...', didOpen: () => Swal.showLoading() });
            const res = await api.post('/proveedores/anticipos', { ...formAnticipo, proveedor_id: id });
            
            if (res.success) {
                Swal.fire('¡Solicitud Creada!', 'El anticipo está listo para ser pagado.', 'success');
                setModalAnticipoAbierto(false);
                setFormAnticipo({ fecha: new Date().toISOString().split('T')[0], monto: '', referencia: '' });
                cargarFicha(id);
                setTabActiva('anticipos');
            }
        } catch (error) {
            Swal.fire('Error', error.response?.data?.mensaje || 'Error al guardar.', 'error');
        }
    };

    const proveedoresFiltrados = listaProveedores.filter(p => {
        const b = terminoBusqueda.toLowerCase();
        return p.razon_social.toLowerCase().includes(b) || (p.rut && p.rut.toLowerCase().includes(b)) || (p.codigo_interno && p.codigo_interno.toLowerCase().includes(b));
    });

    const modalSpotlightJSX = modalAbierto && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-start justify-center pt-[10vh] animate-fade-in p-4" onClick={() => setModalAbierto(false)}>
            <div className="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh] animate-slide-down" onClick={e => e.stopPropagation()}>
                <div className="flex items-center px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <i className="fas fa-search text-indigo-500 text-2xl mr-4"></i>
                    <input ref={inputBusquedaRef} type="text" className="flex-1 bg-transparent border-none outline-none text-2xl font-black text-slate-800 placeholder-slate-300" placeholder="Buscar por RUT, Nombre o Código..." value={terminoBusqueda} onChange={(e) => setTerminoBusqueda(e.target.value)} />
                    <button onClick={() => setModalAbierto(false)} className="bg-slate-200 text-slate-500 hover:bg-slate-300 text-xs font-bold px-3 py-1 rounded-lg">ESC</button>
                </div>
                <div className="overflow-y-auto p-2">
                    {proveedoresFiltrados.length === 0 ? (
                        <div className="py-12 text-center text-slate-400"><i className="fas fa-ghost text-4xl mb-3 opacity-50"></i><p className="font-bold">No se encontraron proveedores</p></div>
                    ) : (
                        <div className="space-y-1">
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
                    )}
                </div>
            </div>
        </div>
    );

    const modalAnticipoJSX = modalAnticipoAbierto && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center animate-fade-in p-4" onClick={() => setModalAnticipoAbierto(false)}>
            <div className="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden animate-slide-down" onClick={e => e.stopPropagation()}>
                <div className="bg-amber-500 px-6 py-4 flex justify-between items-center text-white">
                    <h3 className="font-black text-lg flex items-center gap-2"><i className="fas fa-hand-holding-usd"></i> Solicitar Anticipo</h3>
                    <button onClick={() => setModalAnticipoAbierto(false)} className="text-amber-100 hover:text-white"><i className="fas fa-times text-xl"></i></button>
                </div>
                <div className="bg-amber-50 p-4 border-b border-amber-100 flex items-start gap-3 text-amber-800 text-xs font-medium">
                    <i className="fas fa-info-circle text-amber-500 text-lg mt-0.5"></i>
                    <p>Al guardar, este anticipo quedará <strong>PENDIENTE</strong>. Su asiento contable se generará automáticamente cuando asocies el pago real en la Cartola Bancaria o Nómina.</p>
                </div>
                <div className="p-6 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Fecha Emisión</label>
                            <input type="date" value={formAnticipo.fecha} onChange={e => setFormAnticipo({...formAnticipo, fecha: e.target.value})} className="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl font-bold text-slate-700 outline-none focus:border-amber-500" />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Monto ($)</label>
                            <input type="number" placeholder="Ej: 500000" value={formAnticipo.monto} onChange={e => setFormAnticipo({...formAnticipo, monto: e.target.value})} className="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl font-mono font-black text-slate-800 outline-none focus:border-amber-500" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Motivo / Referencia</label>
                        <input type="text" placeholder="Ej: Anticipo por compra de maquinaria..." value={formAnticipo.referencia} onChange={e => setFormAnticipo({...formAnticipo, referencia: e.target.value})} className="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl font-medium text-slate-700 outline-none focus:border-amber-500" />
                    </div>
                </div>
                <div className="bg-slate-50 p-4 border-t border-slate-100 flex justify-end gap-3">
                    <button onClick={() => setModalAnticipoAbierto(false)} className="px-6 py-2.5 text-slate-500 font-bold hover:bg-slate-200 rounded-xl transition-colors">Cancelar</button>
                    <button onClick={guardarAnticipo} className="bg-amber-500 hover:bg-amber-600 text-white font-black px-8 py-2.5 rounded-xl shadow-lg shadow-amber-500/30 transition-transform hover:-translate-y-0.5">Dejar Pendiente de Pago</button>
                </div>
            </div>
        </div>
    );

    if (loading && !datos) {
        return (
            <div className="flex flex-col items-center justify-center h-[80vh] text-slate-400">
                <i className="fas fa-circle-notch fa-spin text-5xl mb-4 text-indigo-500"></i>
                <p className="font-black tracking-widest uppercase text-sm animate-pulse">Obteniendo Radiografía Financiera...</p>
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

    const { proveedor, facturas = [], anticipos = [] } = datos;
    const totalComprado = facturas.reduce((sum, f) => sum + parseFloat(f.monto_bruto), 0);
    const totalDeuda = facturas.filter(f => f.estado !== 'PAGADA' && f.estado !== 'ANULADA').reduce((sum, f) => sum + parseFloat(f.monto_bruto), 0);
    const anticiposVigentes = anticipos.filter(a => a.estado === 'VIGENTE').reduce((sum, a) => sum + parseFloat(a.saldo_disponible), 0);

    const facturasFiltradas = facturas.filter(f => {
        let pasaNumero = true;
        let pasaEstado = true;
        let pasaFecha = true;

        if (filtroNumero) pasaNumero = f.numero_factura.toLowerCase().includes(filtroNumero.toLowerCase());
        
        if (filtroEstado) {
            if (filtroEstado === 'PENDIENTE') {
                pasaEstado = f.estado !== 'PAGADA' && f.estado !== 'ANULADA';
            } else {
                pasaEstado = f.estado === filtroEstado;
            }
        }

        if (filtroFechaInicio || filtroFechaFin) {
            const fechaFac = new Date(f.fecha_emision);
            fechaFac.setHours(0, 0, 0, 0);
            if (filtroFechaInicio) {
                const inicio = new Date(filtroFechaInicio + 'T00:00:00');
                if (fechaFac < inicio) pasaFecha = false;
            }
            if (filtroFechaFin) {
                const fin = new Date(filtroFechaFin + 'T00:00:00');
                if (fechaFac > fin) pasaFecha = false;
            }
        }

        return pasaNumero && pasaEstado && pasaFecha;
    });

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 font-sans text-slate-800 animate-fade-in pb-20">
            {modalSpotlightJSX}
            {modalAnticipoJSX}
            
            <div className="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8">
                <div>
                    <div className="flex items-center gap-2 mb-2">
                        <span className="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-indigo-200">
                            Ficha 360°
                        </span>
                        <span className="text-slate-400 text-xs font-bold px-2">|</span>
                        <button onClick={() => navigate('/proveedores')} className="text-slate-500 hover:text-indigo-600 font-bold text-xs flex items-center gap-1 transition-colors">
                            Ver Directorio Completo
                        </button>
                    </div>
                    <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Radiografía del Proveedor</h1>
                </div>
                
                <div className="flex gap-3">
                    <button onClick={() => setModalAnticipoAbierto(true)} className="bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-200 font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <i className="fas fa-hand-holding-usd"></i> Emitir Anticipo
                    </button>
                    <button onClick={abrirBuscador} className="bg-white border border-slate-200 hover:border-indigo-500 text-slate-600 hover:text-indigo-600 font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <i className="fas fa-search"></i> Cambiar <kbd className="hidden md:inline-block bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded text-[10px] font-mono ml-2">Ctrl+2</kbd>
                    </button>
                </div>
            </div>

            <div className="bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 rounded-3xl p-8 text-white shadow-2xl shadow-indigo-900/20 mb-8 relative overflow-hidden border border-slate-700">
                <i className="fas fa-globe absolute -right-10 -top-10 text-[250px] text-white opacity-5 pointer-events-none"></i>
                <div className="relative z-10 flex flex-col lg:flex-row justify-between gap-10">
                    <div className="space-y-5 flex-1">
                        <div>
                            <span className="bg-white/10 text-indigo-200 border border-white/20 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest mb-3 inline-block backdrop-blur-sm">
                                CÓDIGO INTERNO: {proveedor.codigo_interno}
                            </span>
                            <h2 className="text-4xl md:text-5xl font-black tracking-tight leading-none mb-2">{proveedor.razon_social}</h2>
                            <p className="text-indigo-300 font-mono text-lg font-medium"><i className="fas fa-fingerprint mr-2"></i>RUT: {proveedor.rut || 'Extranjero'}</p>
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
                    
                    <div className="flex flex-col justify-center gap-4 lg:min-w-[280px] shrink-0">
                        <div className="bg-slate-950/50 p-5 rounded-2xl border border-white/10 backdrop-blur-md relative overflow-hidden">
                            <div className={`absolute top-0 left-0 w-1 h-full ${totalDeuda > 0 ? 'bg-rose-500' : 'bg-slate-500'}`}></div>
                            <p className="text-[10px] text-slate-400 uppercase tracking-widest font-black mb-1">Deuda Vigente (Pendiente)</p>
                            <p className={`text-3xl font-mono font-black tracking-tight ${totalDeuda > 0 ? 'text-rose-400' : 'text-slate-300'}`}>
                                {formatCurrency(totalDeuda)}
                            </p>
                        </div>
                        {anticiposVigentes > 0 && (
                            <div className="bg-emerald-500/10 p-4 rounded-xl border border-emerald-500/30 text-right backdrop-blur-sm">
                                <p className="text-[10px] text-emerald-300 uppercase tracking-widest font-black mb-0.5">Saldo a Favor (Anticipos)</p>
                                <p className="text-xl font-mono font-bold text-emerald-400">{formatCurrency(anticiposVigentes)}</p>
                            </div>
                        )}
                        {anticiposVigentes === 0 && (
                            <div className="text-right px-2">
                                <p className="text-[10px] text-slate-400 uppercase tracking-widest font-black mb-0.5">Total Histórico Comprado</p>
                                <p className="text-xl font-mono font-bold text-indigo-200">{formatCurrency(totalComprado)}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="bg-white rounded-3xl border border-slate-200 shadow-lg shadow-slate-200/50 overflow-hidden">
                <div className="flex border-b border-slate-200 bg-slate-50/80">
                    <button 
                        onClick={() => setTabActiva('facturas')} 
                        className={`flex-1 py-4 px-6 text-sm font-black uppercase tracking-widest transition-colors flex items-center justify-center gap-2 ${tabActiva === 'facturas' ? 'text-indigo-600 bg-white border-b-2 border-indigo-600' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-100'}`}
                    >
                        <i className="fas fa-file-invoice"></i> Historial de Facturas <span className="bg-slate-200 text-slate-500 px-2 py-0.5 rounded-full text-[10px] ml-1">{facturas.length}</span>
                    </button>
                    <button 
                        onClick={() => setTabActiva('anticipos')} 
                        className={`flex-1 py-4 px-6 text-sm font-black uppercase tracking-widest transition-colors flex items-center justify-center gap-2 ${tabActiva === 'anticipos' ? 'text-emerald-600 bg-white border-b-2 border-emerald-600' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-100'}`}
                    >
                        <i className="fas fa-hand-holding-usd"></i> Anticipos Creados <span className="bg-slate-200 text-slate-500 px-2 py-0.5 rounded-full text-[10px] ml-1">{anticipos.length}</span>
                    </button>
                </div>
                
                <div className="overflow-x-auto min-h-[300px]">
                    {tabActiva === 'facturas' && (
                        <>
                            <div className="bg-white p-4 border-b border-slate-100 flex flex-wrap gap-4 items-center bg-slate-50/50">
                                <div className="flex-1 min-w-[200px]">
                                    <div className="relative w-full">
                                        <i className="fas fa-search absolute left-4 top-3.5 text-slate-400"></i>
                                        <input 
                                            type="text" 
                                            placeholder="N° Factura..." 
                                            value={filtroNumero}
                                            onChange={(e) => setFiltroNumero(e.target.value)}
                                            className="w-full pl-11 pr-4 py-2.5 border border-slate-200 rounded-xl bg-white text-sm font-medium text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 shadow-sm transition-all"
                                        />
                                    </div>
                                </div>
                                <div className="w-48">
                                    <select 
                                        value={filtroEstado}
                                        onChange={(e) => setFiltroEstado(e.target.value)}
                                        className="w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-white text-sm font-medium text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 shadow-sm cursor-pointer"
                                    >
                                        <option value="">Todos los Estados</option>
                                        <option value="PENDIENTE">Pendiente</option>
                                        <option value="PAGADA">Pagada</option>
                                        <option value="ANULADA">Anulada</option>
                                    </select>
                                </div>
                                <div className="flex items-center gap-2">
                                    <input 
                                        type="date" 
                                        value={filtroFechaInicio}
                                        onChange={(e) => setFiltroFechaInicio(e.target.value)}
                                        className="px-4 py-2.5 border border-slate-200 rounded-xl bg-white text-sm font-medium text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 shadow-sm"
                                    />
                                    <span className="text-slate-400 font-bold">-</span>
                                    <input 
                                        type="date" 
                                        value={filtroFechaFin}
                                        onChange={(e) => setFiltroFechaFin(e.target.value)}
                                        className="px-4 py-2.5 border border-slate-200 rounded-xl bg-white text-sm font-medium text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 shadow-sm"
                                    />
                                </div>
                                <span className="text-xs font-bold text-slate-400 bg-slate-100 px-3 py-1 rounded-lg ml-auto">
                                    {facturasFiltradas.length} resultados
                                </span>
                            </div>
                            
                            {facturas.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-20 text-slate-400">
                                    <div className="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4 border border-slate-100"><i className="fas fa-folder-open text-3xl opacity-50"></i></div>
                                    <p className="font-bold text-slate-500 text-lg">Historial en blanco</p>
                                    <p className="text-sm font-medium mt-1">No hemos registrado compras a este proveedor aún.</p>
                                </div>
                            ) : (
                                <table className="w-full text-left text-sm whitespace-nowrap">
                                    <thead className="bg-white border-b border-slate-100">
                                        <tr>
                                            <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase">Fecha</th>
                                            <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase">N° Factura</th>
                                            <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase text-right">Monto Neto</th>
                                            <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase text-right">Total Bruto</th>
                                            <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase text-center">Estado</th>
                                            <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase text-center">Comprobante</th>
                                            <th className="px-6 py-4 font-black text-slate-400 text-[10px] uppercase text-center">PDF</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50 bg-slate-50/30">
                                        {facturasFiltradas.map((fac) => (
                                            <tr key={fac.id} className="hover:bg-indigo-50/40 transition-colors">
                                                <td className="px-6 py-4 font-bold text-slate-500">{new Date(fac.fecha_emision).toLocaleDateString('es-CL')}</td>
                                                <td className="px-6 py-4 font-black text-slate-800 text-base"><span className="text-slate-300 font-normal mr-1">#</span>{fac.numero_factura}</td>
                                                <td className="px-6 py-4 text-right font-mono font-medium text-slate-500">{formatCurrency(fac.monto_neto)}</td>
                                                <td className="px-6 py-4 text-right font-mono font-black text-slate-900 text-base">{formatCurrency(fac.monto_bruto)}</td>
                                                <td className="px-6 py-4 text-center">
                                                    {fac.estado === 'PAGADA' && <span className="bg-emerald-100 text-emerald-700 text-[10px] font-black px-3 py-1.5 rounded-md uppercase tracking-widest shadow-sm">Pagada</span>}
                                                    {fac.estado === 'ANULADA' && <span className="bg-rose-100 text-rose-700 text-[10px] font-black px-3 py-1.5 rounded-md uppercase tracking-widest shadow-sm">Anulada</span>}
                                                    {fac.estado !== 'PAGADA' && fac.estado !== 'ANULADA' && <span className="bg-amber-100 text-amber-700 text-[10px] font-black px-3 py-1.5 rounded-md uppercase tracking-widest shadow-sm">Pendiente</span>}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    {fac.comprobante_contable ? (
                                                        <span className="inline-flex items-center gap-1 text-slate-600 font-mono text-xs font-bold bg-white border border-slate-200 px-2 py-1 rounded shadow-sm">
                                                            <i className="fas fa-link text-indigo-400"></i> {fac.comprobante_contable}
                                                        </span>
                                                    ) : <span className="text-slate-400 text-xs italic">S/N</span>}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    {fac.archivo_pdf ? (
                                                        <a href={`${import.meta.env.VITE_API_URL.replace('/api', '')}/${fac.archivo_pdf}`} target="_blank" rel="noopener noreferrer" className="inline-flex w-8 h-8 rounded-lg items-center justify-center bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-500 hover:text-white transition-all shadow-sm" title="Ver PDF">
                                                            <i className="fas fa-file-pdf"></i>
                                                        </a>
                                                    ) : fac.estado === 'ANULADA' ? (
                                                        <span className="text-slate-300 text-xs italic">-</span>
                                                    ) : (
                                                        <label className="inline-flex w-8 h-8 rounded-lg items-center justify-center bg-white border border-slate-200 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-sm cursor-pointer" title="Subir PDF">
                                                            <i className="fas fa-cloud-upload-alt"></i>
                                                            <input type="file" accept="application/pdf" className="hidden" onChange={(e) => subirPdfFactura(fac.id, e)} />
                                                        </label>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                        {facturasFiltradas.length === 0 && (
                                            <tr><td colSpan="7" className="py-12 text-center text-slate-400 font-medium">No se encontraron facturas con esos criterios.</td></tr>
                                        )}
                                    </tbody>
                                </table>
                            )}
                        </>
                    )}

                    {tabActiva === 'anticipos' && (
                        anticipos.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-20 text-slate-400">
                                <div className="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4 border border-slate-100"><i className="fas fa-receipt text-3xl opacity-50"></i></div>
                                <p className="font-bold text-slate-500 text-lg">Sin Anticipos</p>
                                <p className="text-sm font-medium mt-1">Aún no se ha solicitado ni enviado dinero por adelantado a este proveedor.</p>
                            </div>
                        ) : (
                            <table className="w-full text-left text-sm whitespace-nowrap">
                                <thead className="bg-white border-b border-slate-100">
                                    <tr>
                                        <th className="px-8 py-4 font-black text-slate-400 text-[10px] uppercase tracking-widest">Fecha</th>
                                        <th className="px-8 py-4 font-black text-slate-400 text-[10px] uppercase tracking-widest">Referencia</th>
                                        <th className="px-8 py-4 font-black text-slate-400 text-[10px] uppercase tracking-widest text-right">Monto Original</th>
                                        <th className="px-8 py-4 font-black text-emerald-500 text-[10px] uppercase tracking-widest text-right">Saldo Disponible</th>
                                        <th className="px-8 py-4 font-black text-slate-400 text-[10px] uppercase tracking-widest text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50 bg-slate-50/20">
                                    {anticipos.map((ant) => (
                                        <tr key={ant.id} className="hover:bg-emerald-50/30 transition-colors group">
                                            <td className="px-8 py-4 font-bold text-slate-500">{new Date(ant.fecha).toLocaleDateString('es-CL')}</td>
                                            <td className="px-8 py-4 font-medium text-slate-700">{ant.referencia || 'S/R'}</td>
                                            <td className="px-8 py-4 text-right font-mono font-medium text-slate-400">{formatCurrency(ant.monto)}</td>
                                            <td className="px-8 py-4 text-right font-mono font-black text-emerald-600 text-base">{formatCurrency(ant.saldo_disponible)}</td>
                                            <td className="px-8 py-4 text-center">
                                                {ant.estado === 'PENDIENTE' && <span className="bg-amber-100 text-amber-700 text-[10px] font-black px-3 py-1.5 rounded-md uppercase tracking-widest shadow-sm border border-amber-200">Por Pagar</span>}
                                                {ant.estado === 'VIGENTE' && <span className="bg-emerald-100 text-emerald-700 text-[10px] font-black px-3 py-1.5 rounded-md uppercase tracking-widest shadow-sm border border-emerald-200">Disponible</span>}
                                                {ant.estado === 'APLICADO' && <span className="bg-slate-100 text-slate-500 text-[10px] font-black px-3 py-1.5 rounded-md uppercase tracking-widest shadow-sm border border-slate-200">Aplicado</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )
                    )}
                </div>
            </div>
        </div>
    );
};

export default VisorProveedor;