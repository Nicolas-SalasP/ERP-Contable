import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';
import Select from 'react-select';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const AsientoManual = () => {
    const [loading, setLoading] = useState(false);
    const [planCuentas, setPlanCuentas] = useState([]);
    const [centrosCosto, setCentrosCosto] = useState([]);
    const [fecha, setFecha] = useState(new Date().toISOString().split('T')[0]);
    const [glosaGeneral, setGlosaGeneral] = useState('');
    const [filas, setFilas] = useState([]);
    const [cuentaActual, setCuentaActual] = useState(null);
    const [tipoMovimiento, setTipoMovimiento] = useState('debe'); 
    const [montoActual, setMontoActual] = useState('');
    const [glosaDetalleActual, setGlosaDetalleActual] = useState('');
    const [centroCostoActual, setCentroCostoActual] = useState(null);
    const [empleadoActual, setEmpleadoActual] = useState('');

    useEffect(() => {
        cargarDatosBase();
    }, []);

    const cargarDatosBase = async () => {
        try {
            const resPlan = await api.get('/banco/cuentas-imputables');
            if (resPlan.success) {
                const cuentasFormat = resPlan.data.map(c => ({
                    value: c.codigo, label: `[${c.codigo}] ${c.nombre}`
                }));
                setPlanCuentas(cuentasFormat);
            }
            try {
                const resCentros = await api.get('/empresas/centros-costo');
                if (resCentros.success) setCentrosCosto(resCentros.data);
            } catch (err) { console.warn("Sin centros de costo."); }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los datos base.' });
        }
    };

    const agregarLinea = () => {
        if (!cuentaActual) return Swal.fire({ icon: 'warning', text: 'Debes seleccionar una cuenta contable.' });
        const montoEntero = parseInt(montoActual, 10);
        if (!montoEntero || montoEntero <= 0) return Swal.fire({ icon: 'warning', text: 'El monto debe ser un número mayor a cero.' });

        const nuevaFila = {
            id: Date.now(),
            cuenta: cuentaActual,
            debe: tipoMovimiento === 'debe' ? montoEntero : 0,
            haber: tipoMovimiento === 'haber' ? montoEntero : 0,
            glosa: glosaDetalleActual,
            centroCosto: centroCostoActual,
            empleado: empleadoActual
        };

        setFilas([...filas, nuevaFila]);
        limpiarFormularioInferior();
    };

    const limpiarFormularioInferior = () => {
        setCuentaActual(null);
        setMontoActual('');
        setGlosaDetalleActual('');
        setCentroCostoActual(null);
        setEmpleadoActual('');
        setTipoMovimiento(tipoMovimiento === 'debe' ? 'haber' : 'debe');
    };

    const editarFila = (fila) => {
        setCuentaActual(fila.cuenta);
        setTipoMovimiento(fila.debe > 0 ? 'debe' : 'haber');
        setMontoActual(fila.debe > 0 ? fila.debe : fila.haber);
        setGlosaDetalleActual(fila.glosa || '');
        setCentroCostoActual(fila.centroCosto);
        setEmpleadoActual(fila.empleado || '');
        setFilas(filas.filter(f => f.id !== fila.id));
    };

    const eliminarFila = (id, e) => {
        e.stopPropagation();
        setFilas(filas.filter(f => f.id !== id));
    };

    const handleKeyDown = (e) => {
        if (e.key === '.' || e.key === ',') {
            e.preventDefault();
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            agregarLinea();
        }
    };

    const totalDebe = filas.reduce((sum, fila) => sum + fila.debe, 0);
    const totalHaber = filas.reduce((sum, fila) => sum + fila.haber, 0);
    const diferencia = Math.abs(totalDebe - totalHaber);
    const estaCuadrado = totalDebe === totalHaber && totalDebe > 0 && filas.length >= 2;

    const guardarAsiento = async () => {
        if (!glosaGeneral.trim()) return Swal.fire({ icon: 'warning', text: 'La glosa general es obligatoria.' });
        if (filas.length < 2) return Swal.fire({ icon: 'warning', text: 'El comprobante debe tener al menos 2 líneas.' });
        if (!estaCuadrado) return Swal.fire({ icon: 'warning', text: 'El asiento no está cuadrado.' });

        const detallesFormateados = filas.map(f => ({
            cuenta_contable: f.cuenta.value,
            debe: f.debe,
            haber: f.haber,
            tipo_operacion: f.debe > 0 ? 'DEBE' : 'HABER',
            glosa_detalle: f.glosa.trim() || null,
            centro_costo_id: f.centroCosto ? f.centroCosto.value : null,
            empleado_nombre: f.empleado.trim() || null
        }));

        const payload = { fecha, glosa: glosaGeneral, detalles: detallesFormateados };

        setLoading(true);
        try {
            const res = await api.post('/contabilidad/asiento-manual/avanzado', payload);
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Comprobante Generado', text: `Folio: ${res.data?.numero_comprobante || ''}`, confirmButtonColor: '#10b981' });
                setGlosaGeneral('');
                setFilas([]);
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Error al guardar.' });
        } finally {
            setLoading(false);
        }
    };

    const selectStyles = {
        control: (base, state) => ({
            ...base, backgroundColor: '#ffffff', borderColor: state.isFocused ? '#6366f1' : '#cbd5e1',
            boxShadow: state.isFocused ? '0 0 0 1px #6366f1' : 'none', minHeight: '42px', fontSize: '0.875rem'
        }),
        menuPortal: base => ({ ...base, zIndex: 9999 })
    };

    return (
        <div className="max-w-[95rem] mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 animate-fade-in pb-20">
            
            <div className="mb-6 flex justify-between items-end">
                <div>
                    <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Asiento Manual</h1>
                    <p className="text-slate-500 font-medium mt-1">Ingreso de traspasos y ajustes contables de partida doble.</p>
                </div>
            </div>

            <div className="space-y-6">
                <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row gap-6">
                    <div className="w-full md:w-1/4">
                        <label className="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Fecha Contable</label>
                        <input 
                            type="date" value={fecha} onChange={(e) => setFecha(e.target.value)}
                            className="w-full bg-slate-50 border border-slate-300 text-slate-800 font-bold rounded-xl p-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        />
                    </div>
                    <div className="w-full md:w-3/4">
                        <label className="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Glosa General del Comprobante</label>
                        <input 
                            type="text" value={glosaGeneral} onChange={(e) => setGlosaGeneral(e.target.value)} placeholder="Ej: Reconocimiento de gastos bancarios..."
                            className="w-full bg-slate-50 border border-slate-300 text-slate-800 font-bold rounded-xl p-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        />
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="bg-slate-900 px-6 py-3 border-b border-slate-800 flex justify-between items-center">
                        <h3 className="text-white font-bold text-sm tracking-wide"><i className="fas fa-list-ul mr-2 text-indigo-400"></i> Detalle del Asiento</h3>
                        <span className="text-slate-400 text-xs font-bold bg-slate-800 px-3 py-1 rounded-full">{filas.length} líneas</span>
                    </div>
                    <div className="overflow-x-auto min-h-[200px]">
                        {filas.length === 0 ? (
                            <div className="flex flex-col items-center justify-center h-48 text-slate-400">
                                <i className="fas fa-inbox text-4xl mb-3 text-slate-200"></i>
                                <p className="font-medium">El asiento está vacío.</p>
                                <p className="text-xs">Utiliza el formulario inferior para registrar las cuentas.</p>
                            </div>
                        ) : (
                            <table className="w-full text-left border-collapse min-w-[1100px]">
                                <thead className="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th className="px-4 py-3 font-black text-slate-400 text-[10px] uppercase w-12 text-center">#</th>
                                        <th className="px-4 py-3 font-black text-slate-600 text-xs uppercase w-[20%]">Cuenta</th>
                                        <th className="px-4 py-3 font-black text-slate-500 text-xs uppercase w-[20%]">Glosa Línea</th>
                                        <th className="px-4 py-3 font-black text-emerald-600 text-xs uppercase w-32 text-right">Debe</th>
                                        <th className="px-4 py-3 font-black text-rose-600 text-xs uppercase w-32 text-right">Haber</th>
                                        <th className="px-4 py-3 font-black text-slate-500 text-xs uppercase">C. Costo</th>
                                        <th className="px-4 py-3 font-black text-slate-500 text-xs uppercase">Empleado</th>
                                        <th className="px-4 py-3 font-black text-slate-400 text-xs uppercase text-center w-24"><i className="fas fa-cog"></i></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filas.map((fila, index) => (
                                        <tr 
                                            key={fila.id} 
                                            className="hover:bg-indigo-50/40 transition-colors cursor-pointer group"
                                            onContextMenu={(e) => { e.preventDefault(); editarFila(fila); }}
                                            onDoubleClick={() => editarFila(fila)}
                                            title="Click Derecho para editar línea"
                                        >
                                            <td className="px-4 py-3 text-center text-xs font-bold text-slate-400">{index + 1}</td>
                                            <td className="px-4 py-3 text-sm font-bold text-slate-700 truncate max-w-[200px]">{fila.cuenta.label}</td>
                                            <td className="px-4 py-3 text-xs text-slate-500 truncate max-w-[200px] italic">{fila.glosa || '-'}</td>
                                            <td className="px-4 py-3 text-sm font-mono font-black text-emerald-600 text-right">{fila.debe > 0 ? formatCurrency(fila.debe) : ''}</td>
                                            <td className="px-4 py-3 text-sm font-mono font-black text-rose-600 text-right">{fila.haber > 0 ? formatCurrency(fila.haber) : ''}</td>
                                            <td className="px-4 py-3 text-xs text-slate-500">{fila.centroCosto ? fila.centroCosto.label : '-'}</td>
                                            <td className="px-4 py-3 text-xs text-slate-500">{fila.empleado || '-'}</td>
                                            <td className="px-4 py-3 text-center opacity-0 group-hover:opacity-100 transition-opacity flex justify-center gap-2">
                                                <button onClick={(e) => { e.stopPropagation(); editarFila(fila); }} className="text-indigo-400 hover:text-indigo-600 bg-white p-1.5 rounded-md shadow-sm border border-slate-200">
                                                    <i className="fas fa-pencil-alt"></i>
                                                </button>
                                                <button onClick={(e) => eliminarFila(fila.id, e)} className="text-rose-400 hover:text-rose-600 bg-white p-1.5 rounded-md shadow-sm border border-slate-200">
                                                    <i className="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    <div className="bg-slate-50 border-t border-slate-200 p-4 flex justify-end gap-12 pr-16">
                        <div className="text-right">
                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Debe</p>
                            <p className={`text-2xl font-mono font-black ${totalDebe > 0 ? 'text-emerald-600' : 'text-slate-400'}`}>{formatCurrency(totalDebe)}</p>
                        </div>
                        <div className="text-right">
                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Haber</p>
                            <p className={`text-2xl font-mono font-black ${totalHaber > 0 ? 'text-rose-600' : 'text-slate-400'}`}>{formatCurrency(totalHaber)}</p>
                        </div>
                    </div>
                </div>

                <div className="bg-indigo-50/50 border border-indigo-100 rounded-2xl p-6 shadow-inner">
                    <h4 className="text-indigo-800 font-bold text-sm mb-4 uppercase tracking-wider flex items-center gap-2">
                        <i className="fas fa-keyboard"></i> Panel de Ingreso de Cuentas
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-end mb-4">
                        <div className="md:col-span-3">
                            <label className="block text-[10px] font-black text-indigo-900/60 uppercase mb-1">Cuenta Contable *</label>
                            <Select 
                                options={planCuentas} value={cuentaActual} onChange={setCuentaActual}
                                placeholder="Buscar cuenta..." styles={selectStyles} menuPortalTarget={document.body} menuPosition="fixed"
                            />
                        </div>

                        <div className="md:col-span-2">
                            <label className="block text-[10px] font-black text-indigo-900/60 uppercase mb-1">Tipo *</label>
                            <div className="flex bg-white rounded-lg border border-slate-300 p-1 h-[42px]">
                                <button 
                                    onClick={() => setTipoMovimiento('debe')}
                                    className={`flex-1 text-xs font-bold rounded-md transition-all ${tipoMovimiento === 'debe' ? 'bg-emerald-100 text-emerald-700 shadow-sm' : 'text-slate-400 hover:bg-slate-50'}`}
                                >
                                    DEBE
                                </button>
                                <button 
                                    onClick={() => setTipoMovimiento('haber')}
                                    className={`flex-1 text-xs font-bold rounded-md transition-all ${tipoMovimiento === 'haber' ? 'bg-rose-100 text-rose-700 shadow-sm' : 'text-slate-400 hover:bg-slate-50'}`}
                                >
                                    HABER
                                </button>
                            </div>
                        </div>

                        <div className="md:col-span-3">
                            <label className="block text-[10px] font-black text-indigo-900/60 uppercase mb-1">Monto *</label>
                            <div className={`flex items-center bg-white border rounded-lg overflow-hidden h-[42px] transition-all ${tipoMovimiento === 'debe' ? 'focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-100 border-slate-300' : 'focus-within:border-rose-400 focus-within:ring-2 focus-within:ring-rose-100 border-slate-300'}`}>
                                <span className="bg-slate-100 text-slate-500 font-bold px-3 h-full flex items-center border-r border-slate-200">$</span>
                                <input 
                                    type="number" min="0" step="1" value={montoActual} 
                                    onChange={(e) => setMontoActual(e.target.value)} 
                                    onKeyDown={handleKeyDown}
                                    placeholder="0" 
                                    className="w-full py-2 px-3 font-mono font-black text-slate-800 outline-none"
                                />
                            </div>
                        </div>

                        <div className="md:col-span-4">
                            <label className="block text-[10px] font-black text-indigo-900/60 uppercase mb-1">Glosa Específica (Opcional)</label>
                            <input 
                                type="text" value={glosaDetalleActual} onChange={(e) => setGlosaDetalleActual(e.target.value)} onKeyDown={handleKeyDown}
                                placeholder="Detalle de esta cuenta..." 
                                className="w-full h-[42px] bg-white border border-slate-300 text-slate-700 text-sm rounded-lg px-3 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                        <div className="md:col-span-4">
                            <label className="block text-[10px] font-black text-indigo-900/60 uppercase mb-1">Centro de Costo</label>
                            <Select 
                                options={centrosCosto} value={centroCostoActual} onChange={setCentroCostoActual}
                                placeholder="Seleccionar..." styles={selectStyles} menuPortalTarget={document.body} menuPosition="fixed" isClearable
                            />
                        </div>

                        <div className="md:col-span-4">
                            <label className="block text-[10px] font-black text-indigo-900/60 uppercase mb-1">Empleado</label>
                            <input 
                                type="text" value={empleadoActual} onChange={(e) => setEmpleadoActual(e.target.value)} onKeyDown={handleKeyDown}
                                placeholder="Nombre empleado..." 
                                className="w-full h-[42px] bg-white border border-slate-300 text-slate-700 text-sm rounded-lg px-3 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                            />
                        </div>

                        <div className="md:col-span-4">
                            <button 
                                onClick={agregarLinea}
                                className="w-full h-[42px] bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-lg flex justify-center items-center gap-2 shadow-md shadow-indigo-600/20 transition-all hover:scale-[1.02]"
                            >
                                <i className="fas fa-plus"></i> AGREGAR LÍNEA (Enter ↵)
                            </button>
                        </div>
                    </div>
                </div>

                {filas.length >= 2 && (
                    <div className="flex justify-end mt-8 animate-fade-in">
                        <button 
                            onClick={guardarAsiento} disabled={!estaCuadrado || loading}
                            className={`font-black py-4 px-10 rounded-xl flex items-center gap-3 transition-all duration-300 text-lg ${
                                estaCuadrado ? 'bg-slate-900 hover:bg-black text-white shadow-xl hover:-translate-y-1' : 'bg-slate-200 text-slate-400 cursor-not-allowed'
                            }`}
                        >
                            {loading ? <i className="fas fa-spinner fa-spin"></i> : <i className="fas fa-save"></i>}
                            CONTABILIZAR ASIENTO
                        </button>
                    </div>
                )}

            </div>
        </div>
    );
};

export default AsientoManual;