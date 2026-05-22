import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import Select from 'react-select';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const MesaConciliacion = () => {
    const [cuentasBanco, setCuentasBanco] = useState([]);
    const [cuentaActiva, setCuentaActiva] = useState('');
    const [movimientos, setMovimientos] = useState([]);
    const [planCuentas, setPlanCuentas] = useState([]);
    const [centrosCosto, setCentrosCosto] = useState([]); 
    const [anticiposPendientes, setAnticiposPendientes] = useState([]);
    
    // Estados Sugerencias y Manual
    const [sugerenciasFacturas, setSugerenciasFacturas] = useState([]);
    const [cargandoSugerencias, setCargandoSugerencias] = useState(false);
    const [modoFacturas, setModoFacturas] = useState('SUGERENCIAS'); 
    
    // Estados Búsqueda Manual
    const [entidades, setEntidades] = useState([]);
    const [entidadSel, setEntidadSel] = useState(null);
    const [facturasManuales, setFacturasManuales] = useState([]);
    const [buscandoFacturas, setBuscandoFacturas] = useState(false);
    const [facturasSeleccionadas, setFacturasSeleccionadas] = useState([]);

    const [loading, setLoading] = useState(false);
    const [modalActivo, setModalActivo] = useState(false);
    const [movSeleccionado, setMovSeleccionado] = useState(null);
    const [tipoConciliacion, setTipoConciliacion] = useState('FACTURAS'); 
    const [cuentaSel, setCuentaSel] = useState(null);
    const [centroSel, setCentroSel] = useState(null);
    const [empleadoNombre, setEmpleadoNombre] = useState(''); 
    const [glosa, setGlosa] = useState('');
    const [anticipoSelId, setAnticipoSelId] = useState('');

    useEffect(() => {
        cargarDatosBase();
    }, []);

    useEffect(() => {
        if (cuentaActiva) cargarMovimientos(cuentaActiva);
    }, [cuentaActiva]);

    const cargarDatosBase = async () => {
        try {
            const resCuentas = await api.get('/banco/cuentas');
            if (resCuentas.success) {
                setCuentasBanco(resCuentas.data);
                if (resCuentas.data.length > 0) setCuentaActiva(resCuentas.data[0].id);
            }
            
            const resPlan = await api.get('/banco/cuentas-imputables');
            if (resPlan.success) {
                const cuentasFormat = resPlan.data.map(c => ({
                    value: c.codigo,
                    label: `[${c.codigo}] ${c.nombre} (${c.tipo})`
                }));
                setPlanCuentas(cuentasFormat);
            }

            try {
                const resAnticipos = await api.get('/banco/anticipos-pendientes');
                if (resAnticipos.success) setAnticiposPendientes(resAnticipos.data);
            } catch (err) {}

            try {
                const resCentros = await api.get('/empresas/centros-costo');
                if (resCentros.success) setCentrosCosto(resCentros.data);
            } catch (err) {}

        } catch (error) {
            Swal.fire({
                icon: 'error', title: 'Error', text: 'No se pudieron cargar los datos base.',
                buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }
            });
        }
    };

    const cargarMovimientos = async (idCuenta) => {
        setLoading(true);
        try {
            const res = await api.get(`/banco/movimientos/pendientes/${idCuenta}`);
            if (res.success) setMovimientos(res.data);
        } catch (error) {
            Swal.fire('Error', 'Error al cargar los movimientos.', 'error');
        } finally {
            setLoading(false);
        }
    };

    const abrirModalConciliacion = async (mov) => {
        setMovSeleccionado(mov);
        setGlosa(mov.descripcion); 
        setCuentaSel(null);
        setCentroSel(null);
        setEmpleadoNombre('');
        setAnticipoSelId('');
        setTipoConciliacion('FACTURAS'); 
        setModoFacturas('SUGERENCIAS');
        setModalActivo(true);
        setSugerenciasFacturas([]);
        setFacturasSeleccionadas([]);
        setEntidadSel(null);
        setFacturasManuales([]);

        if (mov.cargo > 0 || mov.abono > 0) {
            setCargandoSugerencias(true);
            try {
                const res = await api.get(`/banco/movimientos/${mov.id}/sugerencias`);
                if (res.success && res.data.length > 0) {
                    setSugerenciasFacturas(res.data);
                } else {
                    setModoFacturas('MANUAL');
                    cargarEntidades(mov);
                }
            } catch (error) {
                setModoFacturas('MANUAL');
                cargarEntidades(mov);
            } finally {
                setCargandoSugerencias(false);
            }
        }
    };

    const cargarEntidades = async (mov) => {
        const esEgreso = mov.cargo > 0;
        try {
            const endpoint = esEgreso ? '/proveedores/catalogo' : '/clientes';
            const res = await api.get(endpoint);
            const dataArr = res.success ? res.data : (Array.isArray(res) ? res : []);
            
            const options = dataArr.map(e => ({
                value: e.id,
                label: `${e.rut || ''} - ${e.razon_social || e.nombre}`
            }));
            setEntidades(options);
        } catch (error) {}
    };

    const handleCambioModo = (modo) => {
        setModoFacturas(modo);
        if (modo === 'MANUAL' && entidades.length === 0) {
            cargarEntidades(movSeleccionado);
        }
    };

    const handleEntidadSeleccionada = async (selected) => {
        setEntidadSel(selected);
        setFacturasManuales([]);
        setFacturasSeleccionadas([]);
        if (!selected) return;

        setBuscandoFacturas(true);
        const esEgreso = movSeleccionado.cargo > 0;
        const tipo = esEgreso ? 'COMPRA' : 'VENTA';
        
        try {
            const res = await api.get('/facturas');
            const todas = res.success ? res.data : (res.data?.data || []);
            const pendientes = todas.filter(f => 
                f.tipo === tipo && 
                f.estado !== 'PAGADA' && 
                (esEgreso ? f.proveedor_id === selected.value : f.cliente_id === selected.value)
            );
            
            setFacturasManuales(pendientes);
        } catch (error) {
            Swal.fire('Error', 'No se pudieron cargar las facturas de esta entidad.', 'error');
        } finally {
            setBuscandoFacturas(false);
        }
    };

    const toggleFacturaManual = (factura) => {
        const yaExiste = facturasSeleccionadas.find(f => f.id === factura.id);
        if (yaExiste) {
            setFacturasSeleccionadas(facturasSeleccionadas.filter(f => f.id !== factura.id));
        } else {
            setFacturasSeleccionadas([...facturasSeleccionadas, factura]);
        }
    };

    const ejecutarConciliacion = async () => {
        if (tipoConciliacion === 'FACTURAS') {
            const facturasAProcesar = modoFacturas === 'SUGERENCIAS' ? sugerenciasFacturas : facturasSeleccionadas;
            
            if (facturasAProcesar.length === 0 && !entidadSel) {
                return Swal.fire('Atención', 'Selecciona al menos una factura o un proveedor/cliente para generar un anticipo.', 'warning');
            }

            try {
                Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                const payload = {
                    movimiento_id: movSeleccionado.id,
                    facturas_ids: facturasAProcesar.map(f => f.id),
                    entidad_id: entidadSel ? entidadSel.value : null
                };
                
                const res = await api.post('/banco/movimientos/conciliar-facturas', payload);
                
                if (res.success) {
                    const comprobante = res.asiento?.numero_comprobante || 'Generado';
                    Swal.fire({ 
                        icon: 'success', 
                        title: '¡Completado!', 
                        text: `Conciliación exitosa. Comprobante Contable N° ${comprobante}`, 
                        timer: 3500, 
                        showConfirmButton: false 
                    });
                    cerrarModalYRecargar();
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error Operativo', text: error.response?.data?.message || 'Error al procesar.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }});
            }
        }
        else if (tipoConciliacion === 'DIRECTA') {
            if (!cuentaSel) return Swal.fire('Atención', 'Debes seleccionar una cuenta contable.', 'warning');
            if (!glosa.trim()) return Swal.fire('Atención', 'La glosa no puede estar vacía.', 'warning');

            try {
                Swal.fire({ title: 'Contabilizando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const payload = {
                    movimiento_id: movSeleccionado.id,
                    cuenta_codigo: cuentaSel.value,
                    glosa: glosa,
                    centro_costo_id: centroSel ? centroSel.value : null,
                    empleado_nombre: empleadoNombre.trim() || null
                };
                
                const res = await api.post('/banco/movimientos/conciliar', payload);
                
                if (res.success) {
                    Swal.fire({ icon: 'success', title: '¡Asiento Generado!', text: 'Contabilizado con éxito.', timer: 2000, showConfirmButton: false });
                    cerrarModalYRecargar();
                }
            } catch (error) {
                Swal.fire('Error', error.response?.data?.mensaje || 'Error al contabilizar.', 'error');
            }
        } 
        else if (tipoConciliacion === 'ANTICIPO') {
            if (!anticipoSelId) return Swal.fire('Atención', 'Selecciona una solicitud de anticipo.', 'warning');

            try {
                Swal.fire({ title: 'Enlazando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const res = await api.post('/banco/movimientos/conciliar-anticipo', { 
                    movimiento_id: movSeleccionado.id, 
                    anticipo_id: anticipoSelId 
                });
                
                if (res.success) {
                    Swal.fire({ icon: 'success', title: '¡Anticipo Enlazado!', text: 'El movimiento cubrió la solicitud de anticipo.', timer: 2000, showConfirmButton: false });
                    cerrarModalYRecargar();
                }
            } catch (error) {
                Swal.fire('Error', error.response?.data?.mensaje || 'Error al cruzar el anticipo.', 'error');
            }
        }
    };

    const cerrarModalYRecargar = () => {
        setModalActivo(false);
        cargarMovimientos(cuentaActiva);
        api.get('/banco/anticipos-pendientes').then(res => { if(res.success) setAnticiposPendientes(res.data); });
    };

    const selectStyles = {
        control: (base, state) => ({
            ...base,
            backgroundColor: '#f8fafc',
            borderColor: state.isFocused ? '#3b82f6' : '#e2e8f0',
            boxShadow: state.isFocused ? '0 0 0 3px rgba(59, 130, 246, 0.1)' : 'none',
            borderRadius: '0.75rem',
            padding: '4px',
            fontSize: '0.875rem',
            fontWeight: '600',
            cursor: 'text'
        }),
        menuPortal: base => ({ ...base, zIndex: 9999 }) 
    };

    const getNombreBancoActivo = () => {
        const banco = cuentasBanco.find(c => c.id == cuentaActiva);
        return banco ? `[${banco.cuenta_contable || '110100'}] ${banco.banco || 'Banco'}` : 'Cuenta Bancaria';
    };

    const facturasAProcesar = modoFacturas === 'SUGERENCIAS' ? sugerenciasFacturas : facturasSeleccionadas;
    const totalSugerido = facturasAProcesar.reduce((acc, f) => acc + Number(f.monto_bruto || f.monto_total), 0);
    const montoMovimiento = movSeleccionado ? (movSeleccionado.cargo > 0 ? movSeleccionado.cargo : movSeleccionado.abono) : 0;
    const esMatchPerfecto = totalSugerido === montoMovimiento;

    let textoBotonFactura = "Seleccione Información";
    let iconBotonFactura = "fa-check";
    if (modoFacturas === 'MANUAL' && facturasAProcesar.length === 0 && entidadSel) {
        textoBotonFactura = "Registrar como Anticipo Directo";
        iconBotonFactura = "fa-forward";
    } else if (facturasAProcesar.length > 0) {
        textoBotonFactura = esMatchPerfecto ? "Aprobar Pago Exacto" : (totalSugerido < montoMovimiento ? "Aprobar Pago + Anticipo" : "Aprobar Pago Parcial");
        iconBotonFactura = esMatchPerfecto ? "fa-check-double" : "fa-adjust";
    }

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 animate-fade-in pb-10">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <div className="flex items-center gap-3"><h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Mesa de Conciliación</h1><AyudaModulo moduloId="conciliacion" size={28} /></div>
                    <p className="text-slate-500 font-medium mt-1">Asigna las entradas y salidas del banco a tu contabilidad general.</p>
                </div>
                <div className="w-full md:w-auto bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
                    <span className="text-xs font-bold text-slate-400 uppercase ml-2 flex items-center gap-1">
                        <svg className="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        Cuenta:
                    </span>
                    <select
                        value={cuentaActiva}
                        onChange={(e) => setCuentaActiva(e.target.value)}
                        className="bg-slate-50 border border-slate-200 text-slate-800 font-bold py-2 px-4 rounded-lg outline-none cursor-pointer focus:ring-2 focus:ring-blue-500"
                    >
                        {cuentasBanco.length === 0 ? <option value="">Sin cuentas registradas</option> : cuentasBanco.map(c => <option key={c.id} value={c.id}>{c.banco} - {c.numero_cuenta}</option>)}
                    </select>
                </div>
            </div>

            {loading ? (
                <EstadoCarga
                    cargando={true}
                    mensajeCargando="Cargando movimientos bancarios..."
                    tamano="completo"
                    color="blue"
                />
            ) : movimientos.length === 0 ? (
                <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-16 text-center">
                    <div className="bg-emerald-50 text-emerald-500 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3 className="text-2xl font-black text-slate-800">¡Banco 100% Cuadrado!</h3>
                    <p className="text-slate-500 mt-2">No tienes movimientos pendientes por conciliar en esta cuenta.</p>
                </div>
            ) : (
                <div className="bg-white rounded-3xl border border-slate-200 shadow-md overflow-hidden">
                    <div className="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                        <h3 className="font-bold text-slate-700 flex items-center gap-2">
                            <span className="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-xs shadow-inner">{movimientos.length}</span>
                            Movimientos Pendientes
                        </h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-white border-b border-slate-100">
                                <tr>
                                    <th className="px-6 py-4 font-bold text-slate-400 uppercase tracking-wider text-xs">Fecha</th>
                                    <th className="px-6 py-4 font-bold text-slate-400 uppercase tracking-wider text-xs">Descripción</th>
                                    <th className="px-6 py-4 font-bold text-slate-400 uppercase tracking-wider text-xs text-right">Cargo</th>
                                    <th className="px-6 py-4 font-bold text-slate-400 uppercase tracking-wider text-xs text-right">Abono</th>
                                    <th className="px-6 py-4 font-bold text-slate-400 uppercase tracking-wider text-xs text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {movimientos.map(mov => (
                                    <tr key={mov.id} className="hover:bg-slate-50/80 transition-colors group">
                                        <td className="px-6 py-4 whitespace-nowrap font-bold text-slate-700">{new Date(mov.fecha).toLocaleDateString('es-CL')}</td>
                                        <td className="px-6 py-4 font-bold text-slate-800">{mov.descripcion}</td>
                                        <td className="px-6 py-4 font-black text-rose-500 text-right">{mov.cargo > 0 ? formatCurrency(mov.cargo) : ''}</td>
                                        <td className="px-6 py-4 font-black text-emerald-500 text-right">{mov.abono > 0 ? formatCurrency(mov.abono) : ''}</td>
                                        <td className="px-6 py-4 text-center">
                                            <button onClick={() => abrirModalConciliacion(mov)} className="bg-white text-blue-600 hover:bg-blue-600 hover:text-white font-bold py-2.5 px-5 rounded-xl transition-all text-xs border border-blue-200 shadow-sm opacity-90 group-hover:opacity-100">
                                                Conciliar
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {modalActivo && movSeleccionado && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[95vh] overflow-hidden border border-slate-100 animate-fade-in-up">
                        <div className="bg-slate-900 p-6 flex justify-between items-center text-white relative overflow-hidden">
                            <div className="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white opacity-5 rounded-full blur-2xl"></div>
                            <div className="relative z-10">
                                <h3 className="text-2xl font-black flex items-center gap-2">
                                    <i className="fas fa-layer-group text-blue-400"></i> Plataforma de Conciliación
                                </h3>
                                <p className="text-slate-400 text-xs mt-1 tracking-wider uppercase">Asignación Contable de Movimiento</p>
                            </div>
                            <button onClick={() => setModalActivo(false)} className="text-slate-400 hover:text-white bg-slate-800 hover:bg-rose-500 p-2 rounded-full transition-all relative z-10">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <div className="flex border-b border-slate-200 bg-slate-50/80">
                            <button onClick={() => setTipoConciliacion('FACTURAS')} className={`flex-1 py-4 px-4 text-xs font-black uppercase tracking-widest transition-all ${tipoConciliacion === 'FACTURAS' ? 'text-blue-600 bg-white border-b-2 border-blue-600 shadow-sm' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'}`}>
                                <i className="fas fa-file-invoice mr-2"></i> Pago / Cobro Facturas
                            </button>
                            <button onClick={() => setTipoConciliacion('DIRECTA')} className={`flex-1 py-4 px-4 text-xs font-black uppercase tracking-widest transition-all ${tipoConciliacion === 'DIRECTA' ? 'text-indigo-600 bg-white border-b-2 border-indigo-600 shadow-sm' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'}`}>
                                <i className="fas fa-project-diagram mr-2"></i> Imputación Directa
                            </button>
                        </div>

                        <div className="p-8 space-y-8 overflow-y-auto bg-slate-50/30">
                            <div className="bg-white p-5 rounded-2xl border border-slate-200 flex justify-between items-center shadow-sm relative overflow-hidden">
                                <div className={`absolute left-0 top-0 bottom-0 w-1 ${movSeleccionado.cargo > 0 ? 'bg-rose-500' : 'bg-emerald-500'}`}></div>
                                <div className="pl-3">
                                    <p className={`text-[10px] font-black uppercase tracking-widest ${movSeleccionado.cargo > 0 ? 'text-rose-500' : 'text-emerald-500'} mb-1`}>
                                        {movSeleccionado.cargo > 0 ? 'Salida de Dinero (Egreso)' : 'Entrada de Dinero (Ingreso)'}
                                    </p>
                                    <p className="font-bold text-slate-800 text-lg leading-snug">{movSeleccionado.descripcion}</p>
                                </div>
                                <div className={`text-3xl font-black ${movSeleccionado.cargo > 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                                    {formatCurrency(montoMovimiento)}
                                </div>
                            </div>

                            {tipoConciliacion === 'FACTURAS' && (
                                <div className="space-y-6 animate-fade-in">
                                    <div className="flex bg-slate-100 p-1.5 rounded-xl w-full max-w-sm mx-auto shadow-inner">
                                        <button onClick={() => handleCambioModo('SUGERENCIAS')} className={`flex-1 py-2.5 text-xs font-black uppercase tracking-wide rounded-lg transition-all ${modoFacturas === 'SUGERENCIAS' ? 'bg-white text-blue-600 shadow border border-slate-200' : 'text-slate-400 hover:text-slate-600'}`}>
                                            <i className="fas fa-magic mr-1"></i> Sugerencia
                                        </button>
                                        <button onClick={() => handleCambioModo('MANUAL')} className={`flex-1 py-2.5 text-xs font-black uppercase tracking-wide rounded-lg transition-all ${modoFacturas === 'MANUAL' ? 'bg-white text-indigo-600 shadow border border-slate-200' : 'text-slate-400 hover:text-slate-600'}`}>
                                            <i className="fas fa-search mr-1"></i> Búsqueda Manual
                                        </button>
                                    </div>

                                    {modoFacturas === 'SUGERENCIAS' ? (
                                        cargandoSugerencias ? (
                                            <EstadoCarga
                                                cargando={true}
                                                mensajeCargando="Buscando match exacto..."
                                                tamano="compacto"
                                                color="blue"
                                            />
                                        ) : sugerenciasFacturas.length > 0 ? (
                                            <div className="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                                                <table className="w-full text-left text-sm">
                                                    <thead className="bg-slate-50 text-slate-500 text-[10px] uppercase font-bold tracking-widest border-b border-slate-200">
                                                        <tr>
                                                            <th className="px-5 py-4">Documento</th>
                                                            <th className="px-5 py-4">Entidad</th>
                                                            <th className="px-5 py-4 text-right">Monto a Pagar</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100">
                                                        {sugerenciasFacturas.map(f => (
                                                            <tr key={f.id}>
                                                                <td className="px-5 py-4 font-bold text-slate-800">Doc {f.numero_factura}</td>
                                                                <td className="px-5 py-4 text-slate-600">{f.proveedor?.razon_social || f.cliente?.razon_social}</td>
                                                                <td className="px-5 py-4 font-black text-right">{formatCurrency(f.monto_bruto || f.monto_total)}</td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <div className="py-10 text-center border-2 border-dashed border-slate-300 rounded-2xl bg-white shadow-sm">
                                                <div className="bg-slate-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                                                    <i className="fas fa-search text-2xl"></i>
                                                </div>
                                                <p className="text-slate-600 font-bold mb-4">No se encontró una factura por ese monto exacto.</p>
                                                <button onClick={() => handleCambioModo('MANUAL')} className="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-blue-700 shadow-md shadow-blue-200 transition-all">
                                                    Buscar Cliente/Proveedor Manualmente
                                                </button>
                                            </div>
                                        )
                                    ) : (
                                        <div className="bg-white p-6 border border-slate-200 rounded-2xl shadow-sm">
                                            <label className="block text-xs font-bold text-slate-500 uppercase mb-2 tracking-wide">
                                                Seleccionar {movSeleccionado.cargo > 0 ? 'Proveedor' : 'Cliente'}
                                            </label>
                                            <Select 
                                                options={entidades}
                                                value={entidadSel}
                                                onChange={handleEntidadSeleccionada}
                                                placeholder={`Busca el ${movSeleccionado.cargo > 0 ? 'proveedor' : 'cliente'}...`}
                                                styles={selectStyles}
                                                menuPortalTarget={document.body}
                                                menuPosition="fixed"
                                            />

                                            {entidadSel && (
                                                <div className="mt-6 border-t border-slate-100 pt-6">
                                                    {buscandoFacturas ? (
                                                        <p className="text-sm font-medium text-slate-400 text-center">Consultando registros...</p>
                                                    ) : facturasManuales.length === 0 ? (
                                                        <div className="bg-blue-50 border border-blue-200 p-4 rounded-xl text-center">
                                                            <p className="text-sm text-blue-700 font-bold mb-1">Entidad sin deudas.</p>
                                                            <p className="text-xs text-blue-600">Al confirmar, el monto completo quedará a su favor como <b>Anticipo Directo</b>.</p>
                                                        </div>
                                                    ) : (
                                                        <div className="border border-slate-200 rounded-xl overflow-hidden max-h-56 overflow-y-auto shadow-inner">
                                                            <table className="w-full text-sm text-left">
                                                                <thead className="bg-slate-100 sticky top-0 border-b border-slate-200">
                                                                    <tr>
                                                                        <th className="px-4 py-3 w-12 text-center"><i className="fas fa-check-square text-slate-400"></i></th>
                                                                        <th className="px-4 py-3 text-xs font-bold text-slate-500 uppercase">Doc N°</th>
                                                                        <th className="px-4 py-3 text-xs font-bold text-slate-500 uppercase text-right">Monto Factura</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody className="divide-y divide-slate-100">
                                                                    {facturasManuales.map(f => {
                                                                        const seleccionado = facturasSeleccionadas.some(fs => fs.id === f.id);
                                                                        return (
                                                                            <tr key={f.id} className={`cursor-pointer transition-colors ${seleccionado ? 'bg-blue-50/80' : 'hover:bg-slate-50'}`} onClick={() => toggleFacturaManual(f)}>
                                                                                <td className="px-4 py-3 text-center">
                                                                                    <div className={`w-5 h-5 rounded mx-auto flex items-center justify-center border transition-colors ${seleccionado ? 'bg-blue-600 border-blue-600' : 'bg-white border-slate-300'}`}>
                                                                                        {seleccionado && <i className="fas fa-check text-white text-[10px]"></i>}
                                                                                    </div>
                                                                                </td>
                                                                                <td className="px-4 py-3 font-bold text-slate-700">#{f.numero_factura}</td>
                                                                                <td className="px-4 py-3 font-black text-right text-slate-800">{formatCurrency(f.monto_bruto || f.monto_total)}</td>
                                                                            </tr>
                                                                        );
                                                                    })}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {facturasAProcesar.length > 0 && (
                                        <div className={`p-4 border rounded-xl flex justify-between items-center ${esMatchPerfecto ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'}`}>
                                            <div>
                                                <p className="text-xs font-bold text-slate-500 uppercase">Total Seleccionado</p>
                                                <p className={`text-xl font-black ${esMatchPerfecto ? 'text-emerald-700' : 'text-amber-700'}`}>{formatCurrency(totalSugerido)}</p>
                                            </div>
                                            {!esMatchPerfecto && (
                                                <div className="text-right max-w-xs">
                                                    <span className="bg-amber-200 text-amber-800 text-[10px] font-bold px-2 py-1 rounded uppercase">Descuadre Detectado</span>
                                                    <p className="text-[10px] text-amber-700 mt-1 leading-tight">La factura no coincide con el pago. Se procesará como <b>Pago Parcial / Diferencia</b>.</p>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}

                            {tipoConciliacion === 'DIRECTA' && (
                                <div className="space-y-6 animate-fade-in">
                                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                                        <div className="mb-5">
                                            <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Glosa del Asiento</label>
                                            <input 
                                                type="text" 
                                                value={glosa} 
                                                onChange={(e) => setGlosa(e.target.value)}
                                                className="w-full bg-slate-50 border border-slate-300 text-slate-800 font-bold rounded-xl p-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                            />
                                        </div>
                                        <div className="mb-2">
                                            <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Cuenta Contable a Imputar</label>
                                            <Select options={planCuentas} value={cuentaSel} onChange={setCuentaSel} styles={selectStyles} menuPortalTarget={document.body} menuPosition="fixed" />
                                        </div>
                                    </div>

                                    <div className="border border-slate-200 rounded-2xl overflow-hidden shadow-md bg-white">
                                        <div className="bg-slate-800 px-5 py-3 flex justify-between items-center">
                                            <p className="text-xs font-black text-white uppercase tracking-widest flex items-center gap-2">
                                                <i className="fas fa-balance-scale"></i> Vista Previa Contable
                                            </p>
                                        </div>
                                        <table className="w-full text-sm text-left">
                                            <thead className="bg-slate-50 border-b border-slate-100 text-[10px] text-slate-400 uppercase font-bold tracking-widest">
                                                <tr>
                                                    <th className="px-5 py-3">Código y Nombre de Cuenta</th>
                                                    <th className="px-5 py-3 text-right">Debe</th>
                                                    <th className="px-5 py-3 text-right">Haber</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-50">
                                                {movSeleccionado.cargo > 0 ? (
                                                    <>
                                                        <tr>
                                                            <td className="px-5 py-4 font-bold text-slate-700">{cuentaSel ? cuentaSel.label : <span className="text-rose-400 italic font-medium">Seleccione cuenta arriba...</span>}</td>
                                                            <td className="px-5 py-4 font-black text-slate-800 text-right">{formatCurrency(movSeleccionado.cargo)}</td>
                                                            <td className="px-5 py-4 text-right text-slate-300">-</td>
                                                        </tr>
                                                        <tr>
                                                            <td className="px-5 py-4 font-medium text-slate-600 pl-10"><span className="text-slate-400 mr-2">↳</span> {getNombreBancoActivo()}</td>
                                                            <td className="px-5 py-4 text-right text-slate-300">-</td>
                                                            <td className="px-5 py-4 font-black text-rose-600 text-right">{formatCurrency(movSeleccionado.cargo)}</td>
                                                        </tr>
                                                    </>
                                                ) : (
                                                    <>
                                                        <tr>
                                                            <td className="px-5 py-4 font-bold text-slate-700">{getNombreBancoActivo()}</td>
                                                            <td className="px-5 py-4 font-black text-emerald-600 text-right">{formatCurrency(movSeleccionado.abono)}</td>
                                                            <td className="px-5 py-4 text-right text-slate-300">-</td>
                                                        </tr>
                                                        <tr>
                                                            <td className="px-5 py-4 font-medium text-slate-600 pl-10"><span className="text-slate-400 mr-2">↳</span> {cuentaSel ? cuentaSel.label : <span className="text-emerald-400 italic font-medium">Seleccione cuenta arriba...</span>}</td>
                                                            <td className="px-5 py-4 text-right text-slate-300">-</td>
                                                            <td className="px-5 py-4 font-black text-slate-800 text-right">{formatCurrency(movSeleccionado.abono)}</td>
                                                        </tr>
                                                    </>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Centro de Costo</label>
                                            <Select options={centrosCosto} value={centroSel} onChange={setCentroSel} placeholder="Opcional..." styles={selectStyles} menuPortalTarget={document.body} menuPosition="fixed" isClearable />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Empleado Relacionado</label>
                                            <input type="text" value={empleadoNombre} onChange={(e) => setEmpleadoNombre(e.target.value)} className="w-full bg-slate-50 border border-slate-300 text-slate-700 text-sm font-medium rounded-xl p-[9px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="Opcional..." />
                                        </div>
                                    </div>
                                </div>
                            )}

                            <div className="pt-2">
                                <button 
                                    onClick={ejecutarConciliacion}
                                    disabled={tipoConciliacion === 'FACTURAS' && facturasAProcesar.length === 0 && !entidadSel}
                                    className={`w-full text-white font-black py-4 rounded-2xl shadow-xl transition-all transform hover:-translate-y-1 flex justify-center items-center gap-3 text-sm tracking-wide disabled:opacity-50 disabled:transform-none ${!esMatchPerfecto && tipoConciliacion === 'FACTURAS' ? 'bg-gradient-to-r from-amber-500 to-amber-600 shadow-amber-500/30' : (tipoConciliacion === 'DIRECTA' ? 'bg-gradient-to-r from-indigo-600 to-blue-600 shadow-indigo-500/30' : 'bg-gradient-to-r from-emerald-500 to-emerald-600 shadow-emerald-500/30')}`}
                                >
                                    <i className={`fas ${iconBotonFactura} text-lg`}></i> 
                                    {tipoConciliacion === 'DIRECTA' ? 'Generar Asiento Contable' : textoBotonFactura}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default MesaConciliacion;