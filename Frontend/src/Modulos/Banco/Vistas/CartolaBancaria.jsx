import React, { useState, useEffect, useRef } from 'react';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const CartolaBancaria = () => {
    const [cuentas, setCuentas] = useState([]);
    const [cuentaActiva, setCuentaActiva] = useState('');
    const [loading, setLoading] = useState(false);
    const [archivo, setArchivo] = useState(null);
    const fileInputRef = useRef(null);

    // Modal Ingreso Manual
    const [modalIngreso, setModalIngreso] = useState(false);
    const [formIngreso, setFormIngreso] = useState({ fecha: '', descripcion: '', monto: '', nro_documento: '' });

    useEffect(() => {
        cargarCuentas();
    }, []);

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

    // --- MANEJO DEL EXCEL (AHORA ACEPTA .XLS ANTIGUOS) ---
    const handleFileChange = (e) => {
        const file = e.target.files[0];
        // Agregamos .xls a la validación
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

    // --- REGISTRO MANUAL DE INGRESOS ---
    const guardarIngresoManual = async () => {
        if (!formIngreso.monto || !formIngreso.descripcion || !formIngreso.fecha) {
            return Swal.fire('Faltan Datos', 'Complete todos los campos obligatorios.', 'warning');
        }

        try {
            const payload = { ...formIngreso, cuenta_bancaria_id: cuentaActiva };
            const res = await api.post('/banco/ingreso-manual', payload);
            
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Ingreso Registrado', timer: 1500, showConfirmButton: false });
                setFormIngreso({ fecha: '', descripcion: '', monto: '', nro_documento: '' });
                cargarCuentas();
            }
        } catch (error) {
            Swal.fire('Error', 'No se pudo registrar el ingreso.', 'error');
        }
    };

    const cuentaSeleccionada = cuentas.find(c => c.id == cuentaActiva);

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
                    <p className="text-3xl font-black text-emerald-400 truncate max-wxs">
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
                        {/* INPUT ACTUALIZADO CON ACEPTACIÓN DE .XLS */}
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

                {/* ZONA 2: INGRESO MANUAL DE DINERO */}
                <div className="bg-white p-6 md:p-8 rounded-2xl border border-slate-200 shadow-sm flex flex-col h-full">
                    <h3 className="text-lg font-black text-slate-800 flex items-center gap-2 mb-2">
                        <svg className="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Registrar Ingreso Manual
                    </h3>
                    <p className="text-sm text-slate-500 mb-6">Si un cliente te pagó o recibiste un depósito sin usar el Excel, regístralo aquí directamente.</p>

                    <div className="space-y-4 flex-1 flex flex-col justify-between">
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Fecha del Abono</label>
                                    <input type="date" value={formIngreso.fecha} onChange={e => setFormIngreso({...formIngreso, fecha: e.target.value})} className="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 text-sm font-bold text-slate-700" />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Monto Recibido</label>
                                    <div className="relative">
                                        <span className="absolute left-4 top-3 text-slate-400 font-bold">$</span>
                                        <input type="number" placeholder="0" value={formIngreso.monto} onChange={e => setFormIngreso({...formIngreso, monto: e.target.value})} className="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pl-8 outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 text-sm font-black text-emerald-600" />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Detalle / Cliente</label>
                                <input type="text" placeholder="Ej: Pago de Cotización #45 - Constructora S.A." value={formIngreso.descripcion} onChange={e => setFormIngreso({...formIngreso, descripcion: e.target.value})} className="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 text-sm text-slate-700 font-medium" />
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">N° Documento (Opcional)</label>
                                <input type="text" placeholder="N° Transacción o Cheque" value={formIngreso.nro_documento} onChange={e => setFormIngreso({...formIngreso, nro_documento: e.target.value})} className="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 text-sm text-slate-700 font-mono" />
                            </div>
                        </div>

                        <button onClick={guardarIngresoManual} className="w-full mt-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-emerald-600/30 transition-all flex justify-center items-center gap-2">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Confirmar Ingreso de Dinero
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CartolaBancaria;