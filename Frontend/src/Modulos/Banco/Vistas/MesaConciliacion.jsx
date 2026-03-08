import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';
import Select from 'react-select';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const MesaConciliacion = () => {
    const [cuentasBanco, setCuentasBanco] = useState([]);
    const [cuentaActiva, setCuentaActiva] = useState('');
    const [movimientos, setMovimientos] = useState([]);
    const [planCuentas, setPlanCuentas] = useState([]);
    const [centrosCosto, setCentrosCosto] = useState([]); 
    const [anticiposPendientes, setAnticiposPendientes] = useState([]);
    const [loading, setLoading] = useState(false);
    const [modalActivo, setModalActivo] = useState(false);
    const [movSeleccionado, setMovSeleccionado] = useState(null);
    const [tipoConciliacion, setTipoConciliacion] = useState('DIRECTA');
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
            } catch (err) { console.warn("Sin anticipos pendientes."); }

            try {
                const resCentros = await api.get('/empresas/centros-costo');
                if (resCentros.success) {
                    setCentrosCosto(resCentros.data);
                }
            } catch (err) {
                console.warn("Endpoints de centros de costo aún no disponibles.");
            }

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
            Swal.fire({
                icon: 'error', title: 'Error', text: 'Error al cargar los movimientos.',
                buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }
            });
        } finally {
            setLoading(false);
        }
    };

    const abrirModalConciliacion = (mov) => {
        setMovSeleccionado(mov);
        setGlosa(mov.descripcion); 
        setCuentaSel(null);
        setCentroSel(null);
        setEmpleadoNombre('');
        setAnticipoSelId('');
        setTipoConciliacion('DIRECTA');
        setModalActivo(true);
    };

    const ejecutarConciliacion = async () => {
        if (tipoConciliacion === 'DIRECTA') {
            if (!cuentaSel) {
                return Swal.fire({
                    icon: 'warning', title: 'Atención', text: 'Debes buscar y seleccionar una cuenta contable.',
                    buttonsStyling: false, customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg' }
                });
            }
            if (!glosa.trim()) {
                return Swal.fire({
                    icon: 'warning', title: 'Atención', text: 'La glosa no puede estar vacía.',
                    buttonsStyling: false, customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg' }
                });
            }

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
                Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.mensaje || 'Error al contabilizar.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }});
            }
        } 
        else if (tipoConciliacion === 'ANTICIPO') {
            if (!anticipoSelId) {
                return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Selecciona una solicitud de anticipo.', buttonsStyling: false, customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg' }});
            }

            try {
                Swal.fire({ title: 'Enlazando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const res = await api.post('/banco/movimientos/conciliar-anticipo', { 
                    movimiento_id: movSeleccionado.id, 
                    anticipo_id: anticipoSelId 
                });
                
                if (res.success) {
                    Swal.fire({ icon: 'success', title: '¡Anticipo Conciliado!', text: 'Se ha habilitado el saldo a favor.', timer: 2000, showConfirmButton: false });
                    cerrarModalYRecargar();
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.mensaje || 'Error al cruzar el anticipo.', buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }});
            }
        }
    };

    const cerrarModalYRecargar = () => {
        setModalActivo(false);
        cargarMovimientos(cuentaActiva);
        api.get('/banco/anticipos-pendientes').then(res => { if(res.success) setAnticiposPendientes(res.data); });
    };

    const formatHora = (horaString) => {
        if (!horaString) return '--:--';
        return horaString.substring(0, 5); 
    };

    const selectStyles = {
        control: (base, state) => ({
            ...base,
            backgroundColor: '#f8fafc',
            borderColor: state.isFocused ? '#10b981' : '#cbd5e1',
            boxShadow: state.isFocused ? '0 0 0 2px rgba(16, 185, 129, 0.2)' : 'none',
            borderRadius: '0.5rem',
            padding: '2px',
            fontSize: '0.875rem',
            fontWeight: '500',
            cursor: 'text',
            '&:hover': { borderColor: state.isFocused ? '#10b981' : '#94a3b8' }
        }),
        option: (base, state) => ({
            ...base,
            backgroundColor: state.isSelected ? '#10b981' : state.isFocused ? '#ecfdf5' : 'white',
            color: state.isSelected ? 'white' : '#334155',
            fontSize: '0.875rem',
            cursor: 'pointer',
            '&:active': { backgroundColor: '#10b981', color: 'white' }
        }),
        menuPortal: base => ({ ...base, zIndex: 9999 }) 
    };

    const getNombreBancoActivo = () => {
        const banco = cuentasBanco.find(c => c.id == cuentaActiva);
        return banco ? `[${banco.cuenta_contable || '110100'}] ${banco.banco}` : 'Cuenta Bancaria';
    };

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 animate-fade-in pb-10">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <div className="flex items-center gap-3 mb-2">
                        <span className="bg-emerald-100 text-emerald-700 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-emerald-200">
                            Auditoría y Conciliación
                        </span>
                    </div>
                    <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Mesa de Conciliación</h1>
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
                        {cuentasBanco.map(c => (
                            <option key={c.id} value={c.id}>{c.banco} - {c.numero_cuenta}</option>
                        ))}
                    </select>
                </div>
            </div>

            {loading ? (
                <div className="flex flex-col items-center justify-center py-20 text-slate-400">
                    <div className="animate-spin rounded-full h-10 w-10 border-b-4 border-emerald-500 mb-4"></div>
                    <p className="font-bold">Cargando movimientos bancarios...</p>
                </div>
            ) : movimientos.length === 0 ? (
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-16 text-center">
                    <div className="bg-emerald-50 text-emerald-500 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3 className="text-2xl font-black text-slate-800">¡Banco 100% Cuadrado!</h3>
                    <p className="text-slate-500 mt-2">No tienes movimientos pendientes por conciliar en esta cuenta.</p>
                </div>
            ) : (
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                        <h3 className="font-bold text-slate-700 flex items-center gap-2">
                            <span className="bg-amber-100 text-amber-600 px-2 py-0.5 rounded text-xs">{movimientos.length}</span>
                            Movimientos Pendientes de Imputar
                        </h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-white border-b border-slate-100">
                                <tr>
                                    <th className="px-6 py-4 font-bold text-slate-500 uppercase">Fecha y Hora</th>
                                    <th className="px-6 py-4 font-bold text-slate-500 uppercase">Descripción Original</th>
                                    <th className="px-6 py-4 font-bold text-slate-500 uppercase text-right">Cargo (Salida)</th>
                                    <th className="px-6 py-4 font-bold text-slate-500 uppercase text-right">Abono (Entrada)</th>
                                    <th className="px-6 py-4 font-bold text-slate-500 uppercase text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {movimientos.map(mov => (
                                    <tr key={mov.id} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <p className="font-bold text-slate-700">{new Date(mov.fecha).toLocaleDateString('es-CL')}</p>
                                            <p className="text-xs text-slate-400 font-mono mt-0.5 flex items-center gap-1">
                                                <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                {formatHora(mov.hora)}
                                            </p>
                                        </td>
                                        <td className="px-6 py-4">
                                            <p className="font-bold text-slate-800">{mov.descripcion}</p>
                                            {mov.nro_documento && <p className="text-xs text-slate-400 font-mono mt-0.5">Doc: {mov.nro_documento}</p>}
                                        </td>
                                        <td className="px-6 py-4 font-black text-rose-600 text-right">{mov.cargo > 0 ? formatCurrency(mov.cargo) : ''}</td>
                                        <td className="px-6 py-4 font-black text-emerald-600 text-right">{mov.abono > 0 ? formatCurrency(mov.abono) : ''}</td>
                                        <td className="px-6 py-4 text-center">
                                            <button 
                                                onClick={() => abrirModalConciliacion(mov)} 
                                                className="bg-slate-100 text-slate-700 hover:bg-slate-900 hover:text-white font-bold py-2 px-5 rounded-lg transition-all border border-slate-200 hover:border-slate-900 text-xs shadow-sm flex items-center justify-center gap-2 mx-auto"
                                            >
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
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
                <div className="fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl border border-slate-700 flex flex-col max-h-[90vh] overflow-hidden">
                        <div className="bg-slate-900 p-6 flex justify-between items-center text-white border-b border-slate-700">
                            <div>
                                <h3 className="text-xl font-black flex items-center gap-2">
                                    <svg className="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                    Asignación Contable
                                </h3>
                                <p className="text-slate-400 text-xs mt-1">Imputar movimiento bancario al Libro Mayor</p>
                            </div>
                            <button onClick={() => setModalActivo(false)} className="text-slate-400 hover:text-white transition-colors">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        {movSeleccionado.cargo > 0 && anticiposPendientes.length > 0 && (
                            <div className="flex border-b border-slate-200 bg-slate-50">
                                <button 
                                    onClick={() => setTipoConciliacion('DIRECTA')} 
                                    className={`flex-1 py-3 text-xs font-black uppercase tracking-widest transition-colors ${tipoConciliacion === 'DIRECTA' ? 'text-indigo-600 bg-white border-b-2 border-indigo-600' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'}`}
                                >
                                    Imputación Directa a Cuentas
                                </button>
                                <button 
                                    onClick={() => setTipoConciliacion('ANTICIPO')} 
                                    className={`flex-1 py-3 text-xs font-black uppercase tracking-widest transition-colors flex items-center justify-center gap-2 ${tipoConciliacion === 'ANTICIPO' ? 'text-emerald-600 bg-white border-b-2 border-emerald-600' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'}`}
                                >
                                    <i className="fas fa-link"></i> Asociar a Anticipo
                                    <span className="bg-amber-100 text-amber-600 px-1.5 py-0.5 rounded-full text-[10px] ml-1">{anticiposPendientes.length}</span>
                                </button>
                            </div>
                        )}

                        <div className="p-6 space-y-6 overflow-y-auto rounded-b-2xl">
                            <div className={`p-4 rounded-xl border ${movSeleccionado.cargo > 0 ? 'bg-rose-50 border-rose-100' : 'bg-emerald-50 border-emerald-100'} flex justify-between items-center shadow-sm`}>
                                <div>
                                    <p className={`text-[10px] font-black uppercase tracking-widest ${movSeleccionado.cargo > 0 ? 'text-rose-500' : 'text-emerald-600'} mb-1`}>
                                        {movSeleccionado.cargo > 0 ? 'Salida de Dinero (Egreso)' : 'Entrada de Dinero (Ingreso)'}
                                    </p>
                                    <p className="font-bold text-slate-800 leading-snug">{movSeleccionado.descripcion}</p>
                                </div>
                                <div className={`text-2xl font-black ${movSeleccionado.cargo > 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                                    {formatCurrency(movSeleccionado.cargo > 0 ? movSeleccionado.cargo : movSeleccionado.abono)}
                                </div>
                            </div>

                            {tipoConciliacion === 'DIRECTA' ? (
                                <div className="space-y-4 animate-fade-in">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2 flex items-center justify-between">
                                            <span>Descripción del Asiento</span>
                                        </label>
                                        <input 
                                            type="text" 
                                            value={glosa} 
                                            onChange={(e) => setGlosa(e.target.value)}
                                            className="w-full bg-white border border-slate-300 text-slate-800 font-bold rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all shadow-inner"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2 flex items-center gap-1">
                                            <svg className="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                            ¿A qué cuenta contable corresponde este movimiento?
                                        </label>
                                        <Select 
                                            options={planCuentas}
                                            value={cuentaSel}
                                            onChange={setCuentaSel}
                                            placeholder="Buscar cuenta por nombre o código..."
                                            styles={selectStyles}
                                            menuPortalTarget={document.body}
                                            menuPosition="fixed"
                                            isClearable
                                            noOptionsMessage={() => "No se encontraron cuentas"}
                                        />
                                    </div>

                                    <div className="mt-6 border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                                        <div className="bg-slate-100 px-4 py-2 border-b border-slate-200">
                                            <p className="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                Vista Previa del Asiento a Generar
                                            </p>
                                        </div>
                                        <table className="w-full text-sm text-left">
                                            <thead className="bg-white border-b border-slate-100 text-xs text-slate-400">
                                                <tr>
                                                    <th className="px-4 py-2 font-medium">Cuenta Contable</th>
                                                    <th className="px-4 py-2 font-medium text-right">Debe</th>
                                                    <th className="px-4 py-2 font-medium text-right">Haber</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-50 bg-slate-50/50">
                                                {movSeleccionado.cargo > 0 ? (
                                                    <>
                                                        <tr>
                                                            <td className="px-4 py-2 font-bold text-slate-700">{cuentaSel ? cuentaSel.label : <span className="text-rose-400 italic">Seleccione cuenta arriba...</span>}</td>
                                                            <td className="px-4 py-2 font-black text-slate-800 text-right">{formatCurrency(movSeleccionado.cargo)}</td>
                                                            <td className="px-4 py-2 text-right text-slate-300">-</td>
                                                        </tr>
                                                        <tr>
                                                            <td className="px-4 py-2 font-medium text-slate-600 ml-4 pl-8">{getNombreBancoActivo()}</td>
                                                            <td className="px-4 py-2 text-right text-slate-300">-</td>
                                                            <td className="px-4 py-2 font-black text-rose-600 text-right">{formatCurrency(movSeleccionado.cargo)}</td>
                                                        </tr>
                                                    </>
                                                ) : (
                                                    <>
                                                        <tr>
                                                            <td className="px-4 py-2 font-bold text-slate-700">{getNombreBancoActivo()}</td>
                                                            <td className="px-4 py-2 font-black text-emerald-600 text-right">{formatCurrency(movSeleccionado.abono)}</td>
                                                            <td className="px-4 py-2 text-right text-slate-300">-</td>
                                                        </tr>
                                                        <tr>
                                                            <td className="px-4 py-2 font-medium text-slate-600 ml-4 pl-8">{cuentaSel ? cuentaSel.label : <span className="text-emerald-400 italic">Seleccione cuenta arriba...</span>}</td>
                                                            <td className="px-4 py-2 text-right text-slate-300">-</td>
                                                            <td className="px-4 py-2 font-black text-slate-800 text-right">{formatCurrency(movSeleccionado.abono)}</td>
                                                        </tr>
                                                    </>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Centro de Costo <span className="text-[10px] text-slate-400 font-normal">(Opcional)</span></label>
                                            <Select options={centrosCosto} value={centroSel} onChange={setCentroSel} placeholder="Buscar centro..." styles={selectStyles} menuPortalTarget={document.body} menuPosition="fixed" isClearable noOptionsMessage={() => "No hay centros registrados"} />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Empleado Asociado <span className="text-[10px] text-slate-400 font-normal">(Opcional)</span></label>
                                            <input type="text" value={empleadoNombre} onChange={(e) => setEmpleadoNombre(e.target.value)} className="w-full bg-slate-50 border border-slate-300 text-slate-700 text-sm font-medium rounded-lg p-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Ej: Nicolás Salas" />
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-4 animate-fade-in">
                                    <div className="bg-amber-50 p-4 border border-amber-200 rounded-xl mb-4">
                                        <p className="text-xs text-amber-800 font-medium">
                                            <i className="fas fa-info-circle mr-1"></i> Selecciona la solicitud de anticipo (creada en el Visor del Proveedor) para enlazarla con esta salida de dinero real.
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Solicitud de Anticipo Pendiente</label>
                                        <select 
                                            value={anticipoSelId} 
                                            onChange={(e) => setAnticipoSelId(e.target.value)} 
                                            className="w-full bg-white border border-slate-300 font-bold text-slate-700 rounded-lg p-3 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 cursor-pointer"
                                        >
                                            <option value="">-- Elija un anticipo para cruzar --</option>
                                            {anticiposPendientes.map(ant => (
                                                <option key={ant.id} value={ant.id}>
                                                    Ref: {ant.referencia || 'S/R'} | {ant.razon_social} | {formatCurrency(ant.monto)}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="mt-4 border border-slate-200 rounded-xl overflow-hidden opacity-80">
                                        <div className="bg-slate-100 px-4 py-2 border-b border-slate-200">
                                            <p className="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
                                                <i className="fas fa-lock text-slate-400"></i> Asiento Fijo Generado
                                            </p>
                                        </div>
                                        <table className="w-full text-sm text-left">
                                            <thead className="bg-white border-b border-slate-100 text-xs text-slate-400">
                                                <tr>
                                                    <th className="px-4 py-2 font-medium">Cuenta Contable</th>
                                                    <th className="px-4 py-2 font-medium text-right">Debe</th>
                                                    <th className="px-4 py-2 font-medium text-right">Haber</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-50 bg-slate-50/50">
                                                <tr>
                                                    <td className="px-4 py-2 font-bold text-slate-700">[110205] Anticipos a Proveedores</td>
                                                    <td className="px-4 py-2 font-black text-slate-800 text-right">{formatCurrency(movSeleccionado.cargo)}</td>
                                                    <td className="px-4 py-2 text-right text-slate-300">-</td>
                                                </tr>
                                                <tr>
                                                    <td className="px-4 py-2 font-medium text-slate-600 ml-4 pl-8">{getNombreBancoActivo()}</td>
                                                    <td className="px-4 py-2 text-right text-slate-300">-</td>
                                                    <td className="px-4 py-2 font-black text-rose-600 text-right">{formatCurrency(movSeleccionado.cargo)}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            <div className="pt-2 pb-2">
                                <button 
                                    onClick={ejecutarConciliacion}
                                    className="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-4 rounded-xl shadow-lg shadow-emerald-500/30 transition-transform hover:-translate-y-0.5 flex justify-center items-center gap-2 text-sm"
                                >
                                    {tipoConciliacion === 'DIRECTA' ? (
                                        <><svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Generar Asiento Contable</>
                                    ) : (
                                        <><i className="fas fa-check-double text-lg"></i> Validar Salida de Anticipo</>
                                    )}
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