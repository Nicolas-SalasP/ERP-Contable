import React, { useState, useEffect, useRef } from 'react';
import AyudaModulo from '../../../Componentes/AyudaModulo';
import EstadoCarga from '../../../Componentes/EstadoCarga';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';
import { logger } from '../../../Configuracion/logger';
import { CreditCard, Upload, FileText, Check, Plus } from 'lucide-react';
import { formatearMoneda, formatFecha } from '../../../Utilidades/formato';
const formatCurrency = formatearMoneda;

const CartolaBancaria = () => {
    const [cuentas, setCuentas] = useState([]);
    const [cuentaActiva, setCuentaActiva] = useState('');
    const [movimientos, setMovimientos] = useState([]);
    const [loading, setLoading] = useState(false);
    const [archivo, setArchivo] = useState(null);
    const fileInputRef = useRef(null);

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
            logger.error("Error cargando movimientos:", error);
        } finally {
            setLoading(false);
        }
    };

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
        formData.append('archivo', archivo);
        formData.append('cuenta_bancaria_id', cuentaActiva);

        Swal.fire({ title: 'Procesando Cartola...', text: 'Analizando ingresos y egresos...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            // api.upload() (no api.post con Content-Type manual, que rompe el boundary y vacía el body); request() ya
            // devuelve el body JSON tal cual, no destructurar "{ data }" o se pierde el envelope real de la respuesta.
            const data = await api.upload('/banco/importar', formData);

            Swal.fire({
                icon: 'success',
                title: '¡Cartola Importada!',
                text: data.message,
                customClass: { confirmButton: 'bg-emerald-600 text-white font-bold py-2 px-6 rounded-lg' },
                buttonsStyling: false
            });
            setArchivo(null);
            if (fileInputRef.current) fileInputRef.current.value = '';
            cargarMovimientos();
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

    const guardarIngresoManual = async () => {
        if (!formManual.monto || !formManual.descripcion || !formManual.fecha) {
            return Swal.fire('Faltan Datos', 'Complete todos los campos obligatorios.', 'warning');
        }

        try {
            Swal.fire({ title: 'Registrando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            const payload = { ...formManual, cuenta_bancaria_id: cuentaActiva };
            const res = await api.post('/banco/ingreso-manual', payload);

            if (res.success) {
                Swal.fire({ icon: 'success', title: '¡Registrado!', text: 'El movimiento ha sido guardado exitosamente.', timer: 1500, showConfirmButton: false });
                setFormManual({ ...formManual, descripcion: '', monto: '', nro_documento: '' });
                cargarMovimientos();
            }
        } catch (error) {
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
        <div className="max-w-7xl mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 dark:text-slate-200 animate-fade-in pb-10">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <div className="flex items-center gap-3 mb-2">
                        <span className="bg-blue-100 text-blue-700 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-blue-200">
                            Tesorería y Finanzas
                        </span>
                    </div>
                    <div className="flex items-center gap-3"><h1 className="text-3xl md:text-4xl font-black text-slate-900 dark:text-slate-100 tracking-tight">Cartola y Movimientos</h1><AyudaModulo moduloId="cartolaBancaria" /></div>
                    <p className="text-slate-500 dark:text-slate-400 font-medium mt-1">Registra ingresos manuales o importa la cartola del banco.</p>
                </div>
            </div>

            <div className="bg-slate-900 rounded-2xl p-6 shadow-xl text-white mb-8 flex flex-col md:flex-row justify-between items-center gap-6">
                <div className="w-full md:w-1/2">
                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <CreditCard size={16} strokeWidth={1.75} className="text-blue-400" />
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
                <div className="bg-white dark:bg-slate-800 p-6 md:p-8 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col h-full">
                    <h3 className="text-lg font-black text-slate-800 dark:text-slate-200 flex items-center gap-2 mb-2">
                        <Upload size={20} strokeWidth={1.75} className="text-blue-500" />
                        Importar Cartola del Banco
                    </h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">Sube el archivo Excel descargado desde tu portal bancario para registrar abonos y cargos automáticamente.</p>

                    <div
                        className={`border-2 border-dashed rounded-2xl p-8 text-center transition-colors flex-1 flex flex-col justify-center items-center ${archivo ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-slate-300 dark:border-slate-600 hover:border-blue-400 bg-slate-50 dark:bg-slate-900'}`}
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
                                <div className="bg-white dark:bg-slate-700 p-4 rounded-full shadow-sm mb-4 text-blue-500">
                                    <FileText size={32} strokeWidth={1.75} />
                                </div>
                                <p className="font-bold text-slate-700 dark:text-slate-300 mb-1">Arrastra tu Excel aquí</p>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">Acepta archivos .xlsx, .xls y .csv</p>
                                <button onClick={() => fileInputRef.current.click()} className="bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-bold py-2 px-6 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors shadow-sm text-sm">
                                    Explorar Archivos
                                </button>
                            </>
                        ) : (
                            <>
                                <div className="bg-emerald-100 text-emerald-600 p-4 rounded-full mb-4">
                                    <Check size={32} strokeWidth={1.75} />
                                </div>
                                <p className="font-bold text-slate-800 dark:text-slate-200 mb-1">{archivo.name}</p>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mb-6">{(archivo.size / 1024).toFixed(1)} KB</p>
                                <div className="flex gap-3 w-full max-w-xs">
                                    <button onClick={() => { setArchivo(null); fileInputRef.current.value = ''; }} className="flex-1 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 font-bold py-2.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors text-sm">
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

                <div className="bg-white dark:bg-slate-800 p-6 md:p-8 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col h-full">
                    <h3 className="text-lg font-black text-slate-800 dark:text-slate-200 flex items-center gap-2 mb-2">
                        <Plus size={20} strokeWidth={1.75} className={esIngreso ? 'text-emerald-500' : 'text-rose-500'} />
                        Registro Manual
                    </h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">Ingresa transacciones aisladas que no pasaron por la importación masiva de la cartola.</p>

                    <div className="space-y-4 flex-1 flex flex-col justify-between">
                        <div className="space-y-4">

                            <div className="bg-slate-100 dark:bg-slate-700 p-1 rounded-xl flex gap-1 mb-2">
                                <button
                                    onClick={() => setFormManual({ ...formManual, tipo_movimiento: 'INGRESO' })}
                                    className={`flex-1 py-2 text-xs font-black uppercase tracking-widest rounded-lg transition-all ${esIngreso ? 'bg-white dark:bg-slate-600 text-emerald-600 shadow-sm border border-slate-200 dark:border-slate-500' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'}`}
                                >
                                    <i className="fas fa-arrow-down mr-1"></i> Ingreso (Abono)
                                </button>
                                <button
                                    onClick={() => setFormManual({ ...formManual, tipo_movimiento: 'EGRESO' })}
                                    className={`flex-1 py-2 text-xs font-black uppercase tracking-widest rounded-lg transition-all ${!esIngreso ? 'bg-white dark:bg-slate-600 text-rose-600 shadow-sm border border-slate-200 dark:border-slate-500' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'}`}
                                >
                                    <i className="fas fa-arrow-up mr-1"></i> Salida (Cargo)
                                </button>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1.5">Fecha del Movimiento</label>
                                    <input
                                        type="date"
                                        value={formManual.fecha}
                                        onChange={e => setFormManual({ ...formManual, fecha: e.target.value })}
                                        className={`w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl p-3 outline-none focus:ring-2 transition-all text-sm font-bold text-slate-700 dark:text-slate-200 ${esIngreso ? 'focus:ring-emerald-500/30 focus:border-emerald-500' : 'focus:ring-rose-500/30 focus:border-rose-500'}`}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1.5">Monto de la Operación</label>
                                    <div className={`flex items-center bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl overflow-hidden focus-within:ring-2 transition-all h-[46px] shadow-none ${esIngreso ? 'focus-within:ring-emerald-500/30 focus-within:border-emerald-500' : 'focus-within:ring-rose-500/30 focus-within:border-rose-500'}`}>
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
                                <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1.5">Descripción o Detalle</label>
                                <input
                                    type="text"
                                    placeholder={esIngreso ? "Ej: Pago de cliente, Depósito por ventanilla..." : "Ej: Pago de servicios, Transferencia proveedor..."}
                                    value={formManual.descripcion}
                                    onChange={e => setFormManual({ ...formManual, descripcion: e.target.value })}
                                    className={`w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl p-3 outline-none focus:ring-2 text-sm text-slate-700 dark:text-slate-200 font-medium ${esIngreso ? 'focus:ring-emerald-500/30 focus:border-emerald-500' : 'focus:ring-rose-500/30 focus:border-rose-500'}`}
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1.5">N° Documento (Opcional)</label>
                                <input
                                    type="text"
                                    placeholder="N° Transacción, Cheque o TEF"
                                    value={formManual.nro_documento}
                                    onChange={e => setFormManual({ ...formManual, nro_documento: e.target.value })}
                                    className={`w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl p-3 outline-none focus:ring-2 text-sm text-slate-700 dark:text-slate-200 font-mono ${esIngreso ? 'focus:ring-emerald-500/30 focus:border-emerald-500' : 'focus:ring-rose-500/30 focus:border-rose-500'}`}
                                />
                            </div>
                        </div>

                        <button 
                            onClick={guardarIngresoManual} 
                            className={`w-full mt-4 text-white font-bold py-3.5 rounded-xl shadow-lg transition-all flex justify-center items-center gap-2 ${esIngreso ? 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-600/30' : 'bg-rose-600 hover:bg-rose-500 shadow-rose-600/30'}`}
                        >
                            <Check size={20} strokeWidth={1.75} />
                            Confirmar Registro {esIngreso ? 'de Ingreso' : 'de Salida'}
                        </button>
                    </div>
                </div>
            </div>

            <div className="mt-8">
                {loading ? (
                    <EstadoCarga
                        cargando={true}
                        mensajeCargando="Cargando cartola..."
                        tamano="compacto"
                        color="indigo"
                    />
                ) : (
                    <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
                            <h3 className="font-bold text-slate-700 dark:text-slate-300 text-sm uppercase tracking-widest">
                                Historial de Movimientos
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="sticky top-0 z-10 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
                                    <tr>
                                        <th className="px-6 py-4 font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-[10px]">Fecha</th>
                                        <th className="px-6 py-4 font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-[10px]">Descripción</th>
                                        <th className="px-6 py-4 font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-[10px] text-right">Cargo (Salida)</th>
                                        <th className="px-6 py-4 font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-[10px] text-right">Abono (Entrada)</th>
                                        <th className="px-6 py-4 font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-[10px] text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50 dark:divide-slate-700">
                                    {movimientos.map(mov => (
                                        <tr key={mov.id} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                            <td className="px-6 py-4 font-medium text-slate-600 dark:text-slate-400">
                                                {formatFecha(mov.fecha)}
                                            </td>
                                            <td className="px-6 py-4">
                                                <p className="font-bold text-slate-800 dark:text-slate-200">{mov.descripcion}</p>
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
                                        <EstadoVacio mensaje="Sin movimientos en el período." />
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