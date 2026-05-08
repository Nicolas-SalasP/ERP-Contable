import React, { useState, useEffect, useRef } from 'react';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const CartolaBancaria = () => {
    const [cuentas, setCuentas] = useState([]);
    const [cuentaActiva, setCuentaActiva] = useState('');
    const [movimientos, setMovimientos] = useState([]);
    const [loading, setLoading] = useState(false);
    const [archivo, setArchivo] = useState(null);
    const fileInputRef = useRef(null);

    // FIX: Ahora el formulario controla el tipo de movimiento (INGRESO o EGRESO)
    const [formManual, setFormManual] = useState({ 
        fecha: new Date().toISOString().split('T')[0], 
        descripcion: '', 
        monto: '', 
        nro_documento: '',
        tipo_movimiento: 'INGRESO'
    });

    useEffect(() => {
        cargarCuentas();
    }, []);

    useEffect(() => {
        if (cuentaActiva) {
            cargarMovimientos();
        }
    }, [cuentaActiva]);

    const cargarCuentas = async () => {
        try {
            const res = await api.get('/banco/cuentas');
            if (res.success) {
                setCuentas(res.data);
                if (res.data.length > 0) setCuentaActiva(res.data[0].id);
            }
        } catch (error) {
            Swal.fire('Error', 'No se pudieron cargar las cuentas bancarias.', 'error');
        }
    };

    const cargarMovimientos = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/banco/movimientos/${cuentaActiva}`);
            if (res.success) {
                setMovimientos(res.data);
            }
        } catch (error) {
            console.error("Error cargando movimientos:", error);
        } finally {
            setLoading(false);
        }
    };

    // --- MANEJO DEL EXCEL ---
    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls') || file.name.endsWith('.csv'))) {
            setArchivo(file);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Archivo inválido',
                text: 'Solo se permiten archivos Excel (.xlsx, .xls) o CSV.',
                buttonsStyling: false,
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }
            });
            e.target.value = null;
        }
    };

    const subirExcel = async () => {
        if (!archivo) return;
        if (!cuentaActiva) return Swal.fire('Atención', 'Seleccione una cuenta bancaria destino.', 'warning');

        const formData = new FormData();
        formData.append('archivo_excel', archivo);
        formData.append('cuenta_bancaria_id', cuentaActiva);

        Swal.fire({ title: 'Procesando Cartola...', text: 'Analizando ingresos y egresos...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            let token = localStorage.getItem('token') || sessionStorage.getItem('erp_token');
            if (token && token.startsWith('"')) token = JSON.parse(token);

            const response = await fetch(`${api.defaults?.baseURL || 'http://localhost/ERP-Contable/Backend/Public/api'}/banco/cartola/importar`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}` },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Cartola Importada!',
                    text: data.mensaje,
                    customClass: { confirmButton: 'bg-emerald-600 text-white font-bold py-2 px-6 rounded-lg' },
                    buttonsStyling: false
                });
                setArchivo(null);
                if (fileInputRef.current) fileInputRef.current.value = '';
                cargarCuentas();
            } else {
                throw new Error(data.mensaje);
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de Importación',
                text: error.message || 'El archivo no tiene el formato correcto.',
                buttonsStyling: false,
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }
            });
        }
    };

    // --- REGISTRO MANUAL DE MOVIMIENTO ---
    const guardarIngresoManual = async () => {
        if (!formManual.monto || !formManual.descripcion || !formManual.fecha) {
            return Swal.fire('Faltan Datos', 'Complete todos los campos obligatorios.', 'warning');
        }

        try {
            Swal.fire({ title: 'Registrando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            // FIX: Enviamos el payload completo al backend
            const payload = { ...formManual, cuenta_bancaria_id: cuentaActiva };
            const res = await api.post('/banco/ingreso-manual', payload);

            if (res.success) {
                Swal.fire({ icon: 'success', title: '¡Registrado!', text: 'El movimiento ha sido guardado exitosamente.', timer: 1500, showConfirmButton: false });
                setFormManual({ ...formManual, descripcion: '', monto: '', nro_documento: '' });
                cargarMovimientos();
            }
        } catch (error) {
            // FIX: Interceptamos y formateamos los errores de validación (422) que devuelve Laravel
            let mensajeError = "No se pudo registrar el movimiento.";
            
            if (error.response?.data?.errors) {
                const erroresLaravel = Object.values(error.response.data.errors).flat();
                mensajeError = erroresLaravel.join('\n');
            } else if (error.response?.data?.message) {
                mensajeError = error.response.data.message;
            } else if (error.message) {
                mensajeError = error.message;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error de Validación',
                text: mensajeError,
                buttonsStyling: false,
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }
            });
        }
    };

    const cuentaSeleccionada = cuentas.find(c => c.id == cuentaActiva);
    const esIngreso = formManual.tipo_movimiento === 'INGRESO';

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 animate-fade-in pb-10">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <div className="flex items-center gap-3 mb-2">
                        <span className="bg-blue-100 text-blue-700 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-blue-200">
                            Tesorería y Finanzas
                        </span>
                    </div>
                    <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Cartola y Movimientos</h1>
                    <p className="text-slate-500 font-medium mt-1">Registra ingresos manuales o importa la cartola del banco.</p>
                </div>
            </div>

            {/* SELECTOR DE CUENTA Y SALDO */}
            <div className="bg-slate-900 rounded-2xl p-6 shadow-xl text-white mb-8 flex flex-col md:flex-row justify-between items-center gap-6">
                <div className="w-full md:w-1/2">
                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <svg className="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        Cuenta Bancaria de Trabajo
                    </label>
                    <select
                        value={cuentaActiva}
                        onChange={(e) => setCuentaActiva(e.target.value)}
                        className="w-full bg-slate-800 border border-slate-700 text-white rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500 font-bold cursor-pointer"
                    >
                        {cuentas.length === 0 && <option value="">No hay cuentas registradas</option>}
                        {cuentas.map(c => (
                            <option key={c.id} value={c.id}>{c.banco} - {c.numero_cuenta}</option>
                        ))}
                    </select>
                </div>

                <div className="text-center md:text-right bg-slate-800 px-8 py-4 rounded-xl border border-slate-700 w-full md:w-auto">
                    <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Saldo Actual Contable</p>
                    <p className="text-3xl font-black text-emerald-400 truncate max-w-xs">
                        {cuentaSeleccionada ? formatCurrency(cuentaSeleccionada.saldo_actual) : '$0'}
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* ZONA 1: IMPORTADOR EXCEL */}
                <div className="bg-white p-6 md:p-8 rounded-2xl border border-slate-200 shadow-sm flex flex-col h-full">
                    <h3 className="text-lg font-black text-slate-800 flex items-center gap-2 mb-2">
                        <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        Importar Cartola del Banco
                    </h3>
                    <p className="text-sm text-slate-500 mb-6">Sube el archivo Excel descargado desde tu portal bancario para registrar abonos y cargos automáticamente.</p>

                    <div
                        className={`border-2 border-dashed rounded-2xl p-8 text-center transition-colors flex-1 flex flex-col justify-center items-center ${archivo ? 'border-blue-500 bg-blue-50' : 'border-slate-300 hover:border-blue-400 bg-slate-50'}`}
                    >
                        <input
                            type="file"
                            accept=".xlsx, .xls, .csv"
                            className="hidden"
                            ref={fileInputRef}
                            onChange={handleFileChange}
                        />

                        {!archivo ? (
                            <>
                                <div className="bg-white p-4 rounded-full shadow-sm mb-4 text-blue-500">
                                    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <p className="font-bold text-slate-700 mb-1">Arrastra tu Excel aquí</p>
                                <p className="text-xs text-slate-500 mb-4">Acepta archivos .xlsx, .xls y .csv</p>
                                <button onClick={() => fileInputRef.current.click()} className="bg-white border border-slate-200 text-slate-700 font-bold py-2 px-6 rounded-lg hover:bg-slate-100 transition-colors shadow-sm text-sm">
                                    Explorar Archivos
                                </button>
                            </>
                        ) : (
                            <>
                                <div className="bg-emerald-100 text-emerald-600 p-4 rounded-full mb-4">
                                    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <p className="font-bold text-slate-800 mb-1">{archivo.name}</p>
                                <p className="text-xs text-slate-500 mb-6">{(archivo.size / 1024).toFixed(1)} KB</p>
                                <div className="flex gap-3 w-full max-w-xs">
                                    <button onClick={() => { setArchivo(null); fileInputRef.current.value = ''; }} className="flex-1 bg-white border border-slate-200 text-slate-600 font-bold py-2.5 rounded-lg hover:bg-slate-100 transition-colors text-sm">
                                        Cancelar
                                    </button>
                                    <button onClick={subirExcel} className="flex-1 bg-blue-600 text-white font-bold py-2.5 rounded-lg hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-colors text-sm">
                                        Procesar
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </div>

                {/* ZONA 2: REGISTRO MANUAL (INGRESO O EGRESO) */}
                <div className="bg-white p-6 md:p-8 rounded-2xl border border-slate-200 shadow-sm flex flex-col h-full">
                    <h3 className="text-lg font-black text-slate-800 flex items-center gap-2 mb-2">
                        <svg className={`w-5 h-5 ${esIngreso ? 'text-emerald-500' : 'text-rose-500'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Registro Manual
                    </h3>
                    <p className="text-sm text-slate-500 mb-4">Ingresa transacciones aisladas que no pasaron por la importación masiva de la cartola.</p>

                    <div className="space-y-4 flex-1 flex flex-col justify-between">
                        <div className="space-y-4">
                            
                            {/* FIX: Selector visual de INGRESO vs EGRESO */}
                            <div className="bg-slate-100 p-1 rounded-xl flex gap-1 mb-2">
                                <button
                                    onClick={() => setFormManual({ ...formManual, tipo_movimiento: 'INGRESO' })}
                                    className={`flex-1 py-2 text-xs font-black uppercase tracking-widest rounded-lg transition-all ${esIngreso ? 'bg-white text-emerald-600 shadow-sm border border-slate-200' : 'text-slate-400 hover:text-slate-600'}`}
                                >
                                    <i className="fas fa-arrow-down mr-1"></i> Ingreso (Abono)
                                </button>
                                <button
                                    onClick={() => setFormManual({ ...formManual, tipo_movimiento: 'EGRESO' })}
                                    className={`flex-1 py-2 text-xs font-black uppercase tracking-widest rounded-lg transition-all ${!esIngreso ? 'bg-white text-rose-600 shadow-sm border border-slate-200' : 'text-slate-400 hover:text-slate-600'}`}
                                >
                                    <i className="fas fa-arrow-up mr-1"></i> Salida (Cargo)
                                </button>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Fecha del Movimiento</label>
                                    <input 
                                        type="date" 
                                        value={formManual.fecha} 
                                        onChange={e => setFormManual({ ...formManual, fecha: e.target.value })} 
                                        className={`w-full bg-slate-50 border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 transition-all text-sm font-bold text-slate-700 ${esIngreso ? 'focus:ring-emerald-500/30 focus:border-emerald-500' : 'focus:ring-rose-500/30 focus:border-rose-500'}`} 
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Monto de la Operación</label>
                                    <div className={`flex items-center bg-slate-50 border border-slate-200 rounded-xl overflow-hidden focus-within:ring-2 transition-all h-[46px] shadow-none ${esIngreso ? 'focus-within:ring-emerald-500/30 focus-within:border-emerald-500' : 'focus-within:ring-rose-500/30 focus-within:border-rose-500'}`}>
                                        <span className="pl-4 text-slate-400 font-bold shrink-0">$</span>
                                        <input
                                            type="number"
                                            placeholder="0"
                                            value={formManual.monto}
                                            onChange={e => setFormManual({ ...formManual, monto: e.target.value })}
                                            className={`flex-1 bg-transparent px-3 outline-none border-none shadow-none appearance-none text-sm font-black h-full m-0 ${esIngreso ? 'text-emerald-600' : 'text-rose-600'}`}
                                        />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Descripción o Detalle</label>
                                <input 
                                    type="text" 
                                    placeholder={esIngreso ? "Ej: Pago de cliente, Depósito por ventanilla..." : "Ej: Pago de servicios, Transferencia proveedor..."} 
                                    value={formManual.descripcion} 
                                    onChange={e => setFormManual({ ...formManual, descripcion: e.target.value })} 
                                    className={`w-full bg-slate-50 border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 text-sm text-slate-700 font-medium ${esIngreso ? 'focus:ring-emerald-500/30 focus:border-emerald-500' : 'focus:ring-rose-500/30 focus:border-rose-500'}`} 
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">N° Documento (Opcional)</label>
                                <input 
                                    type="text" 
                                    placeholder="N° Transacción, Cheque o TEF" 
                                    value={formManual.nro_documento} 
                                    onChange={e => setFormManual({ ...formManual, nro_documento: e.target.value })} 
                                    className={`w-full bg-slate-50 border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 text-sm text-slate-700 font-mono ${esIngreso ? 'focus:ring-emerald-500/30 focus:border-emerald-500' : 'focus:ring-rose-500/30 focus:border-rose-500'}`} 
                                />
                            </div>
                        </div>

                        <button 
                            onClick={guardarIngresoManual} 
                            className={`w-full mt-4 text-white font-bold py-3.5 rounded-xl shadow-lg transition-all flex justify-center items-center gap-2 ${esIngreso ? 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-600/30' : 'bg-rose-600 hover:bg-rose-500 shadow-rose-600/30'}`}
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Confirmar Registro {esIngreso ? 'de Ingreso' : 'de Salida'}
                        </button>
                    </div>
                </div>
            </div>

            {/* TABLA DE MOVIMIENTOS RECIENTES */}
            <div className="mt-8">
                {loading ? (
                    <div className="flex flex-col items-center justify-center py-10 text-slate-400">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-4 border-indigo-500 mb-3"></div>
                        <p className="font-bold text-sm">Cargando cartola...</p>
                    </div>
                ) : (
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                            <h3 className="font-bold text-slate-700 text-sm uppercase tracking-widest">
                                Historial de Movimientos
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-white border-b border-slate-100">
                                    <tr>
                                        <th className="px-6 py-4 font-bold text-slate-500 uppercase tracking-widest text-[10px]">Fecha</th>
                                        <th className="px-6 py-4 font-bold text-slate-500 uppercase tracking-widest text-[10px]">Descripción</th>
                                        <th className="px-6 py-4 font-bold text-slate-500 uppercase tracking-widest text-[10px] text-right">Cargo (Salida)</th>
                                        <th className="px-6 py-4 font-bold text-slate-500 uppercase tracking-widest text-[10px] text-right">Abono (Entrada)</th>
                                        <th className="px-6 py-4 font-bold text-slate-500 uppercase tracking-widest text-[10px] text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {movimientos.map(mov => (
                                        <tr key={mov.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="px-6 py-4 font-medium text-slate-600">
                                                {new Date(mov.fecha).toLocaleDateString('es-CL')}
                                            </td>
                                            <td className="px-6 py-4">
                                                <p className="font-bold text-slate-800">{mov.descripcion}</p>
                                                {mov.nro_documento && <p className="text-[10px] text-slate-400 uppercase mt-0.5">Ref: {mov.nro_documento}</p>}
                                            </td>
                                            <td className="px-6 py-4 font-black text-rose-600 text-right">
                                                {mov.cargo > 0 ? formatCurrency(mov.cargo) : '-'}
                                            </td>
                                            <td className="px-6 py-4 font-black text-emerald-600 text-right">
                                                {mov.abono > 0 ? formatCurrency(mov.abono) : '-'}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {mov.estado === 'PENDIENTE' ? (
                                                    <span className="bg-amber-100 text-amber-700 text-[10px] font-bold px-2 py-1 rounded">PENDIENTE</span>
                                                ) : (
                                                    <span className="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-1 rounded">CONCILIADO</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {movimientos.length === 0 && (
                                        <tr>
                                            <td colSpan="5" className="px-6 py-10 text-center text-slate-400 italic">No hay movimientos en esta cuenta.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default CartolaBancaria;